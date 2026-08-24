<?php

class LaravelBuildChecker
{
    // Syntax / parse / if-else closing // major
    // Try-catch validation // major
    // Incomplete/commented try-catch // major
    // N+1 queries // medium
    // DB::table() usage - not required
    // Undefined variables // major
    // Null / chained object access // major
    // Laravel DB relationship return // major
    // Undefined array keys // major
    // SQL injection / unsafe raw SQL // major --check
    // Debug code(dd,return,echo,die,exit added by mistake) // major
    // Sensitive Laravel logging(laravel log added by mistake) // major
    // Undefined/unresolved methods/functions // major
    // Missing class/import detection // major
    // Redundant collection fetching // major
    // Suspicious model field/relation usage // major
    // DB transaction commit/rollback // major
    // Sensitive variable/input usage // major
    // Large query detection // medium
    // Composer/environment validation // major --check nd add

    private $projectPath;
    private $foldersToScan = [
        'app',
        'routes',
        'config',
        'database',
        'resources',
    ];
    private $errors = [];
    private $severityCount = ['CRITICAL' => 0, 'WARNING' => 0, 'INFO' => 0];

    private $frameworkMethods = [
        'where',
        'whereIn',
        'whereNotIn',
        'whereNull',
        'whereNotNull',
        'whereHas',
        'whereDoesntHave',
        'orWhere',
        'orderBy',
        'orderByDesc',
        'groupBy',
        'having',
        'select',
        'selectRaw',
        'addSelect',
        'join',
        'leftJoin',
        'rightJoin',
        'with',
        'withCount',
        'withSum',
        'withAvg',
        'withMin',
        'withMax',
        'load',
        'loadMissing',
        'find',
        'findOrFail',
        'first',
        'firstOrFail',
        'get',
        'all',
        'paginate',
        'simplePaginate',
        'cursor',
        'chunk',
        'chunkById',
        'pluck',
        'value',
        'count',
        'exists',
        'sum',
        'avg',
        'min',
        'max',
        'create',
        'createMany',
        'make',
        'fill',
        'update',
        'save',
        'delete',
        'forceDelete',
        'restore',
        'increment',
        'decrement',
        'toArray',
        'toJson',
        'fresh',
        'refresh',
        'replicate',
        'each',
        'map',
        'filter',
        'sortBy',
        'sortByDesc',
        'firstWhere',
        'keyBy',
        'unique',
        'collect',
        'query',
        'from',
        'table',
        'raw',
    ];

    private $knownPhpFunctions = [
        'isset',
        'empty',
        'array_key_exists',
        'count',
        'sizeof',
        'in_array',
        'array_merge',
        'array_map',
        'array_filter',
        'array_column',
        'array_values',
        'array_keys',
        'explode',
        'implode',
        'trim',
        'strtolower',
        'strtoupper',
        'strlen',
        'substr',
        'str_replace',
        'preg_match',
        'preg_replace',
        'json_encode',
        'json_decode',
        'is_array',
        'is_string',
        'is_numeric',
        'is_null',
        'is_object',
        'is_bool',
        'is_int',
        'is_float',
        'intval',
        'floatval',
        'boolval',
        'date',
        'strtotime',
        'time',
        'print_r',
        'var_dump',
        'var_export',
        'serialize',
        'unserialize',
        'sprintf',
        'number_format',
        'round',
        'floor',
        'ceil',
        'defined',
        'define',
        'class_exists',
        'method_exists',
        'function_exists',
        'file_get_contents',
        'file_put_contents',
        'fopen',
        'fclose',
        'mkdir',
        'rmdir',
        'unlink',
        'basename',
        'dirname',
        'pathinfo',
        'header',
        'http_response_code',
        'set_time_limit',
        'ini_set',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'die',
        'exit',
    ];

    private $sensitiveNames = [
        'password',
        'passwd',
        'secret',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'authorization',
        'bearer',
        'private_key',
        'client_secret',
        'credit_card',
        'card_number',
        'ssn',
        'otp',
    ];

    public function __construct($path)
    {
        $this->projectPath = rtrim($path, '/\\');
    }

    public function scan()
    {
        echo "Scanning project at: {$this->projectPath}\n";
        echo "--------------------------------------------------------\n";

        // Project Level Checks
        $this->checkComposerEnvironment();

        $files = $this->getPhpFiles();
        echo "Found " . count($files) . " PHP files to analyze.\n";

        foreach ($files as $file) {
            $this->analyzeFile($file);
        }

        $this->generateReport();
    }

    private function getPhpFiles()
    {
        $phpFiles = [];
        foreach ($this->foldersToScan as $folder) {
            $dir = $this->projectPath . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($dir))
                continue;

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $phpFiles[] = $file->getPathname();
                }
            }
        }
        return $phpFiles;
    }

    private function analyzeFile($file)
    {
        $content = file_get_contents($file);
        $tokens = token_get_all($content);
        $relativePath = str_replace($this->projectPath . DIRECTORY_SEPARATOR, '', $file);

        $this->checkSyntax($file, $relativePath);
        $this->checkTryCatch($tokens, $relativePath);
        // $this->checkNPlusOne($tokens, $relativePath); // MEDIUM
        // $this->checkQueryTableUsage($tokens, $relativePath); // NOT REQUIRED
        $this->checkVariableDefine($tokens, $relativePath);
        $this->checkNullError($tokens, $relativePath);
        $this->checkDbRelation($tokens, $relativePath); // MAJOR
        $this->checkUndefinedArrayKeys($tokens, $relativePath);
        $this->checkSqlInjection($tokens, $relativePath);
        $this->checkDebugCode($tokens, $relativePath); // MAJOR
        $this->checkSensitiveLogging($tokens, $relativePath); // MAJOR
        $this->checkUndefinedCalls($tokens, $relativePath); // MAJOR
        $this->checkMissingImports($tokens, $relativePath, $content);
        $this->checkRedundantCollectionFetch($tokens, $relativePath); // MAJOR
        $this->checkDangerousQueries($tokens, $relativePath); // MAJOR
        $this->checkEnvUsage($tokens, $relativePath); // MAJOR
        $this->checkExternalApiSafety($tokens, $relativePath); // MAJOR
        $this->checkSuspiciousModelFields($tokens, $relativePath); // MAJOR
        $this->checkDbTransaction($tokens, $relativePath);
    }

    private function checkComposerEnvironment()
    {
        if (!file_exists($this->projectPath . DIRECTORY_SEPARATOR . '.env')) {
            $this->addError('Project Root', 0, "Environment Validation: Missing .env file in the project root.");
        }
        if (!file_exists($this->projectPath . DIRECTORY_SEPARATOR . 'composer.json')) {
            $this->addError('Project Root', 0, "Composer Validation: Missing composer.json file in the project root.");
        }
    }

    private function checkSyntax($file, $relativePath)
    {
        $output = [];
        $returnVar = 0;
        exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnVar);

        if ($returnVar !== 0) {
            $message = implode(" ", $output);
            if (strpos($message, 'unexpected') !== false || strpos($message, 'syntax error') !== false) {
                $this->addError($relativePath, 0, "Syntax Error / If-Else Close Proper Error: " . trim(str_replace("Errors parsing", "", $message)));
            } else {
                $this->addError($relativePath, 0, "Syntax Error: " . trim($message));
            }
        }
    }

    private function checkTryCatch($tokens, $relativePath)
    {
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_CATCH) {
                $foundContent = false;
                $i = $index + 1;
                $openBraces = 0;
                while (isset($tokens[$i])) {
                    if ($tokens[$i] === '{')
                        $openBraces++;
                    elseif ($tokens[$i] === '}') {
                        $openBraces--;
                        if ($openBraces === 0)
                            break;
                    }
                    if ($openBraces > 0 && is_array($tokens[$i]) && !in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_VARIABLE])) {
                        $foundContent = true;
                    }
                    $i++;
                }
                if (!$foundContent && $openBraces === 0) {
                    $this->addError($relativePath, $token[2], "Incomplete/Commented Try-Catch Error: Empty catch block found. Exceptions should be handled properly.");
                }
            }
        }
    }

    private function checkNPlusOne($tokens, $relativePath)
    {
        $inLoop = 0;
        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_FOR, T_FOREACH, T_WHILE]))
                    $inLoop++;
            } else {
                if ($token === '{' && $inLoop > 0)
                    $inLoop++;
                elseif ($token === '}' && $inLoop > 0)
                    $inLoop--;
            }

            if ($inLoop > 0 && is_array($token)) {
                if ($token[0] === T_STRING) {
                    $val = strtolower($token[1]);
                    if (in_array($val, ['find', 'first', 'get', 'all', 'paginate'])) {
                        if (isset($tokens[$index - 1]) && is_array($tokens[$index - 1]) && in_array($tokens[$index - 1][0], [T_OBJECT_OPERATOR, T_PAAMAYIM_NEKUDOTAYIM])) {
                            $this->addError($relativePath, $token[2], "Query with N+1 Error: Possible database query `{$token[1]}()` executed inside a loop.");
                        }
                    }
                }
            }
        }
    }

    private function checkQueryTableUsage($tokens, $relativePath)
    {
        // DB::table() checking has been disabled/commented out per user request.
        /*
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] === 'table') {
                if (isset($tokens[$index - 1]) && is_array($tokens[$index - 1]) && $tokens[$index - 1][0] === T_PAAMAYIM_NEKUDOTAYIM) {
                    if (isset($tokens[$index - 2]) && is_array($tokens[$index - 2]) && $tokens[$index - 2][1] === 'DB') {
                        $this->addError($relativePath, $token[2], "DB::table() Usage Error: Usage of raw DB::table() detected. Prefer using Eloquent Models.");
                    }
                }
            }
        }
        */
    }

    private function checkVariableDefine($tokens, $relativePath)
    {
        $definedVariables = ['$this', '$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_SESSION', '$_COOKIE', '$_FILES'];
        foreach ($tokens as $index => $token) {
            if (is_array($token) && in_array($token[0], [T_FUNCTION, T_CLASS])) {
                $definedVariables = ['$this', '$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_SESSION', '$_COOKIE', '$_FILES'];
            }

            if (is_array($token) && $token[0] === T_VARIABLE) {
                $varName = $token[1];
                $isAssignment = false;

                for ($i = $index + 1; $i < $index + 5; $i++) {
                    if (isset($tokens[$i])) {
                        if (is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE)
                            continue;
                        if ($tokens[$i] === '=' || (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL, T_MOD_EQUAL]))) {
                            $isAssignment = true;
                            $definedVariables[] = $varName;
                            break;
                        } else
                            break;
                    }
                }

                if (!$isAssignment) {
                    $prevValidToken = $this->getPreviousMeaningfulToken($tokens, $index);

                    if (is_array($prevValidToken) && ($prevValidToken[0] === T_AS || $prevValidToken[0] === T_GLOBAL || $prevValidToken[0] === T_STATIC)) {
                        $definedVariables[] = $varName;
                        $isAssignment = true;
                    } elseif ($prevValidToken === 'as') {
                        $definedVariables[] = $varName;
                        $isAssignment = true;
                    } elseif ($prevValidToken === '(' || $prevValidToken === ',' || (is_array($prevValidToken) && $prevValidToken[0] === T_USE)) {
                        $isParam = false;
                        for ($i = $index - 1; $i >= 0; $i--) {
                            $t = is_array($tokens[$i]) ? $tokens[$i][0] : $tokens[$i];
                            if ($t === '{' || $t === '}' || $t === ';')
                                break;
                            if ($t === T_FUNCTION || $t === T_CATCH || $t === T_USE) {
                                $isParam = true;
                                break;
                            }
                            if ($t === T_IF || $t === T_WHILE || $t === T_FOR || $t === T_SWITCH || $t === T_ECHO || $t === T_RETURN) {
                                break;
                            }
                        }
                        if ($isParam) {
                            $definedVariables[] = $varName;
                            $isAssignment = true;
                        }
                    }
                }

                if (!$isAssignment && !in_array($varName, $definedVariables)) {
                    $prevValid = $this->getPreviousMeaningfulToken($tokens, $index);
                    if (is_array($prevValid))
                        $prevValid = $prevValid[0];

                    if ($prevValid !== T_ISSET && $prevValid !== T_EMPTY && $prevValid !== T_GLOBAL && $prevValid !== T_STATIC) {
                        $this->addError($relativePath, $token[2], "Undefined Variable Error: Variable `{$varName}` is used before it is defined.");
                    }
                }
            }
        }
    }

    private function checkNullError($tokens, $relativePath)
    {
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_OBJECT_OPERATOR) {
                $prev = $this->getPreviousMeaningfulToken($tokens, $index);
                if (is_array($prev) && $prev[0] === T_VARIABLE) {
                    if (isset($tokens[$index - 1]) && is_array($tokens[$index - 1]) && $tokens[$index - 1][0] === T_OBJECT_OPERATOR) {
                        $this->addError($relativePath, $token[2], "Null / Chained Object Access Error: Chained access `->` used. Consider using `?->` to prevent Call to a member function on null.");
                    }
                }
            }
        }
    }

    private function checkDbRelation($tokens, $relativePath)
    {
        $relations = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'hasOneThrough', 'hasManyThrough', 'morphTo', 'morphOne', 'morphMany', 'morphToMany', 'morphedByMany'];
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_STRING && in_array($token[1], $relations)) {
                $hasReturn = false;
                for ($i = $index - 1; $i >= 0; $i--) {
                    if (is_array($tokens[$i]) && $tokens[$i][0] === T_RETURN) {
                        $hasReturn = true;
                        break;
                    }
                    if ($tokens[$i] === ';' || $tokens[$i] === '{' || $tokens[$i] === '}')
                        break;
                }
                if (!$hasReturn) {
                    $this->addError($relativePath, $token[2], "Laravel DB Relationship Return Error: Relation method `{$token[1]}` is called but not returned. Make sure to `return \$this->{$token[1]}(...);`");
                }
            }
        }
    }

    private function checkUndefinedArrayKeys($tokens, $relativePath)
    {
        foreach ($tokens as $index => $token) {
            if ($token === '[') {
                $prev = $this->getPreviousMeaningfulToken($tokens, $index);
                if (is_array($prev) && $prev[0] === T_VARIABLE) {
                    $inIsset = false;
                    for ($i = $index; $i >= 0; $i--) {
                        if (is_array($tokens[$i]) && ($tokens[$i][0] === T_ISSET || $tokens[$i][0] === T_EMPTY)) {
                            $inIsset = true;
                            break;
                        }
                        if ($tokens[$i] === ';' || $tokens[$i] === '{' || $tokens[$i] === '}')
                            break;
                    }
                    if (!$inIsset) {
                        $this->addError($relativePath, $prev[2], "Undefined Array Keys Warning: Array access on `{$prev[1]}` without isset/empty check may cause Undefined Array Key exceptions.");
                    }
                }
            }
        }
    }

    private function checkSqlInjection($tokens, $relativePath)
    {
        $rawMethods = ['whereRaw', 'orWhereRaw', 'havingRaw', 'orHavingRaw', 'orderByRaw', 'selectRaw', 'raw'];
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_STRING && in_array($token[1], $rawMethods)) {
                for ($i = $index + 1; $i < $index + 10; $i++) {
                    if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_VARIABLE) {
                        $this->addError($relativePath, $token[2], "SQL Injection / Unsafe Raw SQL: Variable interpolation detected in `{$token[1]}`. Make sure to use prepared bindings.");
                        break;
                    }
                    if (isset($tokens[$i]) && $tokens[$i] === ')')
                        break;
                }
            }
        }
    }

    private function checkDebugCode($tokens, $relativePath)
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_EXIT) {
                $this->addError($relativePath, $token[2], "Debug Code Error: Termination code `{$token[1]}` found left in the code.");
                continue;
            }

            if ($token[0] === T_ECHO) {
                $this->addError($relativePath, $token[2], "Debug Code Error: `echo` statement found left in the code.");
                continue;
            }

            $debugFunctions = ['dd', 'dump', 'var_dump', 'print_r', 'ray'];
            if ($token[0] === T_STRING && in_array($token[1], $debugFunctions)) {
                $prev = $this->getPreviousMeaningfulToken($tokens, $index);
                if (!is_array($prev) || ($prev[0] !== T_OBJECT_OPERATOR && $prev[0] !== T_PAAMAYIM_NEKUDOTAYIM && $prev[0] !== T_FUNCTION)) {
                    $this->addError($relativePath, $token[2], "Debug Code Error: `{$token[1]}()` statement found left in the code.");
                }
            }
        }
    }

    private function checkSensitiveLogging($tokens, $relativePath)
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $method = strtolower($token[1]);

            if (!in_array($method, ['info', 'debug', 'warning', 'error', 'critical', 'alert', 'emergency', 'notice'], true)) {
                continue;
            }

            $previous = $this->getPreviousMeaningfulToken($tokens, $index);

            if (
                is_array($previous) &&
                $previous[0] === T_DOUBLE_COLON
            ) {
                $expression = $this->tokensUntilClosingParen($tokens, $index);

                foreach ($this->sensitiveNames as $sensitive) {
                    if (stripos($expression, $sensitive) !== false) {
                        $this->addError(
                            $relativePath,
                            $token[2],
                            "Sensitive Laravel Logging Error: Laravel Log method `{$token[1]}()` appears to contain `{$sensitive}` data."
                        );
                        break;
                    }
                }
            }
        }
    }

    private function checkUndefinedCalls($tokens, $relativePath)
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $next = $this->getNextMeaningfulToken($tokens, $index);

            if ($next !== '(') {
                continue;
            }

            $name = $token[1];

            if (in_array(strtolower($name), $this->knownPhpFunctions, true)) {
                continue;
            }

            if ($this->isLikelyMethodCall($tokens, $index)) {
                if (in_array(strtolower($name), $this->frameworkMethods, true)) {
                    continue;
                }

                // User-defined method calls cannot be reliably resolved with tokens alone.
                $this->addError(
                    $relativePath,
                    $token[2],
                    "Method/function call `{$name}()` could not be resolved statically. Verify the method/function exists."
                );
            }
        }
    }

    private function isLikelyMethodCall($tokens, $index)
    {
        $previous = $this->getPreviousMeaningfulToken($tokens, $index);
        return is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_PAAMAYIM_NEKUDOTAYIM], true);
    }

    private function tokensUntilClosingParen($tokens, $index)
    {
        $result = '';
        $started = false;
        $depth = 0;

        for ($i = $index; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '(') {
                $depth++;
                $started = true;
            }

            if ($started) {
                $result .= $text;
            }

            if ($text === ')') {
                $depth--;
                if ($started && $depth === 0) {
                    break;
                }
            }
        }
        return $result;
    }

    private function checkMissingImports($tokens, $relativePath, $content)
    {
        $declaredClasses = [];
        $imports = [];
        $namespace = '';

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readQualifiedName($tokens, $index + 1);
            }

            if ($token[0] === T_USE) {
                $imports[] = $this->readQualifiedName($tokens, $index + 1);
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                $name = $this->getNextIdentifier($tokens, $index);

                if ($name !== null) {
                    $declaredClasses[] = $name;
                }
            }

            if ($token[0] === T_NEW) {
                $name = $this->readQualifiedName($tokens, $index + 1);

                if ($name !== '' && !$this->isBuiltinClass($name) && !$this->hasMatchingImport($name, $imports)) {
                    $base = basename(str_replace('\\', '/', $name));

                    if (
                        $base !== '' &&
                        !in_array($base, $declaredClasses, true) &&
                        strpos($content, "class $base") === false &&
                        !class_exists($name)
                    ) {
                        $this->addError(
                            $relativePath,
                            $token[2],
                            "Missing Class/Import Detection: Class `{$name}` is instantiated but no matching import was detected."
                        );
                    }
                }
            }
        }
    }

    private function readQualifiedName(array $tokens, int $start): string
    {
        $name = '';
        for ($i = $start; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true) && $name === '') {
                    continue;
                }

                if (in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                    $name .= $token[1];
                } elseif (defined('T_NAME_QUALIFIED') && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                    $name .= $token[1];
                } else {
                    break;
                }
            } else {
                if ($token === '\\') {
                    $name .= '\\';
                } else {
                    break;
                }
            }
        }

        return trim($name, '\\');
    }

    private function getNextIdentifier(array $tokens, int $index): ?string
    {
        for ($i = $index + 1; $i < count($tokens); $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                return $tokens[$i][1];
            }

            if ($tokens[$i] === '{') {
                break;
            }
        }

        return null;
    }

    private function hasMatchingImport(string $name, array $imports): bool
    {
        $short = basename(str_replace('\\', '/', $name));

        foreach ($imports as $import) {
            if (strcasecmp(basename(str_replace('\\', '/', $import)), $short) === 0) {
                return true;
            }
        }

        return false;
    }

    private function isBuiltinClass(string $name): bool
    {
        return in_array(strtolower(ltrim($name, '\\')), [
            'stdclass',
            'exception',
            'throwable',
            'runtimeexception',
            'invalidargumentexception',
            'datetime',
            'datetimeimmutable',
            'closure',
            'pdo',
            'carbon',
            'collection',
        ], true);
    }

    private function checkRedundantCollectionFetch($tokens, $relativePath)
    {
        $text = $this->tokensToText($tokens);

        $patterns = [
            '/->get\(\)\s*->\s*first\s*\(/i' => 'Use `first()` instead of `get()->first()`.',
            '/->get\(\)\s*->\s*count\s*\(/i' => 'Use `count()` instead of `get()->count()`.',
            '/->get\(\)\s*->\s*exists\s*\(/i' => 'Use `exists()` instead of `get()->exists()`.',
            '/->get\(\)\s*->\s*pluck\s*\(/i' => 'Consider `pluck()` directly on the query.',
            '/::all\(\)\s*->\s*where\s*\(/i' => 'Filter in SQL with `where()` before fetching the collection.',
        ];

        foreach ($patterns as $pattern => $message) {
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
                $line = substr_count(substr($text, 0, $match[0][1]), "\n") + 1;
                $this->addError($relativePath, $line, "Redundant Collection Fetching Error: {$message}");
            }
        }
    }

    private function checkDangerousQueries($tokens, $relativePath)
    {
        $text = $this->tokensToText($tokens);
        if (preg_match('/(?:Model|[A-Za-z_\\\\]+)::(?:query|where\([^;]*\))?\s*->?\s*(?:delete|update)\s*\(/i', $text)) {
            $this->addError($relativePath, 0, 'Dangerous Queries: Review bulk update/delete operations and verify they contain the intended WHERE condition.');
        }
        if (preg_match('/DB::table\([^;]*\)->\s*(?:delete|update)\s*\(/i', $text)) {
            $this->addError($relativePath, 0, 'Dangerous Queries: Review DB::table()->delete/update() for accidental bulk modification.');
        }
    }

    private function checkEnvUsage($tokens, $relativePath)
    {
        if (strpos($relativePath, 'config' . DIRECTORY_SEPARATOR) === 0) {
            return;
        }
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_STRING && strtolower($token[1]) === 'env') {
                $this->addError($relativePath, $token[2], 'Env() Usage: `env()` is used outside config/. Prefer `config()` for application runtime values.');
            }
        }
    }

    private function checkExternalApiSafety($tokens, $relativePath)
    {
        $text = strtolower($this->tokensToText($tokens));
        $apiMethods = ['http::get', 'http::post', 'http::put', 'http::patch', 'http::delete', 'curl_exec', 'curl_init', 'client->request', 'client->get', 'client->post'];
        foreach ($apiMethods as $method) {
            if (strpos($text, $method) !== false) {
                if (strpos($text, 'timeout(') === false && strpos($text, 'connect_timeout') === false) {
                    $this->addError($relativePath, 0, "External API Safety: External API call detected without an obvious timeout: `{$method}`.");
                }
                break;
            }
        }
    }

    private function checkSuspiciousModelFields($tokens, $relativePath)
    {
        $modelVariables = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }

            $next = $this->getNextMeaningfulToken($tokens, $index);

            if ($next !== '=' && $next !== '->') {
                continue;
            }

            $snippet = $this->tokensAround($tokens, $index, 20);

            if (preg_match('/(?:::find|::first|::findOrFail|::query|::where).*?;/is', $snippet)) {
                $modelVariables[$token[1]] = true;
            }
        }

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }

            if (!isset($modelVariables[$token[1]])) {
                continue;
            }

            $operator = $this->getNextMeaningfulToken($tokens, $index);

            if ($operator !== '->') {
                continue;
            }

            $field = $this->getNextMeaningfulToken($tokens, $index + 1);

            if (is_array($field) && $field[0] === T_STRING) {
                $name = strtolower($field[1]);

                if (preg_match('/^(createdat|updatedat|userid|schoolid|deviceid|ticketid|studentid)$/', $name)) {
                    $this->addError(
                        $relativePath,
                        $field[2],
                        "Suspicious model field/relation usage: Verify model field/relation `{$field[1]}` exists on the model assigned to `{$token[1]}`."
                    );
                }
            }
        }
    }

    private function tokensToText($tokens)
    {
        $text = '';
        foreach ($tokens as $token) {
            $text .= is_array($token) ? $token[1] : $token;
        }
        return $text;
    }

    private function tokensAround($tokens, $index, $distance)
    {
        $start = max(0, $index - $distance);
        $end = min(count($tokens) - 1, $index + $distance);
        $text = '';
        for ($i = $start; $i <= $end; $i++) {
            $text .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        }
        return $text;
    }

    private function checkDbTransaction($tokens, $relativePath)
    {
        $hasBegin = false;
        $hasCommitOrRollback = false;
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] === 'beginTransaction') {
                $hasBegin = true;
            }
            if (is_array($token) && $token[0] === T_STRING && in_array($token[1], ['commit', 'rollBack'])) {
                $hasCommitOrRollback = true;
            }
        }
        if ($hasBegin && !$hasCommitOrRollback) {
            $this->addError($relativePath, 0, "DB Transaction Commit/Rollback Error: `DB::beginTransaction()` found, but missing `DB::commit()` or `DB::rollBack()` in the file.");
        }
    }

    private function checkLargeQuery($tokens, $relativePath)
    {
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] === 'all') {
                $prev = $this->getPreviousMeaningfulToken($tokens, $index);
                if (is_array($prev) && $prev[0] === T_PAAMAYIM_NEKUDOTAYIM) {
                    $this->addError($relativePath, $token[2], "Large Query Detection: `::all()` is used. If the table is large, this will cause memory exhaustion. Use `::paginate()` or `::chunk()`.");
                }
            }
        }
    }

    private function getPreviousMeaningfulToken($tokens, $index)
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]))
                continue;
            return $tokens[$i] ?? null;
        }
        return null;
    }

    private function getNextMeaningfulToken($tokens, $index)
    {
        for ($i = $index + 1; $i < count($tokens); $i++) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]))
                continue;
            return $tokens[$i] ?? null;
        }
        return null;
    }

    private function addError($file, $line, $message)
    {
        if (!isset($this->errors[$file]))
            $this->errors[$file] = [];

        $severity = 'WARNING';
        if (strpos(strtolower($message), 'syntax error') !== false || strpos(strtolower($message), 'sql injection') !== false || strpos(strtolower($message), 'sensitive laravel logging') !== false || strpos(strtolower($message), 'missing class/import detection') !== false || strpos(strtolower($message), 'suspicious model field/relation usage') !== false || strpos(strtolower($message), 'debug code error') !== false || strpos(strtolower($message), 'undefined variable error') !== false || strpos(strtolower($message), 'redundant collection fetching') !== false || strpos(strtolower($message), 'dangerous queries') !== false || strpos(strtolower($message), 'env() usage') !== false || strpos(strtolower($message), 'external api safety') !== false || strpos(strtolower($message), 'environment validation') !== false || strpos(strtolower($message), 'composer validation') !== false) {
            $severity = 'CRITICAL';
        } elseif (strpos(strtolower($message), 'undefined array keys warning') !== false) {
            $severity = 'INFO';
        }

        $this->severityCount[$severity]++;

        $lineStr = $line > 0 ? "[Line $line] " : "";
        $this->errors[$file][] = ['message' => "$lineStr$message", 'severity' => $severity];
    }

    private function generateReport()
    {
        echo "\n--------------------------------------------------------\n";
        echo "SCAN REPORT\n";
        echo "--------------------------------------------------------\n";
        if (empty($this->errors)) {
            echo "\033[32mNo issues found! Your Laravel code looks solid.\033[0m\n";
            return;
        }
        $totalErrors = 0;
        foreach ($this->errors as $file => $fileErrors) {
            echo "\nFile: \033[1m$file\033[0m\n";
            foreach ($fileErrors as $errorData) {
                $error = $errorData['message'];
                $severity = $errorData['severity'];
                $color = "\033[33m"; // Yellow for WARNING
                if ($severity === 'CRITICAL')
                    $color = "\033[31m"; // Red
                elseif ($severity === 'INFO')
                    $color = "\033[36m"; // Cyan

                echo "  - {$color}[{$severity}] {$error}\033[0m\n";
                $totalErrors++;
            }
        }

        echo "\n--------------------------------------------------------\n";
        echo "TOTAL ISSUES: {$totalErrors}\n";
        echo "\033[31mCRITICAL: {$this->severityCount['CRITICAL']}\033[0m\n";
        echo "\033[33mWARNING : {$this->severityCount['WARNING']}\033[0m\n";
        echo "\033[36mINFO    : {$this->severityCount['INFO']}\033[0m\n";
        echo "--------------------------------------------------------\n";

        $this->generateHtmlReport($totalErrors);
    }

    private function generateHtmlReport($totalErrors)
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<title>Laravel Build Checker Report</title>';
        $html .= '<style>';
        $html .= 'body{font-family:Arial,sans-serif;background:#0b1020;color:#f8fafc;padding:20px}';
        $html .= 'h1{color:#60a5fa}.summary{padding:15px;background:#1e293b;border-radius:8px}';
        $html .= '.file{background:#1e293b;padding:10px;margin-top:20px;border-radius:6px;font-weight:bold;}';
        $html .= '.item{padding:10px;margin:5px 0;border-left:4px solid #64748b;background:#334155}';
        $html .= '.critical{border-left-color:#ef4444;} strong.critical{color:#ef4444;} .warning{border-left-color:#f59e0b;} strong.warning{color:#f59e0b;} .info{border-left-color:#38bdf8;} strong.info{color:#38bdf8;}';
        $html .= '</style></head><body>';
        $html .= '<h1>Laravel Build Checker Report</h1>';
        $html .= '<div class="summary">';
        $html .= '<strong style="color:#60a5fa;">Total Issues:</strong> ' . $totalErrors . '<br>';
        $html .= '<strong style="color:#ef4444;">Critical:</strong> ' . $this->severityCount['CRITICAL'] . '<br>';
        $html .= '<strong style="color:#f59e0b;">Warning:</strong> ' . $this->severityCount['WARNING'] . '<br>';
        $html .= '<strong style="color:#3697c6;">Info:</strong> ' . $this->severityCount['INFO'];
        $html .= '</div>';

        foreach ($this->errors as $file => $fileErrors) {
            $html .= "<div class='file'>" . htmlspecialchars($file) . "</div>";
            foreach ($fileErrors as $errorData) {
                $error = $errorData['message'];
                $severity = $errorData['severity'];

                $severityClass = strtolower($severity);
                $html .= "<div class='item {$severityClass}'><strong class='{$severityClass}'>[{$severity}]</strong> <span style='color: white;'>" . htmlspecialchars($error) . "</span></div>";
            }
        }
        $html .= "</body></html>";
        $reportPath = $this->projectPath . '/LaravelBuildChecker_laravel/report.html';
        file_put_contents($reportPath, $html);
        echo "HTML report generated at: $reportPath\n";
    }
}

if (php_sapi_name() !== 'cli') {
    die("This tool must be run from the command line.");
}
$projectPath = isset($argv[1]) ? $argv[1] : dirname(__DIR__);
$checker = new LaravelBuildChecker($projectPath);
$checker->scan();
