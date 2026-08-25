import { useState } from 'react'
import './App.css'

function App() {
  const [output, setOutput] = useState<string>('')
  const [isScanning, setIsScanning] = useState<boolean>(false)
  const [reportContent, setReportContent] = useState<string | null>(null)
  const [file, setFile] = useState<File | null>(null)

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      setFile(e.target.files[0])
    }
  }

  const handleScan = async () => {
    if (!file) {
      setOutput('Please select a .zip file first.')
      return
    }

    setIsScanning(true)
    setOutput('Uploading and scanning... This may take a moment.')
    setReportContent(null)

    const formData = new FormData()
    formData.append('projectZip', file)

    try {
      const response = await fetch('/api/scan', {
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
      <p style={{ color: '#94a3b8' }}>Upload a `.zip` of your Laravel project folder to scan for vulnerabilities and build issues.</p>
      
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '15px', marginBottom: '20px' }}>
        <input 
          type="file" 
          accept=".zip" 
          onChange={handleFileChange}
          disabled={isScanning}
          style={{ padding: '10px', background: '#1e293b', color: 'white', borderRadius: '6px' }}
        />

        <div style={{ display: 'flex', gap: '10px', justifyContent: 'center' }}>
          <button 
            onClick={handleScan}
            disabled={isScanning || !file}
            style={{
              background: (isScanning || !file) ? '#475569' : '#3b82f6',
              color: 'white',
              border: 'none',
              padding: '10px 20px',
              borderRadius: '6px',
              fontSize: '16px',
              cursor: (isScanning || !file) ? 'not-allowed' : 'pointer',
              fontWeight: 'bold'
            }}
          >
            {isScanning ? 'Scanning...' : 'Upload & Scan'}
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
