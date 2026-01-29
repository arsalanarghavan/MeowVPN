import { useState, useEffect } from 'react'
import { Routes, Route, NavLink, Navigate } from 'react-router-dom'
import { useAuthStore } from '../../stores/authStore'
import { 
  LayoutDashboard, 
  Users, 
  ShoppingCart, 
  CreditCard, 
  LogOut,
  Plus,
  Wallet,
  TrendingUp,
  Copy,
  Check,
  RefreshCw,
  Eye
} from 'lucide-react'
import { clsx } from 'clsx'
import { subscriptionsApi, plansApi, usersApi, transactionsApi, resellersApi } from '../../services/api'

// Sidebar Component
function ResellerSidebar() {
  const { user, logout } = useAuthStore()

  const menuItems = [
    { path: '/', label: 'داشبورد', icon: LayoutDashboard },
    { path: '/users', label: 'کاربران من', icon: Users },
    { path: '/subscriptions', label: 'سرویس‌ها', icon: ShoppingCart },
    { path: '/transactions', label: 'تراکنش‌ها', icon: CreditCard },
  ]

  return (
    <aside className="w-64 bg-slate-900 text-white min-h-screen flex flex-col" dir="rtl">
      <div className="p-6 border-b border-slate-700">
        <h1 className="text-2xl font-bold text-purple-400">🐱 MeowVPN</h1>
        <p className="text-slate-400 text-sm mt-1">پنل نماینده</p>
      </div>

      <div className="p-4 border-b border-slate-700">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold">
            {user?.username?.[0]?.toUpperCase() || 'R'}
          </div>
          <div>
            <p className="font-medium">{user?.username}</p>
            <p className="text-sm text-slate-400">نماینده</p>
          </div>
        </div>
      </div>

      <nav className="flex-1 p-4 overflow-y-auto">
        <ul className="space-y-1">
          {menuItems.map((item) => (
            <li key={item.path}>
              <NavLink
                to={item.path}
                end={item.path === '/'}
                className={({ isActive }) =>
                  clsx(
                    'flex items-center gap-3 px-4 py-3 rounded-lg transition-colors',
                    isActive
                      ? 'bg-purple-500 text-white'
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

// Overview Page
function ResellerOverview() {
  const { user } = useAuthStore()
  const [stats, setStats] = useState<any>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadStats()
  }, [])

  const loadStats = async () => {
    try {
      // Load reseller data
      if (user?.id) {
        const response = await resellersApi.get(user.id)
        setStats({
          credit_limit: user.credit_limit || 0,
          current_debt: user.current_debt || 0,
          remaining_credit: (user.credit_limit || 0) - (user.current_debt || 0),
        })
      }
    } catch (error) {
      console.error('Failed to load stats:', error)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-slate-800">داشبورد نماینده</h1>

      {/* Credit Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-500">سقف اعتبار</p>
              <p className="text-2xl font-bold text-slate-800">{(stats?.credit_limit || 0).toLocaleString()} ﷼</p>
            </div>
            <div className="p-4 bg-blue-100 rounded-xl">
              <Wallet className="w-6 h-6 text-blue-600" />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-500">بدهی فعلی</p>
              <p className="text-2xl font-bold text-red-600">{(stats?.current_debt || 0).toLocaleString()} ﷼</p>
            </div>
            <div className="p-4 bg-red-100 rounded-xl">
              <CreditCard className="w-6 h-6 text-red-600" />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-500">اعتبار باقیمانده</p>
              <p className="text-2xl font-bold text-emerald-600">{(stats?.remaining_credit || 0).toLocaleString()} ﷼</p>
            </div>
            <div className="p-4 bg-emerald-100 rounded-xl">
              <TrendingUp className="w-6 h-6 text-emerald-600" />
            </div>
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
        <h3 className="text-lg font-semibold text-slate-800 mb-4">عملیات سریع</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <NavLink 
            to="/users"
            className="flex flex-col items-center justify-center p-4 bg-slate-50 rounded-lg hover:bg-purple-50 hover:text-purple-600 transition-colors"
          >
            <Users className="w-8 h-8 mb-2" />
            <span className="text-sm">کاربران من</span>
          </NavLink>
          <NavLink 
            to="/subscriptions"
            className="flex flex-col items-center justify-center p-4 bg-slate-50 rounded-lg hover:bg-purple-50 hover:text-purple-600 transition-colors"
          >
            <ShoppingCart className="w-8 h-8 mb-2" />
            <span className="text-sm">سرویس‌ها</span>
          </NavLink>
          <NavLink 
            to="/transactions"
            className="flex flex-col items-center justify-center p-4 bg-slate-50 rounded-lg hover:bg-purple-50 hover:text-purple-600 transition-colors"
          >
            <CreditCard className="w-8 h-8 mb-2" />
            <span className="text-sm">تراکنش‌ها</span>
          </NavLink>
          <button className="flex flex-col items-center justify-center p-4 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
            <Plus className="w-8 h-8 mb-2" />
            <span className="text-sm">ایجاد سرویس</span>
          </button>
        </div>
      </div>
    </div>
  )
}

// Users Page
function ResellerUsersPage() {
  const { user } = useAuthStore()
  const [users, setUsers] = useState<any[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadUsers()
  }, [])

  const loadUsers = async () => {
    try {
      if (user?.id) {
        const response = await resellersApi.users(user.id)
        setUsers(response.data)
      }
    } catch (error) {
      console.error('Failed to load users:', error)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">کاربران من</h1>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
          </div>
        ) : users.length === 0 ? (
          <div className="p-12 text-center text-slate-500">
            <Users className="w-16 h-16 mx-auto mb-4 text-slate-300" />
            <p>هنوز کاربری ندارید</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">شناسه</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">نام کاربری</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">تعداد سرویس</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">تاریخ عضویت</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {users.map((u) => (
                  <tr key={u.id} className="hover:bg-slate-50">
                    <td className="px-6 py-4 text-sm text-slate-600">#{u.id}</td>
                    <td className="px-6 py-4 font-medium text-slate-800">{u.username || '-'}</td>
                    <td className="px-6 py-4 text-sm text-slate-600">{u.subscriptions?.length || 0}</td>
                    <td className="px-6 py-4 text-sm text-slate-600">{new Date(u.created_at).toLocaleDateString('fa-IR')}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

// Subscriptions Page
function ResellerSubscriptionsPage() {
  const [subscriptions, setSubscriptions] = useState<any[]>([])
  const [plans, setPlans] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [selectedPlan, setSelectedPlan] = useState<number | null>(null)
  const [copied, setCopied] = useState<string | null>(null)

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      const [subsRes, plansRes] = await Promise.all([
        subscriptionsApi.list(),
        plansApi.list(),
      ])
      setSubscriptions(subsRes.data)
      setPlans(plansRes.data)
    } catch (error) {
      console.error('Failed to load data:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleCreateSubscription = async () => {
    if (!selectedPlan) return
    
    try {
      await subscriptionsApi.create({ plan_id: selectedPlan })
      setShowCreateModal(false)
      setSelectedPlan(null)
      loadData()
    } catch (error: any) {
      alert(error.response?.data?.error || 'خطا در ایجاد سرویس')
    }
  }

  const copyToClipboard = (text: string, id: string) => {
    navigator.clipboard.writeText(text)
    setCopied(id)
    setTimeout(() => setCopied(null), 2000)
  }

  const formatTraffic = (bytes: number) => {
    if (bytes === 0) return 'نامحدود'
    const gb = bytes / (1024 * 1024 * 1024)
    return `${gb.toFixed(2)} GB`
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">سرویس‌ها</h1>
        <button 
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600"
        >
          <Plus className="w-5 h-5" />
          ایجاد سرویس جدید
        </button>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
          </div>
        ) : subscriptions.length === 0 ? (
          <div className="p-12 text-center text-slate-500">
            <ShoppingCart className="w-16 h-16 mx-auto mb-4 text-slate-300" />
            <p>هنوز سرویسی ندارید</p>
            <button 
              onClick={() => setShowCreateModal(true)}
              className="mt-4 px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600"
            >
              ایجاد اولین سرویس
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">سرور</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">وضعیت</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">مصرف</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">انقضا</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">لینک</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {subscriptions.map((sub) => (
                  <tr key={sub.id} className="hover:bg-slate-50">
                    <td className="px-6 py-4">
                      {sub.server ? (
                        <span className="flex items-center gap-2">
                          <span>{sub.server.flag_emoji}</span>
                          <span>{sub.server.name}</span>
                        </span>
                      ) : 'چند سرور'}
                    </td>
                    <td className="px-6 py-4">
                      <span className={`px-2 py-1 rounded-full text-xs ${
                        sub.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                      }`}>
                        {sub.status === 'active' ? 'فعال' : 'منقضی'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm">
                      {formatTraffic(sub.used_traffic)} / {formatTraffic(sub.total_traffic)}
                    </td>
                    <td className="px-6 py-4 text-sm">
                      {sub.expire_date ? new Date(sub.expire_date).toLocaleDateString('fa-IR') : 'نامحدود'}
                    </td>
                    <td className="px-6 py-4">
                      <button 
                        onClick={() => copyToClipboard(`${window.location.origin}/api/sub/${sub.uuid}`, sub.uuid)}
                        className="flex items-center gap-1 text-sm text-purple-600 hover:text-purple-800"
                      >
                        {copied === sub.uuid ? <Check className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
                        {copied === sub.uuid ? 'کپی شد' : 'کپی لینک'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Create Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
            <div className="p-6 border-b border-slate-200">
              <h2 className="text-xl font-bold text-slate-800">ایجاد سرویس جدید</h2>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">انتخاب پلن</label>
                <div className="grid gap-3">
                  {plans.map((plan) => (
                    <button
                      key={plan.id}
                      onClick={() => setSelectedPlan(plan.id)}
                      className={`p-4 border-2 rounded-lg text-right transition-colors ${
                        selectedPlan === plan.id 
                          ? 'border-purple-500 bg-purple-50' 
                          : 'border-slate-200 hover:border-purple-300'
                      }`}
                    >
                      <div className="flex items-center justify-between">
                        <span className="font-medium">{plan.name}</span>
                        <span className="text-purple-600 font-bold">{plan.price_base.toLocaleString()} تومان</span>
                      </div>
                      <div className="text-sm text-slate-500 mt-1">
                        {plan.duration_days} روز - {formatTraffic(plan.traffic_bytes)}
                      </div>
                    </button>
                  ))}
                </div>
              </div>
              <div className="flex justify-end gap-3 pt-4">
                <button
                  onClick={() => setShowCreateModal(false)}
                  className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
                >
                  انصراف
                </button>
                <button
                  onClick={handleCreateSubscription}
                  disabled={!selectedPlan}
                  className="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 disabled:opacity-50"
                >
                  ایجاد سرویس
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// Transactions Page
function ResellerTransactionsPage() {
  const [transactions, setTransactions] = useState<any[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadTransactions()
  }, [])

  const loadTransactions = async () => {
    try {
      const response = await transactionsApi.list()
      setTransactions(response.data.data || response.data)
    } catch (error) {
      console.error('Failed to load transactions:', error)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-slate-800">تراکنش‌ها</h1>

      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
          </div>
        ) : transactions.length === 0 ? (
          <div className="p-12 text-center text-slate-500">
            <CreditCard className="w-16 h-16 mx-auto mb-4 text-slate-300" />
            <p>تراکنشی وجود ندارد</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">شناسه</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">مبلغ</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">نوع</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">وضعیت</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">تاریخ</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {transactions.map((tx) => (
                  <tr key={tx.id} className="hover:bg-slate-50">
                    <td className="px-6 py-4 text-sm text-slate-600">#{tx.id}</td>
                    <td className="px-6 py-4">
                      <span className={tx.amount >= 0 ? 'text-emerald-600' : 'text-red-600'}>
                        {tx.amount >= 0 ? '+' : ''}{tx.amount.toLocaleString()} ﷼
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm">{tx.type}</td>
                    <td className="px-6 py-4">
                      <span className={`px-2 py-1 rounded-full text-xs ${
                        tx.status === 'completed' ? 'bg-emerald-100 text-emerald-700' :
                        tx.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                        'bg-red-100 text-red-700'
                      }`}>
                        {tx.status === 'completed' ? 'موفق' : tx.status === 'pending' ? 'در انتظار' : 'ناموفق'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-sm">{new Date(tx.created_at).toLocaleDateString('fa-IR')}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

// Main Dashboard with Routes
export default function ResellerDashboard() {
  return (
    <div className="flex min-h-screen bg-slate-100" dir="rtl">
      <ResellerSidebar />
      <div className="flex-1 p-6 overflow-auto">
        <Routes>
          <Route index element={<ResellerOverview />} />
          <Route path="users" element={<ResellerUsersPage />} />
          <Route path="subscriptions" element={<ResellerSubscriptionsPage />} />
          <Route path="transactions" element={<ResellerTransactionsPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </div>
    </div>
  )
}
