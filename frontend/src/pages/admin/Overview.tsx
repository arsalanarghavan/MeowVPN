import { useEffect, useState } from 'react'
import { dashboardApi, transactionsApi } from '../../services/api'
import { Users, ShoppingCart, CreditCard, TrendingUp, ArrowUp, ArrowDown } from 'lucide-react'
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'

interface Stats {
  total_users: number
  active_subscriptions: number
  today_sales: number
  monthly_sales: number
}

interface Transaction {
  id: number
  user: { username: string }
  amount: number
  type: string
  status: string
  created_at: string
}

export default function Overview() {
  const [stats, setStats] = useState<Stats | null>(null)
  const [salesData, setSalesData] = useState<any[]>([])
  const [recentTransactions, setRecentTransactions] = useState<Transaction[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      const [statsRes, salesRes, transactionsRes] = await Promise.all([
        dashboardApi.stats(),
        dashboardApi.sales('month'),
        dashboardApi.recentTransactions(),
      ])
      setStats(statsRes.data)
      setSalesData(salesRes.data)
      setRecentTransactions(transactionsRes.data)
    } catch (error) {
      console.error('Failed to load dashboard data:', error)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500"></div>
      </div>
    )
  }

  const statCards = [
    {
      title: 'کل کاربران',
      value: stats?.total_users || 0,
      icon: Users,
      color: 'bg-blue-500',
      change: '+12%',
      increasing: true,
    },
    {
      title: 'سرویس‌های فعال',
      value: stats?.active_subscriptions || 0,
      icon: ShoppingCart,
      color: 'bg-emerald-500',
      change: '+8%',
      increasing: true,
    },
    {
      title: 'فروش امروز',
      value: (stats?.today_sales || 0).toLocaleString() + ' ﷼',
      icon: CreditCard,
      color: 'bg-purple-500',
      change: '+23%',
      increasing: true,
    },
    {
      title: 'فروش ماهانه',
      value: (stats?.monthly_sales || 0).toLocaleString() + ' ﷼',
      icon: TrendingUp,
      color: 'bg-orange-500',
      change: '+15%',
      increasing: true,
    },
  ]

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-slate-800">داشبورد</h1>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {statCards.map((stat, index) => (
          <div key={index} className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-slate-500">{stat.title}</p>
                <p className="text-2xl font-bold text-slate-800 mt-1">{stat.value}</p>
                <div className={`flex items-center gap-1 mt-2 text-sm ${stat.increasing ? 'text-emerald-500' : 'text-red-500'}`}>
                  {stat.increasing ? <ArrowUp className="w-4 h-4" /> : <ArrowDown className="w-4 h-4" />}
                  <span>{stat.change}</span>
                  <span className="text-slate-400">از ماه قبل</span>
                </div>
              </div>
              <div className={`${stat.color} p-4 rounded-xl`}>
                <stat.icon className="w-6 h-6 text-white" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Sales Chart */}
        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <h3 className="text-lg font-semibold text-slate-800 mb-4">نمودار فروش ماهانه</h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={salesData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                <XAxis dataKey="date" stroke="#64748b" fontSize={12} />
                <YAxis stroke="#64748b" fontSize={12} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: '#1e293b',
                    border: 'none',
                    borderRadius: '8px',
                    color: '#fff',
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="total"
                  stroke="#10b981"
                  fill="url(#colorSales)"
                  strokeWidth={2}
                />
                <defs>
                  <linearGradient id="colorSales" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#10b981" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                  </linearGradient>
                </defs>
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Recent Transactions */}
        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <h3 className="text-lg font-semibold text-slate-800 mb-4">تراکنش‌های اخیر</h3>
          <div className="space-y-3">
            {recentTransactions.slice(0, 5).map((tx) => (
              <div key={tx.id} className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                <div className="flex items-center gap-3">
                  <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
                    tx.type === 'deposit' ? 'bg-emerald-100 text-emerald-600' :
                    tx.type === 'purchase' ? 'bg-purple-100 text-purple-600' :
                    'bg-slate-100 text-slate-600'
                  }`}>
                    <CreditCard className="w-5 h-5" />
                  </div>
                  <div>
                    <p className="font-medium text-slate-800">{tx.user?.username || 'کاربر'}</p>
                    <p className="text-sm text-slate-500">
                      {tx.type === 'deposit' ? 'شارژ کیف پول' :
                       tx.type === 'purchase' ? 'خرید سرویس' :
                       tx.type === 'renewal' ? 'تمدید سرویس' : tx.type}
                    </p>
                  </div>
                </div>
                <div className="text-left">
                  <p className={`font-medium ${tx.amount >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                    {tx.amount >= 0 ? '+' : ''}{tx.amount.toLocaleString()} ﷼
                  </p>
                  <p className={`text-xs ${
                    tx.status === 'completed' ? 'text-emerald-500' :
                    tx.status === 'pending' ? 'text-yellow-500' :
                    'text-red-500'
                  }`}>
                    {tx.status === 'completed' ? 'موفق' :
                     tx.status === 'pending' ? 'در انتظار' :
                     'ناموفق'}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}

