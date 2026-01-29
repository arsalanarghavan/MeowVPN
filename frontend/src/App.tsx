import { useState, useEffect } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { useAuthStore } from './stores/authStore'
import SetupWizard from './components/setup/SetupWizard'
import Login from './pages/Login'
import AdminDashboard from './pages/admin/Dashboard'
import ResellerDashboard from './pages/reseller/Dashboard'

function App() {
  const { user, isSetupComplete, checkSetup, checkAuth } = useAuthStore()
  const [checking, setChecking] = useState(true)

  useEffect(() => {
    const init = async () => {
      try {
        await checkSetup()
        // If setup is complete and there's a token, check auth
        const token = localStorage.getItem('token')
        if (token) {
          await checkAuth()
        }
      } catch (error) {
        console.error('Initialization error:', error)
      } finally {
        setChecking(false)
      }
    }
    init()
  }, [checkSetup, checkAuth])

  if (checking) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-100" dir="rtl">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500 mx-auto mb-4"></div>
          <p className="text-slate-600">در حال بارگذاری...</p>
        </div>
      </div>
    )
  }

  // Check if setup is needed
  if (!isSetupComplete) {
    return <SetupWizard />
  }

  // Check if user is logged in
  if (!user) {
    return <Login />
  }

  return (
    <BrowserRouter>
      <Routes>
        {user.role === 'admin' && (
          <Route path="/*" element={<AdminDashboard />} />
        )}
        {user.role === 'reseller' && (
          <Route path="/*" element={<ResellerDashboard />} />
        )}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
