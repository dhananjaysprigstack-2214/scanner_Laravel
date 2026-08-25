import { useState, useRef } from 'react'
import { flushSync } from 'react-dom'
import './App.css'

function App() {
  const [output, setOutput] = useState<string>('')
  const [isScanning, setIsScanning] = useState<boolean>(false)
  const [reportPath, setReportPath] = useState<string | null>(null)
  const [files, setFiles] = useState<FileList | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const handleFolderChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const selectedFiles = e.target.files
      
      const foldersToScan = ['app', 'routes', 'config', 'database', 'resources'];
      let relevantFileCount = 0;

      for (let i = 0; i < selectedFiles.length; i++) {
        const parts = selectedFiles[i].webkitRelativePath.split('/');
        if (foldersToScan.includes(parts[0]) || (parts.length > 1 && foldersToScan.includes(parts[1]))) {
          relevantFileCount++;
        }
      }

      // Force React to update the UI instantly before blocking the main thread
      flushSync(() => {
        setFiles(selectedFiles)
        setIsScanning(true)
        setOutput(`Scanning ${relevantFileCount} files... Please wait.`)
      })

      // Give browser time to paint the UI before we freeze it with heavy loops
      setTimeout(() => {
        handleScan(selectedFiles)
      }, 100)
    }
  }

  const handleScan = async (selectedFiles: FileList) => {
    setReportPath(null)

    try {
      // @ts-ignore - Check if we are in Electron and have an absolute path
      const absPath = selectedFiles[0].path;
      // @ts-ignore
      const isElectron = !!(window.ipcRenderer && absPath);

      if (isElectron) {
        // --- ELECTRON LOGIC (Instant, no upload limits) ---
        const relPath = selectedFiles[0].webkitRelativePath;
        const normalizedAbsPath = absPath.replace(/\\/g, '/');
        const relParts = relPath.split('/');
        const insidePath = relParts.slice(1).join('/'); 
        
        let rootFolderPath = normalizedAbsPath;
        if (insidePath && rootFolderPath.endsWith(insidePath)) {
            rootFolderPath = rootFolderPath.slice(0, -(insidePath.length + 1));
        }
        if (absPath.includes('\\')) {
            rootFolderPath = rootFolderPath.replace(/\//g, '\\');
        }

        // @ts-ignore
        const result = await window.ipcRenderer.invoke('scan-specific-folder', rootFolderPath)

        if (result.error) throw new Error(result.error)

        let newOutput = `Scanning: ${result.folder}\n\n${result.output || ''}`
        setOutput(newOutput)
        if (result.reportPath) setReportPath(result.reportPath)

      } else {
        // --- WEB BROWSER LOGIC ("In the live") ---
        const formData = new FormData()
        const foldersToScan = ['app', 'routes', 'config', 'database', 'resources'];

        for (let i = 0; i < selectedFiles.length; i++) {
          const file = selectedFiles[i]
          const parts = file.webkitRelativePath.split('/');
          
          // Only upload files in the targeted Laravel directories
          if (foldersToScan.includes(parts[0]) || (parts.length > 1 && foldersToScan.includes(parts[1]))) {
            formData.append('projectFiles', file)
            formData.append('paths', file.webkitRelativePath)
          }
        }

        const response = await fetch('/api/scan-folder', {
          method: 'POST',
          body: formData,
        })

        const text = await response.text()
        let result;
        try {
          result = JSON.parse(text)
        } catch (parseErr) {
          throw new Error(`Server returned invalid JSON (Status ${response.status}). The uploaded folder might be too large. Response snippet: ${text.substring(0, 150)}`)
        }

        if (!response.ok) throw new Error(result.error || 'Server error')

        let newOutput = `Scanning: ${result.folder}\n\n${result.output || ''}`
        if (result.error) newOutput += `\n[ERRORS]\n${result.error}`

        setOutput(newOutput)
      }
    } catch (err: any) {
      setOutput(`Error invoking scanner: ${err.message}`)
    } finally {
      setIsScanning(false)
    }
  }

  const handleOpenReport = async () => {
    if (reportPath) {
      // @ts-ignore
      if (window.ipcRenderer) {
        // @ts-ignore
        await window.ipcRenderer.invoke('open-report', reportPath)
      } else {
        alert("Report viewing is only fully supported in the desktop app directly. In the web version, please view the report via the backend.")
      }
    }
  }

  const resetScan = () => {
    setFiles(null)
    setReportPath(null)
    setOutput('')
    if (inputRef.current) {
      inputRef.current.value = ''
    }
  }

  return (
    <div style={{ padding: '2rem', fontFamily: 'system-ui, sans-serif', textAlign: 'center' }}>
      <h1 style={{ color: '#60a5fa' }}>Laravel Build Checker</h1>
      <p style={{ color: '#94a3b8' }}>Select your Laravel project folder to scan for errors and build issues.</p>
      
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '15px', marginBottom: '20px' }}>
        {!files && !isScanning && !reportPath && (
          <input
            type="file"
            // @ts-ignore
            webkitdirectory="true"
            directory="true"
            multiple
            onChange={handleFolderChange}
            ref={inputRef}
            style={{ padding: '10px', background: '#1e293b', color: 'white', borderRadius: '6px', cursor: 'pointer' }}
          />
        )}

        {isScanning && (
          <div style={{ color: '#3b82f6', fontSize: '18px', fontWeight: 'bold', margin: '20px 0' }}>
            ⏳ Scanning folder, please wait...
          </div>
        )}

        {reportPath && !isScanning && (
          <div style={{ display: 'flex', gap: '10px', justifyContent: 'center', marginTop: '10px' }}>
            <button
              onClick={handleOpenReport}
              style={{
                background: '#10b981',
                color: 'white',
                border: 'none',
                padding: '10px 20px',
                borderRadius: '6px',
                fontSize: '16px',
                cursor: 'pointer',
                fontWeight: 'bold'
              }}
            >
              🌐 Open Report
            </button>

            <button
              onClick={resetScan}
              style={{
                background: '#475569',
                color: 'white',
                border: 'none',
                padding: '10px 20px',
                borderRadius: '6px',
                fontSize: '16px',
                cursor: 'pointer',
                fontWeight: 'bold'
              }}
            >
              Scan Another Folder
            </button>
          </div>
        )}
      </div>

      <div style={{
        background: '#1e293b',
        color: '#f8fafc',
        padding: '15px',
        borderRadius: '8px',
        minHeight: '300px',
        whiteSpace: 'pre-wrap',
        fontFamily: 'monospace',
        overflowY: 'auto',
        maxHeight: '500px',
        border: '1px solid #334155',
        textAlign: 'left',
        marginTop: '20px'
      }}>
        {output || 'Output will appear here...'}
      </div>
    </div>
  )
}

export default App
