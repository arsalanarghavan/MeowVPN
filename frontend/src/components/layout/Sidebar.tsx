import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard,
  Users,
  Server,
  CreditCard,
  Package,
  ShoppingCart,
  Settings,
  LogOut,
  UserCog,
  TrendingUp,
  MessageSquare,
  FileText,
} from 'lucide-react'
import { useAuthStore } from '../../stores/authStore'
import { clsx } from 'clsx'

const adminMenuItems = [
  { path: '/', label: 'داشبورد', icon: LayoutDashboard },
  { path: '/users', label: 'کاربران', icon: Users },
  { path: '/servers', label: 'سرورها', icon: Server },
  { path: '/plans', label: 'پلن‌ها', icon: Package },
  { path: '/subscriptions', label: 'اشتراک‌ها', icon: ShoppingCart },
  { path: '/transactions', label: 'تراکنش‌ها', icon: CreditCard },
  { path: '/resellers', label: 'نمایندگان', icon: UserCog },
  { path: '/affiliates', label: 'بازاریابان', icon: TrendingUp },
  { path: '/tickets', label: 'تیکت‌ها', icon: MessageSquare },
  { path: '/invoices', label: 'فاکتورها', icon: FileText },
  { path: '/settings', label: 'تنظیمات', icon: Settings },
]

export default function Sidebar() {
  const { user, logout } = useAuthStore()

  return (
    <aside className="w-64 bg-slate-900 text-white min-h-screen flex flex-col" dir="rtl">
      {/* Logo */}
      <div className="p-6 border-b border-slate-700">
        <h1 className="text-2xl font-bold text-emerald-400">🐱 MeowVPN</h1>
        <p className="text-slate-400 text-sm mt-1">پنل مدیریت</p>
      </div>

      {/* User Info */}
      <div className="p-4 border-b border-slate-700">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold">
            {user?.username?.[0]?.toUpperCase() || 'A'}
          </div>
          <div>
            <p className="font-medium">{user?.username}</p>
            <p className="text-sm text-slate-400">{user?.role === 'admin' ? 'مدیر' : 'کاربر'}</p>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 p-4 overflow-y-auto">
        <ul className="space-y-1">
          {adminMenuItems.map((item) => (
            <li key={item.path}>
              <NavLink
                to={item.path}
                end={item.path === '/'}
                className={({ isActive }) =>
                  clsx(
                    'flex items-center gap-3 px-4 py-3 rounded-lg transition-colors',
                    isActive
                      ? 'bg-emerald-500 text-white'
                      : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                  )
                }
              >
                <item.icon className="w-5 h-5" />
                <span>{item.label}</span>
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>

      {/* Logout */}
      <div className="p-4 border-t border-slate-700">
        <button
          onClick={logout}
          className="flex items-center gap-3 px-4 py-3 w-full rounded-lg text-slate-300 hover:bg-red-500/20 hover:text-red-400 transition-colors"
        >
          <LogOut className="w-5 h-5" />
          <span>خروج</span>
        </button>
      </div>
    </aside>
  )
}

