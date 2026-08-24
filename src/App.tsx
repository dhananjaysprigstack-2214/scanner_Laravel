import { useState } from 'react'
import './App.css'

function App() {
  const [output, setOutput] = useState<string>('')
  const [isScanning, setIsScanning] = useState<boolean>(false)
  const [reportPath, setReportPath] = useState<string | null>(null)

  const handleScan = async () => {
    setIsScanning(true)
    setOutput('Waiting for folder selection...')
    setReportPath(null)

    try {
      // @ts-ignore - ipcRenderer is exposed via preload
      const result = await window.ipcRenderer.invoke('scan-folder')
      
      if (result.canceled) {
        setOutput('Scan canceled.')
        setIsScanning(false)
        return
      }

      let newOutput = `Scanning: ${result.folder}\n\n`
      if (result.output) newOutput += result.output
      if (result.error) newOutput += `\n[ERRORS]\n${result.error}`

      setOutput(newOutput)
      if (result.reportPath) setReportPath(result.reportPath)
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

  return (
    <div style={{ padding: '2rem', fontFamily: 'system-ui, sans-serif', textAlign: 'center' }}>
      <h1 style={{ color: '#60a5fa' }}>Laravel Build Checker</h1>
      <p style={{ color: '#94a3b8' }}>Select a Laravel project folder to scan for vulnerabilities and build issues.</p>
      
      <div style={{ display: 'flex', gap: '10px', marginBottom: '20px', justifyContent: 'center' }}>
        <button 
          onClick={handleScan}
          disabled={isScanning}
          style={{
            background: isScanning ? '#475569' : '#3b82f6',
            color: 'white',
            border: 'none',
            padding: '10px 20px',
            borderRadius: '6px',
            fontSize: '16px',
            cursor: isScanning ? 'not-allowed' : 'pointer',
            fontWeight: 'bold'
          }}
        >
          {isScanning ? 'Scanning...' : '📁 Select Folder & Scan'}
        </button>

        {reportPath && !isScanning && (
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
            🌐 Open in Browser
          </button>
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
        textAlign: 'left'
      }}>
        {output || 'Output will appear here...'}
      </div>
    </div>
  )
}

export default App
