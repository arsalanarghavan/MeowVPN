import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'
import { Bell, Search, RefreshCw } from 'lucide-react'
import { useState } from 'react'

export default function AdminLayout() {
  const [searchQuery, setSearchQuery] = useState('')

  return (
    <div className="flex min-h-screen bg-slate-100" dir="rtl">
      <Sidebar />
      
      <div className="flex-1 flex flex-col">
        {/* Header */}
        <header className="bg-white shadow-sm border-b border-slate-200 px-6 py-4">
          <div className="flex items-center justify-between">
            {/* Search */}
            <div className="relative w-96">
              <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
              <input
                type="text"
                placeholder="جستجو..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-4 pr-10 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>

            {/* Actions */}
            <div className="flex items-center gap-4">
              <button 
                onClick={() => window.location.reload()}
                className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                title="بروزرسانی"
              >
                <RefreshCw className="w-5 h-5" />
              </button>
              <button className="relative p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                <Bell className="w-5 h-5" />
                <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
              </button>
            </div>
          </div>
        </header>

        {/* Main Content */}
        <main className="flex-1 p-6 overflow-auto">
          <Outlet />
        </main>

        {/* Footer */}
        <footer className="bg-white border-t border-slate-200 px-6 py-3 text-center text-sm text-slate-500">
          MeowVPN Panel © {new Date().getFullYear()} - All rights reserved
        </footer>
      </div>
    </div>
  )
}

