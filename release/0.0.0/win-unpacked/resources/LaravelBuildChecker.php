<?php

// Syntax / parse / if-else closing // major
// Try-catch validation // major
// Incomplete/commented try-catch // major
// N+1 queries // medium
// DB::table() usage - not required
// Undefined variables // major
// Null / chained object access // major
// Laravel DB relationship return // major
// Undefined array keys // major
// SQL injection / unsafe raw SQL // major
// Debug code(dd,return,echo,die,exit added by mistake) // major
// Sensitive Laravel logging(laravel log added by mistake) // major
// Undefined/unresolved methods/functions // major
// Missing class/import detection // major
// Redundant collection fetching // major
// Suspicious model field/relation usage // major
// DB transaction commit/rollback // major
// Sensitive variable/input usage // major
// Large query detection // medium
// Composer/environment validation // major

/**
 * Laravel Build Checker
 *
 * Pre-release static checks for common Laravel/PHP production issues.
 *
 * Note:
 * - Token-based checks are intentionally conservative.
 * - Model/database-field validation is best-effort unless project metadata is available.
 * - For deep type/method/package analysis, PHPStan/Larastan should also be used.
 */
class LaravelBuildChecker
{
    private string $projectPath;

    private array $foldersToScan = [
        'app',
        'routes',
        'config',
        'database',
        'resources',
    ];

    private array $errors = [];

    private array $severityCount = [
        'CRITICAL' => 0,
        'WARNING' => 0,
        'INFO' => 0,
    ];

    private array $frameworkMethods = [
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

    private array $knownPhpFunctions = [
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

    private array $sensitiveNames = [
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

    public function __construct(string $path)
    {
        $this->projectPath = rtrim($path, '/\\');
    }

    public function scan(): void
    {
        echo "Scanning project at: {$this->projectPath}\n";
        echo "--------------------------------------------------------\n";

        $files = $this->getPhpFiles();

        echo "Found " . count($files) . " PHP files to analyze.\n";

        foreach ($files as $file) {
            $this->analyzeFile($file);
        }

        $this->checkEnvironment();
        $this->checkComposer();
        $this->generateReport();
    }

    private function getPhpFiles(): array
    {
        $phpFiles = [];

        foreach ($this->foldersToScan as $folder) {
            $dir = $this->projectPath . DIRECTORY_SEPARATOR . $folder;

            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $phpFiles[] = $file->getPathname();
                }
            }
        }

        return $phpFiles;
    }

    private function analyzeFile(string $file): void
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return;
        }

        $tokens = token_get_all($content);
        $relativePath = str_replace($this->projectPath . DIRECTORY_SEPARATOR, '', $file);

        // Existing 7 checks.
        $this->checkSyntax($file, $relativePath);
        $this->checkTryCatch($tokens, $relativePath);
        // $this->checkNPlusOne($tokens, $relativePath); // MEDIUM
        // $this->checkQueryTableUsage($tokens, $relativePath); // NOT REQUIRED
        $this->checkVariableDefine($tokens, $relativePath);
        $this->checkNullError($tokens, $relativePath);
        $this->checkDbRelation($tokens, $relativePath);

        // Newly requested checks.
        $this->checkUndefinedArrayKeys($tokens, $relativePath);
        $this->checkSqlInjection($tokens, $relativePath);
        $this->checkDebugCode($tokens, $relativePath);
        $this->checkSensitiveLogging($tokens, $relativePath);
        $this->checkUndefinedCalls($tokens, $relativePath); // MAJOR
        $this->checkImportsAndClasses($tokens, $relativePath); // MAJOR
        $this->checkRedundantCollectionFetch($tokens, $relativePath);
        $this->checkSuspiciousModelFields($tokens, $relativePath);
        $this->checkDangerousQueries($tokens, $relativePath); // (Preserved)
        $this->checkEnvUsage($tokens, $relativePath); // (Preserved)
        $this->checkExternalApiSafety($tokens, $relativePath); // (Preserved)
        $this->checkTransactionHandling($tokens, $relativePath); // MAJOR
        $this->checkSensitiveInputUsage($tokens, $relativePath); // MAJOR
        // $this->checkLargeQueries($tokens, $relativePath); // MEDIUM
    }

    private function checkSyntax(string $file, string $relativePath): void
    {
        $output = [];
        $returnVar = 0;

        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            $message = implode(' ', $output);
            $this->addError(
                $relativePath,
                0,
                'CRITICAL',
                'Syntax Error / If-Else Close Error: ' . trim(str_replace('Errors parsing', '', $message))
            );
        }
    }

    private function checkTryCatch(array $tokens, string $relativePath): void
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CATCH) {
                continue;
            }

            $foundContent = false;
            $openBraces = 0;
            $i = $index + 1;

            while (isset($tokens[$i])) {
                if ($tokens[$i] === '{') {
                    $openBraces++;
                } elseif ($tokens[$i] === '}') {
                    $openBraces--;

                    if ($openBraces === 0) {
                        break;
                    }
                }

                if (
                    $openBraces > 0 &&
                    is_array($tokens[$i]) &&
                    !in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                ) {
                    $foundContent = true;
                }

                $i++;
            }

            if (!$foundContent && $openBraces === 0) {
                $this->addError(
                    $relativePath,
                    $token[2],
                    'WARNING',
                    'Try-Catch Error: Empty catch block found.'
                );
            }
        }
    }

    private function checkNPlusOne(array $tokens, string $relativePath): void
    {
        $loopDepth = 0;
        $braceStack = [];

        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_FOR, T_FOREACH, T_WHILE], true)) {
                    $loopDepth++;
                }

                if ($loopDepth > 0 && $token[0] === T_STRING) {
                    $method = strtolower($token[1]);

                    $queryMethods = [
                        'find',
                        'findorfail',
                        'first',
                        'firstorfail',
                        'get',
                        'all',
                        'paginate',
                        'simplepaginate',
                        'cursor',
                        'count',
                        'exists',
                        'sum',
                        'avg',
                        'min',
                        'max',
                        'pluck',
                        'value',
                    ];

                    if (
                        in_array($method, $queryMethods, true) &&
                        isset($tokens[$index - 1]) &&
                        is_array($tokens[$index - 1]) &&
                        in_array($tokens[$index - 1][0], [T_OBJECT_OPERATOR, T_PAAMAYIM_NEKUDOTAYIM], true)
                    ) {
                        $this->addError(
                            $relativePath,
                            $token[2],
                            'WARNING',
                            "Possible N+1 query: `{$token[1]}()` executes inside a loop."
                        );
                    }
                }
            } else {
                if ($token === '{') {
                    $braceStack[] = $loopDepth > 0;
                } elseif ($token === '}' && $braceStack) {
                    $wasLoopBrace = array_pop($braceStack);

                    if ($wasLoopBrace && $loopDepth > 0) {
                        $loopDepth--;
                    }
                }
            }
        }
    }

    private function checkQueryTableUsage(array $tokens, string $relativePath): void
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'table') {
                continue;
            }

            if (
                isset($tokens[$index - 1], $tokens[$index - 2]) &&
                is_array($tokens[$index - 1]) &&
                $tokens[$index - 1][0] === T_PAAMAYIM_NEKUDOTAYIM &&
                is_array($tokens[$index - 2]) &&
                strtolower($tokens[$index - 2][1]) === 'db'
            ) {
                $this->addError(
                    $relativePath,
                    $token[2],
                    'INFO',
                    'DB::table() detected. Prefer Eloquent when an appropriate model exists.'
                );
            }
        }
    }

    private function checkVariableDefine(array $tokens, string $relativePath): void
    {
        $globalVariables = [
            '$this',
            '$_GET',
            '$_POST',
            '$_REQUEST',
            '$_SERVER',
            '$_SESSION',
            '$_COOKIE',
            '$_FILES',
        ];

        $scopeStack = [];
        $defined = $globalVariables;

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_FUNCTION) {
                $scopeStack[] = $defined;
                $defined = $globalVariables;
                continue;
            }

            if ($token[0] === T_VARIABLE) {
                $var = $token[1];

                if ($this->isVariableAssignment($tokens, $index)) {
                    $defined[] = $var;
                    continue;
                }

                if ($this->isFunctionParameter($tokens, $index)) {
                    $defined[] = $var;
                    continue;
                }

                if (
                    !in_array($var, $defined, true) &&
                    $this->isInsideObjectCall($tokens, $index)
                ) {
                    $this->addError(
                        $relativePath,
                        $token[2],
                        'WARNING',
                        "Variable `{$var}` may be used before definition."
                    );
                }
            }
        }
    }

    private function checkNullError(array $tokens, string $relativePath): void
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_OBJECT_OPERATOR) {
                continue;
            }

            $previous = $this->getPreviousMeaningfulToken($tokens, $index);

            if (
                is_array($previous) &&
                $previous[0] === T_OBJECT_OPERATOR &&
                $this->isPotentialNullChain($tokens, $index)
            ) {
                $this->addError(
                    $relativePath,
                    $token[2],
                    'WARNING',
                    'Possible null error: chained `->` access may call a method/property on null. Consider null checks or `?->`.'
                );
            }
        }
    }

    private function checkDbRelation(array $tokens, string $relativePath): void
    {
        $relations = [
            'hasOne',
            'hasMany',
            'belongsTo',
            'belongsToMany',
            'hasOneThrough',
            'hasManyThrough',
            'morphTo',
            'morphOne',
            'morphMany',
            'morphToMany',
            'morphedByMany',
        ];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[1], $relations, true)) {
                continue;
            }

            $hasReturn = false;

            for ($i = $index - 1; $i >= 0; $i--) {
                if (is_array($tokens[$i]) && $tokens[$i][0] === T_RETURN) {
                    $hasReturn = true;
                    break;
                }

                if ($tokens[$i] === ';' || $tokens[$i] === '{' || $tokens[$i] === '}') {
                    break;
                }
            }

            if (!$hasReturn) {
                $this->addError(
                    $relativePath,
                    $token[2],
                    'WARNING',
                    "Relationship `{$token[1]}` may not be returned from the relationship method."
                );
            }
        }
    }

    private function checkUndefinedArrayKeys(array $tokens, string $relativePath): void
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }

            $next = $this->getNextMeaningfulToken($tokens, $index);

            if ($next === '[') {
                $key = $this->getArrayKey($tokens, $index);

                if ($key !== null) {
                    $this->addError(
                        $relativePath,
                        $token[2],
                        'WARNING',
                        "Possible undefined array key: {$token[1]}['{$key}']. Verify the key exists or use `??`/`isset()`."
                    );
                }
            }
        }
    }

    private function checkSqlInjection(array $tokens, string $relativePath): void
    {
        $rawMethods = [
            'raw',
            'selectraw',
            'whereraw',
            'orwhereraw',
            'havingraw',
            'orderByRaw',
            'groupByRaw',
        ];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $method = strtolower($token[1]);

            if (in_array($method, $rawMethods, true) || in_array($method, ['select', 'statement'], true)) {
                $next = $this->getNextMeaningfulToken($tokens, $index);

                if ($next === '(') {
                    $expression = $this->tokensUntilClosingParen($tokens, $index);

                    if ($this->containsVariableInterpolationOrConcatenation($expression)) {
                        $this->addError(
                            $relativePath,
                            $token[2],
                            'CRITICAL',
                            "Possible SQL Injection: variable interpolation/concatenation detected in `{$token[1]}()`."
                        );
                    } elseif (in_array($method, $rawMethods, true)) {
                        $this->addError(
                            $relativePath,
                            $token[2],
                            'INFO',
                            "Raw SQL method `{$token[1]}()` detected. Verify parameter binding is used."
                        );
                    }
                }
            }
        }
    }

    private function checkDebugCode(array $tokens, string $relativePath): void
    {
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_RETURN) {
                $this->addError(
                    $relativePath,
                    $token[2],
                    'WARNING',
                    'Direct `return` output found. Verify this is intentional (not debug code added by mistake).'
                );
                continue;
            }

            if ($token[0] === T_EXIT) {
                $this->addError(
                    $relativePath,
                    $token[2],
                    'CRITICAL',
                    "Debug/termination code `{$token[1]}` found. Remove before production."
                );
                continue;
            }

            if ($token[0] === T_ECHO) {
                $this->addError(
                    $relativePath,
                    $token[2],
                    'WARNING',
                    'Direct `echo` output found in application code. Verify this is intentional (not debug code added by mistake).'
                );
                continue;
            }

            if ($token[0] === T_STRING) {
                $name = strtolower($token[1]);

                if (in_array($name, ['dd'], true)) {
                    $this->addError(
                        $relativePath,
                        $token[2],
                        'CRITICAL',
                        "Debug/termination code `{$token[1]}()` found. Remove before production."
                    );
                }

                if (in_array($name, ['dump', 'var_dump', 'print_r', 'ray'], true)) {
                    $this->addError(
                        $relativePath,
                        $token[2],
                        'WARNING',
                        "Debug output `{$token[1]}()` found. Remove or verify before production."
                    );
                }
            }
        }
    }

    private function checkSensitiveLogging(array $tokens, string $relativePath): void
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
                            'CRITICAL',
                            "Sensitive logging risk: Laravel Log method `{$token[1]}()` appears to contain `{$sensitive}` data."
                        );
                        break;
                    }
                }
            }
        }
    }

    private function checkUndefinedCalls(array $tokens, string $relativePath): void
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
                    'INFO',
                    "Method/function call `{$name}()` could not be resolved statically. Verify the method/function exists."
                );
            }
        }
    }

    private function checkImportsAndClasses(array $tokens, string $relativePath): void
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
                        !class_exists($name)
                    ) {
                        $this->addError(
                            $relativePath,
                            $token[2],
                            'WARNING',
                            "Possible missing import/class: `{$name}` is instantiated but no matching import was detected."
                        );
                    }
                }
            }
        }
    }

    private function checkRedundantCollectionFetch(array $tokens, string $relativePath): void
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

                $this->addError($relativePath, $line, 'WARNING', "Redundant collection/query usage: {$message}");
            }
        }
    }

    private function checkSuspiciousModelFields(array $tokens, string $relativePath): void
    {
        /*
         * Best-effort check:
         * Detect `$model->field` where the field is obviously suspicious because
         * the same variable was assigned from a model and the field name looks
         * like an unrelated/common typo.
         *
         * Full model-column validation requires reading migrations/schema/models.
         */
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
                        'INFO',
                        "Verify model field/relation `{$field[1]}` exists on the model assigned to `{$token[1]}`."
                    );
                }
            }
        }
    }

    private function checkDangerousQueries(array $tokens, string $relativePath): void
    {
        $text = $this->tokensToText($tokens);

        if (preg_match('/(?:Model|[A-Za-z_\\\\]+)::(?:query|where\([^;]*\))?\s*->?\s*(?:delete|update)\s*\(/i', $text)) {
            // Keep this as a warning because token-level analysis cannot reliably
            // prove that no where clause exists.
            $this->addError(
                $relativePath,
                0,
                'WARNING',
                'Review bulk update/delete operations and verify they contain the intended WHERE condition.'
            );
        }

        if (preg_match('/DB::table\([^;]*\)->\s*(?:delete|update)\s*\(/i', $text)) {
            $this->addError(
                $relativePath,
                0,
                'CRITICAL',
                'Review DB::table()->delete/update() for accidental bulk modification.'
            );
        }
    }

    private function checkEnvUsage(array $tokens, string $relativePath): void
    {
        if (str_starts_with($relativePath, 'config' . DIRECTORY_SEPARATOR)) {
            return;
        }

        foreach ($tokens as $token) {
            if (
                is_array($token) &&
                $token[0] === T_STRING &&
                strtolower($token[1]) === 'env'
            ) {
                $this->addError(
                    $relativePath,
                    $token[2],
                    'WARNING',
                    '`env()` is used outside config/. Prefer `config()` for application runtime values.'
                );
            }
        }
    }

    private function checkExternalApiSafety(array $tokens, string $relativePath): void
    {
        $text = strtolower($this->tokensToText($tokens));

        $apiMethods = [
            'http::get',
            'http::post',
            'http::put',
            'http::patch',
            'http::delete',
            'curl_exec',
            'curl_init',
            'client->request',
            'client->get',
            'client->post',
        ];

        foreach ($apiMethods as $method) {
            if (str_contains($text, $method)) {
                if (!str_contains($text, 'timeout(') && !str_contains($text, 'connect_timeout')) {
                    $this->addError(
                        $relativePath,
                        0,
                        'WARNING',
                        "External API call detected without an obvious timeout: `{$method}`."
                    );
                }

                break;
            }
        }
    }

    private function checkTransactionHandling(array $tokens, string $relativePath): void
    {
        $text = $this->tokensToText($tokens);

        if (!str_contains($text, 'DB::beginTransaction')) {
            return;
        }

        if (!str_contains($text, 'DB::commit')) {
            $this->addError(
                $relativePath,
                0,
                'CRITICAL',
                'DB::beginTransaction() found without an obvious DB::commit().'
            );
        }

        if (!str_contains($text, 'DB::rollBack')) {
            $this->addError(
                $relativePath,
                0,
                'WARNING',
                'DB::beginTransaction() found without an obvious DB::rollBack().'
            );
        }
    }

    private function checkSensitiveInputUsage(array $tokens, string $relativePath): void
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }

            $name = strtolower($token[1]);

            foreach ($this->sensitiveNames as $sensitive) {
                if (str_contains($name, $sensitive)) {
                    $next = $this->getNextMeaningfulToken($tokens, $index);

                    if ($next === '=') {
                        $this->addError(
                            $relativePath,
                            $token[2],
                            'INFO',
                            "Sensitive variable `{$token[1]}` detected. Verify it is not logged, returned, or exposed."
                        );
                    }

                    break;
                }
            }
        }
    }

    private function checkLargeQueries(array $tokens, string $relativePath): void
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            if (strtolower($token[1]) !== 'all') {
                continue;
            }

            $previous = $this->getPreviousMeaningfulToken($tokens, $index);

            if (
                is_array($previous) &&
                in_array($previous[0], [T_PAAMAYIM_NEKUDOTAYIM, T_OBJECT_OPERATOR], true)
            ) {
                $this->addError(
                    $relativePath,
                    $token[2],
                    'WARNING',
                    'Potentially large query: `all()` loads the complete result set into memory.'
                );
            }
        }
    }

    private function checkEnvironment(): void
    {
        $envFile = $this->projectPath . DIRECTORY_SEPARATOR . '.env';

        if (!file_exists($envFile)) {
            $this->addError('.env', 0, 'WARNING', '.env file not found. Verify deployment environment variables are configured.');
            return;
        }

        $content = file_get_contents($envFile);

        if ($content !== false && preg_match('/^\s*APP_DEBUG\s*=\s*true\s*$/mi', $content)) {
            $this->addError('.env', 0, 'CRITICAL', 'APP_DEBUG=true detected. Disable debug mode before production.');
        }

        if ($content !== false && !preg_match('/^\s*APP_KEY\s*=\s*.+$/mi', $content)) {
            $this->addError('.env', 0, 'CRITICAL', 'APP_KEY is missing.');
        }
    }

    private function checkComposer(): void
    {
        $composer = $this->projectPath . DIRECTORY_SEPARATOR . 'composer.json';

        if (!file_exists($composer)) {
            $this->addError('composer.json', 0, 'CRITICAL', 'composer.json not found.');
            return;
        }

        $output = [];
        $status = 0;

        exec(
            'cd ' . escapeshellarg($this->projectPath) . ' && composer validate --no-check-publish 2>&1',
            $output,
            $status
        );

        if ($status !== 0) {
            $this->addError(
                'composer.json',
                0,
                'CRITICAL',
                'composer validate failed: ' . implode(' ', $output)
            );
        }
    }

    private function isVariableAssignment(array $tokens, int $index): bool
    {
        $next = $this->getNextMeaningfulToken($tokens, $index);

        return $next === '=';
    }

    private function isFunctionParameter(array $tokens, int $index): bool
    {
        $previous = $this->getPreviousMeaningfulToken($tokens, $index);

        return $previous === '(' || $previous === ',';
    }

    private function isInsideObjectCall(array $tokens, int $index): bool
    {
        $next = $this->getNextMeaningfulToken($tokens, $index);

        return is_array($next) && $next[0] === T_OBJECT_OPERATOR;
    }

    private function isPotentialNullChain(array $tokens, int $index): bool
    {
        $previous = $this->getPreviousMeaningfulToken($tokens, $index);

        return is_array($previous) && $previous[0] === T_OBJECT_OPERATOR;
    }

    private function getArrayKey(array $tokens, int $index): ?string
    {
        $i = $index + 1;

        while (isset($tokens[$i]) && $tokens[$i] !== '[') {
            $i++;
        }

        $i++;

        while (isset($tokens[$i])) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                return trim($token[1], "'\"");
            }

            if ($token === ']') {
                break;
            }

            $i++;
        }

        return null;
    }

    private function isLikelyMethodCall(array $tokens, int $index): bool
    {
        $previous = $this->getPreviousMeaningfulToken($tokens, $index);

        return is_array($previous) &&
            in_array($previous[0], [T_OBJECT_OPERATOR, T_PAAMAYIM_NEKUDOTAYIM], true);
    }

    private function tokensUntilClosingParen(array $tokens, int $index): string
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

    private function containsVariableInterpolationOrConcatenation(string $expression): bool
    {
        return preg_match('/\$[A-Za-z_][A-Za-z0-9_]*|\.\s*\$|"\s*\.\s*"/', $expression) === 1;
    }

    private function tokensToText(array $tokens): string
    {
        $text = '';

        foreach ($tokens as $token) {
            $text .= is_array($token) ? $token[1] : $token;
        }

        return $text;
    }

    private function tokensAround(array $tokens, int $index, int $distance): string
    {
        $start = max(0, $index - $distance);
        $end = min(count($tokens) - 1, $index + $distance);
        $text = '';

        for ($i = $start; $i <= $end; $i++) {
            $text .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        }

        return $text;
    }

    private function getPreviousMeaningfulToken(array $tokens, int $index)
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (
                is_array($tokens[$i]) &&
                in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }

    private function getNextMeaningfulToken(array $tokens, int $index)
    {
        for ($i = $index + 1; $i < count($tokens); $i++) {
            if (
                is_array($tokens[$i]) &&
                in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }

    private function readQualifiedName(array $tokens, int $start): string
    {
        $name = '';

        for ($i = $start; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name .= $token[1];
                continue;
            }

            if ($token === '\\') {
                $name .= '\\';
                continue;
            }

            if ($name !== '') {
                break;
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
        ], true);
    }

    private function addError(
        string $file,
        int $line,
        string $severity,
        string $message
    ): void {
        $this->errors[$file][] = [
            'line' => $line,
            'severity' => $severity,
            'message' => $message,
        ];

        $this->severityCount[$severity]++;
    }

    private function generateReport(): void
    {
        echo "\n--------------------------------------------------------\n";
        echo "SCAN REPORT\n";
        echo "--------------------------------------------------------\n";

        if (empty($this->errors)) {
            echo "No issues found.\n";
            return;
        }

        $totalErrors = 0;

        foreach ($this->errors as $file => $fileErrors) {
            echo "\nFile: {$file}\n";

            foreach ($fileErrors as $error) {
                echo "  - [{$error['severity']}] [Line {$error['line']}] {$error['message']}\n";
                $totalErrors++;
            }
        }

        echo "\n--------------------------------------------------------\n";
        echo "TOTAL ISSUES: {$totalErrors}\n";
        echo "CRITICAL: {$this->severityCount['CRITICAL']}\n";
        echo "WARNING : {$this->severityCount['WARNING']}\n";
        echo "INFO    : {$this->severityCount['INFO']}\n";
        echo "--------------------------------------------------------\n";

        $this->generateHtmlReport($totalErrors);
    }

    private function generateHtmlReport(int $totalErrors): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<title>Laravel Build Checker Report</title>';
        $html .= '<style>';
        $html .= 'body{font-family:Arial,sans-serif;background:#0b1020;color:#f8fafc;padding:20px}';
        $html .= 'h1{color:#60a5fa}.summary{padding:15px;background:#1e293b;border-radius:8px}';
        $html .= '.file{background:#1e293b;padding:10px;margin-top:20px;border-radius:6px}';
        $html .= '.item{padding:10px;margin:5px 0;border-left:4px solid #64748b;background:#334155}';
        $html .= '.critical{border-left-color:#ef4444}.warning{border-left-color:#f59e0b}.info{border-left-color:#38bdf8}';
        $html .= '</style></head><body>';
        $html .= '<h1>Laravel Build Checker Report</h1>';
        $html .= '<div class="summary">';
        $html .= '<strong>Total Issues:</strong> ' . $totalErrors . '<br>';
        $html .= '<strong>Critical:</strong> ' . $this->severityCount['CRITICAL'] . '<br>';
        $html .= '<strong>Warning:</strong> ' . $this->severityCount['WARNING'] . '<br>';
        $html .= '<strong>Info:</strong> ' . $this->severityCount['INFO'];
        $html .= '</div>';

        foreach ($this->errors as $file => $fileErrors) {
            $html .= '<div class="file"><strong>' . htmlspecialchars($file) . '</strong>';

            foreach ($fileErrors as $error) {
                $class = strtolower($error['severity']);

                $html .= '<div class="item ' . $class . '">';
                $html .= '[' . htmlspecialchars($error['severity']) . '] ';
                $html .= '[Line ' . (int) $error['line'] . '] ';
                $html .= htmlspecialchars($error['message']);
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</body></html>';

        $reportDir = $this->projectPath . DIRECTORY_SEPARATOR . 'LaravelBuildChecker_laravel';

        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0775, true);
        }

        $reportPath = $reportDir . DIRECTORY_SEPARATOR . 'report.html';

        file_put_contents($reportPath, $html);

        echo "HTML report generated at: {$reportPath}\n";
    }
}

if (php_sapi_name() !== 'cli') {
    die("This tool must be run from the command line.\n");
}

$projectPath = dirname(__DIR__);

$checker = new LaravelBuildChecker($projectPath);
$checker->scan();
