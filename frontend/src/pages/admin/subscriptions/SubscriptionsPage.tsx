import { useEffect, useState } from 'react'
import { subscriptionsApi } from '../../../services/api'
import { ShoppingCart, Eye, RefreshCw, Trash2, Filter, CheckCircle, XCircle, Clock } from 'lucide-react'

interface Subscription {
  id: number
  user: { id: number; username: string }
  server: { name: string; flag_emoji: string } | null
  plan: { name: string } | null
  uuid: string
  marzban_username: string
  status: string
  total_traffic: number
  used_traffic: number
  expire_date: string | null
  created_at: string
}

export default function SubscriptionsPage() {
  const [subscriptions, setSubscriptions] = useState<Subscription[]>([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState('')
  const [selectedSubscription, setSelectedSubscription] = useState<Subscription | null>(null)

  useEffect(() => {
    loadSubscriptions()
  }, [statusFilter])

  const loadSubscriptions = async () => {
    setLoading(true)
    try {
      const response = await subscriptionsApi.list({ status: statusFilter || undefined })
      setSubscriptions(response.data)
    } catch (error) {
      console.error('Failed to load subscriptions:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleSync = async (id: number) => {
    try {
      await subscriptionsApi.sync(id)
      loadSubscriptions()
      alert('همگام‌سازی انجام شد')
    } catch (error) {
      console.error('Failed to sync subscription:', error)
      alert('خطا در همگام‌سازی')
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('آیا از حذف این اشتراک اطمینان دارید؟ این عمل کاربر را از سرور نیز حذف می‌کند.')) return
    
    try {
      await subscriptionsApi.delete(id)
      loadSubscriptions()
    } catch (error) {
      console.error('Failed to delete subscription:', error)
      alert('خطا در حذف اشتراک')
    }
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'active': return { label: 'فعال', color: 'bg-emerald-100 text-emerald-700', icon: CheckCircle }
      case 'expired': return { label: 'منقضی', color: 'bg-red-100 text-red-700', icon: XCircle }
      case 'banned': return { label: 'مسدود', color: 'bg-slate-100 text-slate-700', icon: XCircle }
      default: return { label: status, color: 'bg-slate-100 text-slate-700', icon: Clock }
    }
  }

  const formatTraffic = (bytes: number) => {
    if (bytes === 0) return 'نامحدود'
    const gb = bytes / (1024 * 1024 * 1024)
    if (gb >= 1) return `${gb.toFixed(2)} GB`
    const mb = bytes / (1024 * 1024)
    return `${mb.toFixed(2)} MB`
  }

  const getUsagePercent = (used: number, total: number) => {
    if (total === 0) return 0
    return Math.min(100, Math.round((used / total) * 100))
  }

  const getRemainingDays = (expireDate: string | null) => {
    if (!expireDate) return 'نامحدود'
    const now = new Date()
    const expire = new Date(expireDate)
    const diff = expire.getTime() - now.getTime()
    const days = Math.ceil(diff / (1000 * 60 * 60 * 24))
    if (days < 0) return 'منقضی'
    return `${days} روز`
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">مدیریت اشتراک‌ها</h1>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
        <div className="flex items-center gap-4">
          <Filter className="w-5 h-5 text-slate-400" />
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">همه وضعیت‌ها</option>
            <option value="active">فعال</option>
            <option value="expired">منقضی</option>
            <option value="banned">مسدود</option>
          </select>
          <button 
            onClick={loadSubscriptions}
            className="flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
          >
            <RefreshCw className="w-4 h-4" />
            بروزرسانی
          </button>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500"></div>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">شناسه</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">کاربر</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">سرور</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">پلن</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">وضعیت</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">مصرف</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">انقضا</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">عملیات</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {subscriptions.map((sub) => {
                  const statusBadge = getStatusBadge(sub.status)
                  const StatusIcon = statusBadge.icon
                  const usagePercent = getUsagePercent(sub.used_traffic, sub.total_traffic)

                  return (
                    <tr key={sub.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4 text-sm text-slate-600">#{sub.id}</td>
                      <td className="px-6 py-4">
                        <span className="font-medium text-slate-800">{sub.user?.username || '-'}</span>
                      </td>
                      <td className="px-6 py-4">
                        {sub.server ? (
                          <span className="flex items-center gap-2">
                            <span>{sub.server.flag_emoji}</span>
                            <span className="text-slate-700">{sub.server.name}</span>
                          </span>
                        ) : (
                          <span className="text-slate-400">چند سرور</span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">
                        {sub.plan?.name || '-'}
                      </td>
                      <td className="px-6 py-4">
                        <span className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium w-fit ${statusBadge.color}`}>
                          <StatusIcon className="w-3 h-3" />
                          {statusBadge.label}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <div className="w-32">
                          <div className="flex items-center justify-between text-xs mb-1">
                            <span className="text-slate-500">{formatTraffic(sub.used_traffic)}</span>
                            <span className="text-slate-400">{formatTraffic(sub.total_traffic)}</span>
                          </div>
                          <div className="w-full bg-slate-200 rounded-full h-1.5">
                            <div 
                              className={`h-1.5 rounded-full ${usagePercent >= 90 ? 'bg-red-500' : usagePercent >= 70 ? 'bg-yellow-500' : 'bg-emerald-500'}`}
                              style={{ width: `${usagePercent}%` }}
                            ></div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">
                        {getRemainingDays(sub.expire_date)}
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-2">
                          <button 
                            onClick={() => setSelectedSubscription(sub)}
                            className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" 
                            title="مشاهده"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          <button 
                            onClick={() => handleSync(sub.id)}
                            className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" 
                            title="همگام‌سازی"
                          >
                            <RefreshCw className="w-4 h-4" />
                          </button>
                          <button 
                            onClick={() => handleDelete(sub.id)}
                            className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" 
                            title="حذف"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Detail Modal */}
      {selectedSubscription && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
            <div className="p-6 border-b border-slate-200 flex items-center justify-between">
              <h2 className="text-xl font-bold text-slate-800">جزئیات اشتراک #{selectedSubscription.id}</h2>
              <button 
                onClick={() => setSelectedSubscription(null)}
                className="p-2 hover:bg-slate-100 rounded-lg"
              >
                ✕
              </button>
            </div>
            <div className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-slate-500">کاربر</p>
                  <p className="font-medium">{selectedSubscription.user?.username}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">وضعیت</p>
                  <p className="font-medium">{getStatusBadge(selectedSubscription.status).label}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">سرور</p>
                  <p className="font-medium">
                    {selectedSubscription.server 
                      ? `${selectedSubscription.server.flag_emoji} ${selectedSubscription.server.name}`
                      : 'چند سرور'}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">پلن</p>
                  <p className="font-medium">{selectedSubscription.plan?.name || '-'}</p>
                </div>
              </div>

              <div className="border-t pt-4">
                <p className="text-sm text-slate-500 mb-2">مصرف ترافیک</p>
                <div className="flex items-center justify-between mb-1">
                  <span>{formatTraffic(selectedSubscription.used_traffic)}</span>
                  <span>از {formatTraffic(selectedSubscription.total_traffic)}</span>
                </div>
                <div className="w-full bg-slate-200 rounded-full h-2">
                  <div 
                    className="h-2 rounded-full bg-emerald-500"
                    style={{ width: `${getUsagePercent(selectedSubscription.used_traffic, selectedSubscription.total_traffic)}%` }}
                  ></div>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4 border-t pt-4">
                <div>
                  <p className="text-sm text-slate-500">تاریخ انقضا</p>
                  <p className="font-medium">
                    {selectedSubscription.expire_date 
                      ? new Date(selectedSubscription.expire_date).toLocaleDateString('fa-IR')
                      : 'نامحدود'}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">روزهای باقیمانده</p>
                  <p className="font-medium">{getRemainingDays(selectedSubscription.expire_date)}</p>
                </div>
              </div>

              <div className="border-t pt-4">
                <p className="text-sm text-slate-500 mb-1">UUID</p>
                <p className="font-mono text-sm bg-slate-100 p-2 rounded break-all">{selectedSubscription.uuid}</p>
              </div>

              <div>
                <p className="text-sm text-slate-500 mb-1">نام کاربری Marzban</p>
                <p className="font-mono text-sm bg-slate-100 p-2 rounded">{selectedSubscription.marzban_username}</p>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

