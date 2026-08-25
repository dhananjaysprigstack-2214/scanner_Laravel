import { app as t, BrowserWindow as u, ipcMain as h, dialog as _, shell as v } from "electron";
import { fileURLToPath as R } from "node:url";
import e from "node:path";
import { exec as T } from "node:child_process";
const f = e.dirname(R(import.meta.url));
process.env.APP_ROOT = e.join(f, "..");
const a = process.env.VITE_DEV_SERVER_URL, L = e.join(process.env.APP_ROOT, "dist-electron"), m = e.join(process.env.APP_ROOT, "dist");
process.env.VITE_PUBLIC = a ? e.join(process.env.APP_ROOT, "public") : m;
let n;
function w() {
  n = new u({
    width: 900,
    height: 700,
    title: "Laravel Build Checker",
    icon: e.join(process.env.VITE_PUBLIC, "electron-vite.svg"),
    webPreferences: {
      preload: e.join(f, "preload.mjs"),
      contextIsolation: !0,
      nodeIntegration: !1
    }
  }), n.webContents.on("did-finish-load", () => {
    n == null || n.webContents.send("main-process-message", (/* @__PURE__ */ new Date()).toLocaleString());
  }), a ? n.loadURL(a) : n.loadFile(e.join(m, "index.html"));
}
t.on("window-all-closed", () => {
  process.platform !== "darwin" && (t.quit(), n = null);
});
t.on("activate", () => {
  u.getAllWindows().length === 0 && w();
});
t.whenReady().then(() => {
  w(), h.handle("scan-folder", async () => {
    if (!n) return { error: "No window found" };
    const r = await _.showOpenDialog(n, {
      properties: ["openDirectory"]
    });
    if (r.canceled || r.filePaths.length === 0)
      return { canceled: !0 };
    const o = r.filePaths[0], i = t.isPackaged ? e.join(process.resourcesPath, "scan.php") : e.join(process.env.APP_ROOT, "scan.php");
    return new Promise((P) => {
      T(`php "${i}" "${o}"`, { maxBuffer: 1024 * 1024 * 50 }, (s, l, g) => {
        let c = l ? l.replace(/\x1B\[[0-9;]*[mK]/g, "") : "", p = g || (s ? s.message : null), d = e.join(o, "LaravelBuildChecker_laravel", "report.html");
        s && s.message.includes("stdout maxBuffer length exceeded") && (c = `The output is too large to display in this terminal window.

However, a full HTML report has been generated! You can view it here:
file:///` + d.replace(/\\/g, "/"), p = null), P({
          folder: o,
          output: c,
          error: p,
          reportPath: d
        });
      });
    });
  }), h.handle("open-report", async (r, o) => {
    try {
      return await v.openPath(o), { success: !0 };
    } catch (i) {
      return { error: i.message };
    }
  });
});
export {
  L as MAIN_DIST,
  m as RENDERER_DIST,
  a as VITE_DEV_SERVER_URL
};
