import { app, BrowserWindow, ipcMain, dialog, shell } from "electron";
import { fileURLToPath } from "node:url";
import path from "node:path";
import { exec } from "node:child_process";
const __dirname$1 = path.dirname(fileURLToPath(import.meta.url));
process.env.APP_ROOT = path.join(__dirname$1, "..");
const VITE_DEV_SERVER_URL = process.env["VITE_DEV_SERVER_URL"];
const MAIN_DIST = path.join(process.env.APP_ROOT, "dist-electron");
const RENDERER_DIST = path.join(process.env.APP_ROOT, "dist");
process.env.VITE_PUBLIC = VITE_DEV_SERVER_URL ? path.join(process.env.APP_ROOT, "public") : RENDERER_DIST;
let win;
function createWindow() {
  win = new BrowserWindow({
    width: 900,
    height: 700,
    title: "Laravel Build Checker",
    icon: path.join(process.env.VITE_PUBLIC, "electron-vite.svg"),
    webPreferences: {
      preload: path.join(__dirname$1, "preload.mjs"),
      contextIsolation: true,
      nodeIntegration: false
    }
  });
  win.webContents.on("did-finish-load", () => {
    win == null ? void 0 : win.webContents.send("main-process-message", (/* @__PURE__ */ new Date()).toLocaleString());
  });
  if (VITE_DEV_SERVER_URL) {
    win.loadURL(VITE_DEV_SERVER_URL);
  } else {
    win.loadFile(path.join(RENDERER_DIST, "index.html"));
  }
}
app.on("window-all-closed", () => {
  if (process.platform !== "darwin") {
    app.quit();
    win = null;
  }
});
app.on("activate", () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  }
});
app.whenReady().then(() => {
  createWindow();
  ipcMain.handle("scan-folder", async () => {
    if (!win) return { error: "No window found" };
    const result = await dialog.showOpenDialog(win, {
      properties: ["openDirectory"]
    });
    if (result.canceled || result.filePaths.length === 0) {
      return { canceled: true };
    }
    const folderPath = result.filePaths[0];
    return runScanner(folderPath);
  });
  ipcMain.handle("scan-specific-folder", async (_event, folderPath) => {
    return runScanner(folderPath);
  });
  function runScanner(folderPath) {
    const scriptPath = app.isPackaged ? path.join(process.resourcesPath, "scan.php") : path.join(process.env.APP_ROOT, "scan.php");
    return new Promise((resolve) => {
      exec(`php "${scriptPath}" "${folderPath}"`, { maxBuffer: 1024 * 1024 * 50 }, (error, stdout, stderr) => {
        let cleanOutput = stdout ? stdout.replace(/\x1B\[[0-9;]*[mK]/g, "") : "";
        let errorMsg = stderr || (error ? error.message : null);
        let reportPath = path.join(folderPath, "LaravelBuildChecker_laravel", "report.html");
        if (error && error.message.includes("stdout maxBuffer length exceeded")) {
          cleanOutput = "The output is too large to display in this terminal window.\n\nHowever, a full HTML report has been generated! You can view it here:\nfile:///" + reportPath.replace(/\\/g, "/");
          errorMsg = null;
        }
        resolve({
          folder: folderPath,
          output: cleanOutput,
          error: errorMsg,
          reportPath
        });
      });
    });
  }
  ipcMain.handle("open-report", async (_event, reportPath) => {
    try {
      await shell.openPath(reportPath);
      return { success: true };
    } catch (err) {
      return { error: err.message };
    }
  });
});
export {
  MAIN_DIST,
  RENDERER_DIST,
  VITE_DEV_SERVER_URL
};
