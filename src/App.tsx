import { useState } from 'react'
import './App.css'

function App() {
  const [output, setOutput] = useState<string>('')
  const [isScanning, setIsScanning] = useState<boolean>(false)
  const [reportContent, setReportContent] = useState<string | null>(null)
  const [files, setFiles] = useState<FileList | null>(null)

  const handleFolderChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      setFiles(e.target.files)
    }
  }

  const handleScan = async () => {
    if (!files || files.length === 0) {
      setOutput('Please select a folder first.')
      return
    }

    setIsScanning(true)
    setOutput(`Uploading ${files.length} files... This might take a while for large folders.`)
    setReportContent(null)

    const formData = new FormData()
    
    // Append all files with their relative paths
    for (let i = 0; i < files.length; i++) {
      const file = files[i]
      // Skip node_modules and vendor to prevent browser/server crashing
      if (file.webkitRelativePath.includes('/node_modules/') || file.webkitRelativePath.includes('/vendor/')) {
        continue
      }
      formData.append('projectFiles', file)
      formData.append('paths', file.webkitRelativePath)
    }

    try {
      const response = await fetch('/api/scan-folder', {
        method: 'POST',
        body: formData,
      })

      const result = await response.json()

      if (!response.ok) {
        throw new Error(result.error || 'Server error')
      }

      let newOutput = `Scanning: ${result.folder}\n\n`
      if (result.output) newOutput += result.output
      if (result.error) newOutput += `\n[ERRORS]\n${result.error}`

      setOutput(newOutput)
      if (result.reportContent) setReportContent(result.reportContent)
    } catch (err: any) {
      setOutput(`Error invoking scanner: ${err.message}`)
    } finally {
      setIsScanning(false)
    }
  }

  const handleOpenReport = () => {
    if (reportContent) {
      const blob = new Blob([reportContent], { type: 'text/html' })
      const url = URL.createObjectURL(blob)
      window.open(url, '_blank')
    }
  }

  return (
    <div style={{ padding: '2rem', fontFamily: 'system-ui, sans-serif', textAlign: 'center' }}>
      <h1 style={{ color: '#60a5fa' }}>Laravel Build Checker</h1>
      <p style={{ color: '#94a3b8' }}>Select your Laravel project folder to scan for vulnerabilities and build issues.</p>
      
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '15px', marginBottom: '20px' }}>
        <input 
          type="file" 
          // @ts-ignore
          webkitdirectory="true"
          directory="true"
          multiple
          onChange={handleFolderChange}
          disabled={isScanning}
          style={{ padding: '10px', background: '#1e293b', color: 'white', borderRadius: '6px' }}
        />

        <div style={{ display: 'flex', gap: '10px', justifyContent: 'center' }}>
          <button 
            onClick={handleScan}
            disabled={isScanning || !files}
            style={{
              background: (isScanning || !files) ? '#475569' : '#3b82f6',
              color: 'white',
              border: 'none',
              padding: '10px 20px',
              borderRadius: '6px',
              fontSize: '16px',
              cursor: (isScanning || !files) ? 'not-allowed' : 'pointer',
              fontWeight: 'bold'
            }}
          >
            {isScanning ? 'Scanning...' : 'Upload Folder & Scan'}
          </button>

          {reportContent && !isScanning && (
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
              🌐 View HTML Report
            </button>
          )}
        </div>
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
