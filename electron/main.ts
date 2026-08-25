import { app, BrowserWindow, ipcMain, dialog, shell } from 'electron'

import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { exec } from 'node:child_process'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

// The built directory structure
process.env.APP_ROOT = path.join(__dirname, '..')

export const VITE_DEV_SERVER_URL = process.env['VITE_DEV_SERVER_URL']
export const MAIN_DIST = path.join(process.env.APP_ROOT, 'dist-electron')
export const RENDERER_DIST = path.join(process.env.APP_ROOT, 'dist')

process.env.VITE_PUBLIC = VITE_DEV_SERVER_URL ? path.join(process.env.APP_ROOT, 'public') : RENDERER_DIST

let win: BrowserWindow | null

function createWindow() {
  win = new BrowserWindow({
    width: 900,
    height: 700,
    title: 'Laravel Build Checker',
    icon: path.join(process.env.VITE_PUBLIC, 'electron-vite.svg'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.mjs'),
      contextIsolation: true,
      nodeIntegration: false
    },
  })

  win.webContents.on('did-finish-load', () => {
    win?.webContents.send('main-process-message', (new Date).toLocaleString())
  })

  if (VITE_DEV_SERVER_URL) {
    win.loadURL(VITE_DEV_SERVER_URL)
  } else {
    win.loadFile(path.join(RENDERER_DIST, 'index.html'))
  }
}

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit()
    win = null
  }
})

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow()
  }
})

app.whenReady().then(() => {
  createWindow()

  // Handle IPC calls
  ipcMain.handle('scan-folder', async () => {
    if (!win) return { error: 'No window found' };
    
    // Open native folder dialog
    const result = await dialog.showOpenDialog(win, {
      properties: ['openDirectory']
    });

    if (result.canceled || result.filePaths.length === 0) {
      return { canceled: true };
    }

    const folderPath = result.filePaths[0];
    return runScanner(folderPath);
  })

  ipcMain.handle('scan-specific-folder', async (_event, folderPath) => {
    return runScanner(folderPath);
  })

  function runScanner(folderPath: string) {
    const scriptPath = app.isPackaged 
      ? path.join(process.resourcesPath, 'scan.php') 
      : path.join(process.env.APP_ROOT, 'scan.php');

    return new Promise((resolve) => {
      // Execute the PHP scanner script with a 50MB maxBuffer
      exec(`php "${scriptPath}" "${folderPath}"`, { maxBuffer: 1024 * 1024 * 50 }, (error, stdout, stderr) => {
        let cleanOutput = stdout ? stdout.replace(/\x1B\[[0-9;]*[mK]/g, "") : "";
        let errorMsg = stderr || (error ? error.message : null);
        let reportPath = path.join(folderPath, 'LaravelBuildChecker_laravel', 'report.html');

        if (error && error.message.includes('stdout maxBuffer length exceeded')) {
          cleanOutput = "The output is too large to display in this terminal window.\n\n" +
                        "However, a full HTML report has been generated! You can view it here:\n" + 
                        "file:///" + reportPath.replace(/\\/g, '/');
          errorMsg = null;
        }

        resolve({
          folder: folderPath,
          output: cleanOutput,
          error: errorMsg,
          reportPath: reportPath
        });
      });
    });
  }

  ipcMain.handle('open-report', async (_event, reportPath) => {
    try {
      await shell.openPath(reportPath);
      return { success: true };
    } catch (err: any) {
      return { error: err.message };
    }
  })
})
