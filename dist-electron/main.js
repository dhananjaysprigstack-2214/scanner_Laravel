import { app as i, BrowserWindow as u, ipcMain as h, dialog as _, shell as v } from "electron";
import { fileURLToPath as R } from "node:url";
import n from "node:path";
import { exec as T } from "node:child_process";
const f = n.dirname(R(import.meta.url));
process.env.APP_ROOT = n.join(f, "..");
const a = process.env.VITE_DEV_SERVER_URL, L = n.join(process.env.APP_ROOT, "dist-electron"), m = n.join(process.env.APP_ROOT, "dist");
process.env.VITE_PUBLIC = a ? n.join(process.env.APP_ROOT, "public") : m;
let e;
function w() {
  e = new u({
    width: 900,
    height: 700,
    title: "Laravel Build Checker",
    icon: n.join(process.env.VITE_PUBLIC, "electron-vite.svg"),
    webPreferences: {
      preload: n.join(f, "preload.mjs"),
      contextIsolation: !0,
      nodeIntegration: !1
    }
  }), e.webContents.on("did-finish-load", () => {
    e == null || e.webContents.send("main-process-message", (/* @__PURE__ */ new Date()).toLocaleString());
  }), a ? e.loadURL(a) : e.loadFile(n.join(m, "index.html"));
}
i.on("window-all-closed", () => {
  process.platform !== "darwin" && (i.quit(), e = null);
});
i.on("activate", () => {
  u.getAllWindows().length === 0 && w();
});
i.whenReady().then(() => {
  w(), h.handle("scan-folder", async () => {
    if (!e) return { error: "No window found" };
    const t = await _.showOpenDialog(e, {
      properties: ["openDirectory"]
    });
    if (t.canceled || t.filePaths.length === 0)
      return { canceled: !0 };
    const o = t.filePaths[0], s = n.join(process.env.APP_ROOT, "scan.php");
    return new Promise((P) => {
      T(`php "${s}" "${o}"`, { maxBuffer: 1024 * 1024 * 50 }, (r, l, g) => {
        let c = l ? l.replace(/\x1B\[[0-9;]*[mK]/g, "") : "", p = g || (r ? r.message : null), d = n.join(o, "LaravelBuildChecker_laravel", "report.html");
        r && r.message.includes("stdout maxBuffer length exceeded") && (c = `The output is too large to display in this terminal window.

However, a full HTML report has been generated! You can view it here:
file:///` + d.replace(/\\/g, "/"), p = null), P({
          folder: o,
          output: c,
          error: p,
          reportPath: d
        });
      });
    });
  }), h.handle("open-report", async (t, o) => {
    try {
      return await v.openPath(o), { success: !0 };
    } catch (s) {
      return { error: s.message };
    }
  });
});
export {
  L as MAIN_DIST,
  m as RENDERER_DIST,
  a as VITE_DEV_SERVER_URL
};
