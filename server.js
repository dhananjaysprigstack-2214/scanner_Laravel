import express from 'express';
import multer from 'multer';
import cors from 'cors';
import AdmZip from 'adm-zip';
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

app.post('/api/scan', upload.single('projectZip'), (req, res) => {
    if (!req.file) {
        return res.status(400).json({ error: 'No zip file uploaded' });
    }

    const zipPath = req.file.path;
    const extractDir = path.join(os.tmpdir(), `laravel-scan-${Date.now()}`);

    try {
        fs.mkdirSync(extractDir, { recursive: true });
        
        // Extract zip
        const zip = new AdmZip(zipPath);
        zip.extractAllTo(extractDir, true);
        
        const scriptPath = path.join(__dirname, 'scan.php');
        
        // Execute PHP scanner
        exec(`php "${scriptPath}" "${extractDir}"`, { maxBuffer: 1024 * 1024 * 50 }, (error, stdout, stderr) => {
            let cleanOutput = stdout ? stdout.replace(/\x1B\[[0-9;]*[mK]/g, "") : "";
            let errorMsg = stderr || (error ? error.message : null);
            let reportContent = null;
            
            const reportPath = path.join(extractDir, 'LaravelBuildChecker_laravel', 'report.html');

            if (fs.existsSync(reportPath)) {
                reportContent = fs.readFileSync(reportPath, 'utf8');
            }

            if (error && error.message.includes('stdout maxBuffer length exceeded')) {
                cleanOutput = "The output is too large to display in this terminal window.\n\n" +
                              "However, a full HTML report has been generated!";
                errorMsg = null;
            }
            
            // Cleanup temp files
            try {
                fs.rmSync(extractDir, { recursive: true, force: true });
                fs.unlinkSync(zipPath);
            } catch (cleanupErr) {
                console.error("Cleanup error:", cleanupErr);
            }

            res.json({
                folder: req.file.originalname,
                output: cleanOutput,
                error: errorMsg,
                reportContent: reportContent
            });
        });
    } catch (err) {
        // Cleanup on failure
        try {
            if (fs.existsSync(extractDir)) fs.rmSync(extractDir, { recursive: true, force: true });
            if (fs.existsSync(zipPath)) fs.unlinkSync(zipPath);
        } catch (cleanupErr) {
            console.error("Cleanup error:", cleanupErr);
        }
        
        res.status(500).json({ error: `Server error: ${err.message}` });
    }
});

// Removed fallback route as it is not needed for a single page app without router.

app.listen(port, () => {
    console.log(`Server listening on port ${port}`);
});
