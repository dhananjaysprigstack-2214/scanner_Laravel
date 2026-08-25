import { useState, useRef } from 'react'
import './App.css'

function App() {
  const [output, setOutput] = useState<string>('')
  const [isScanning, setIsScanning] = useState<boolean>(false)
  const [reportContent, setReportContent] = useState<string | null>(null)
  const [reportPath, setReportPath] = useState<string | null>(null)
  const [files, setFiles] = useState<FileList | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const handleFolderChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const selectedFiles = e.target.files
      setFiles(selectedFiles)
      setIsScanning(true)

      // Delay execution to allow React to render the "Scanning folder..." UI first
      setTimeout(() => {
        handleScan(selectedFiles)
      }, 10)
    }
  }

  const handleScan = async (selectedFiles: FileList) => {
    setOutput(`Scanning ${selectedFiles.length} files... Please wait.`)
    setReportContent(null)
    setReportPath(null)

    try {
      // 1. Calculate the absolute path of the root folder (works in Electron)
      // @ts-ignore - 'path' exists on File in Electron
      const absPath = selectedFiles[0].path;
      if (!absPath) {
        throw new Error("Cannot get absolute path. Make sure you are running the app in Electron.");
      }

      const relPath = selectedFiles[0].webkitRelativePath;
      
      const normalizedAbsPath = absPath.replace(/\\/g, '/');
      const relParts = relPath.split('/');
      
      // The part of the path inside the root folder
      const insidePath = relParts.slice(1).join('/'); 
      
      let rootFolderPath = normalizedAbsPath;
      if (insidePath && rootFolderPath.endsWith(insidePath)) {
          // Slice off the insidePath and the trailing slash
          rootFolderPath = rootFolderPath.slice(0, -(insidePath.length + 1));
      }

      // Restore Windows backslashes if needed
      if (absPath.includes('\\')) {
          rootFolderPath = rootFolderPath.replace(/\//g, '\\');
      }

      // @ts-ignore - ipcRenderer is exposed via preload
      const result = await window.ipcRenderer.invoke('scan-specific-folder', rootFolderPath)

      if (result.error) {
        throw new Error(result.error)
      }

      let newOutput = `Scanning: ${result.folder}\n\n`
      if (result.output) newOutput += result.output

      setOutput(newOutput)
      if (result.reportPath) {
        setReportPath(result.reportPath)
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
      await window.ipcRenderer.invoke('open-report', reportPath)
    }
  }

  const resetScan = () => {
    setFiles(null)
    setReportContent(null)
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
              🌐 Open Report in Browser
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
