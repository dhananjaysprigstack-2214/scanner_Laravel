import express from 'express';
import multer from 'multer';
import cors from 'cors';
import path from 'path';
import fs from 'fs';
import os from 'os';
import { exec } from 'child_process';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const port = process.env.PORT || 3000;

app.use(cors());
app.use(express.static(path.join(__dirname, 'dist')));

const upload = multer({ dest: os.tmpdir() });

app.post('/api/scan-folder', upload.array('projectFiles'), (req, res) => {
    if (!req.files || req.files.length === 0) {
        return res.status(400).json({ error: 'No files uploaded' });
    }

    const extractDir = path.join(os.tmpdir(), `laravel-scan-${Date.now()}`);
    let paths = req.body.paths || [];
    
    if (!Array.isArray(paths)) {
        paths = [paths]; // If only one file, it might come as a string
    }

    try {
        fs.mkdirSync(extractDir, { recursive: true });
        
        // Reconstruct the folder structure
        req.files.forEach((file, index) => {
            const relPath = paths[index];
            if (relPath) {
                const targetPath = path.join(extractDir, relPath);
                const targetDir = path.dirname(targetPath);
                fs.mkdirSync(targetDir, { recursive: true });
                fs.copyFileSync(file.path, targetPath);
            }
            // Delete the original temp file created by multer
            fs.unlinkSync(file.path);
        });
        
        // The root folder name is the first part of the relative path
        const rootFolderName = paths.length > 0 ? paths[0].split('/')[0] : 'project';
        const projectDir = path.join(extractDir, rootFolderName);

        const scriptPath = path.join(__dirname, 'scan.php');
        
        // Execute PHP scanner
        exec(`php "${scriptPath}" "${projectDir}"`, { maxBuffer: 1024 * 1024 * 50 }, (error, stdout, stderr) => {
            let cleanOutput = stdout ? stdout.replace(/\x1B\[[0-9;]*[mK]/g, "") : "";
            let errorMsg = stderr || (error ? error.message : null);
            let reportContent = null;
            
            const reportPath = path.join(projectDir, 'LaravelBuildChecker_laravel', 'report.html');

            if (fs.existsSync(reportPath)) {
                reportContent = fs.readFileSync(reportPath, 'utf8');
            }

            if (error && error.message.includes('stdout maxBuffer length exceeded')) {
                cleanOutput = "The output is too large to display in this terminal window.\n\n" +
                              "However, a full HTML report has been generated!";
                errorMsg = null;
            }
            
            // Cleanup temp directory
            try {
                fs.rmSync(extractDir, { recursive: true, force: true });
            } catch (cleanupErr) {
                console.error("Cleanup error:", cleanupErr);
            }

            res.json({
                folder: rootFolderName,
                output: cleanOutput,
                error: errorMsg,
                reportContent: reportContent
            });
        });
    } catch (err) {
        // Cleanup on failure
        try {
            if (fs.existsSync(extractDir)) fs.rmSync(extractDir, { recursive: true, force: true });
            if (req.files) req.files.forEach(f => { if (fs.existsSync(f.path)) fs.unlinkSync(f.path); });
        } catch (cleanupErr) {
            console.error("Cleanup error:", cleanupErr);
        }
        
        res.status(500).json({ error: `Server error: ${err.message}` });
    }
});

app.listen(port, () => {
    console.log(`Server listening on port ${port}`);
});
