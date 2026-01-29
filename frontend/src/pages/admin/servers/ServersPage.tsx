import { useEffect, useState } from 'react'
import { serversApi } from '../../../services/api'
import { Server, Plus, Edit, Trash2, Activity, RefreshCw, CheckCircle, XCircle, Cpu, HardDrive, Users, Wifi, WifiOff, TestTube } from 'lucide-react'

interface ServerData {
  id: number
  name: string
  flag_emoji: string
  ip_address: string
  api_domain: string
  capacity: number
  active_users_count: number
  type: string
  location_tag: string
  is_active: boolean
  panel_type: 'marzban' | 'hiddify'
  created_at: string
}

interface ServerHealth {
  status: string
  cpu: number
  ram: number
  total_users?: number
  active_users?: number
  online_users?: number
  version?: string
  uptime?: number
}

interface MonitoringData {
  servers: Array<ServerData & { health: ServerHealth; usage_percentage: number; available_slots: number }>
  summary: {
    total_servers: number
    online_servers: number
    offline_servers: number
    total_capacity: number
    total_active_users: number
    marzban_servers: number
    hiddify_servers: number
  }
}

export default function ServersPage() {
  const [servers, setServers] = useState<ServerData[]>([])
  const [loading, setLoading] = useState(true)
  const [healthData, setHealthData] = useState<Record<number, ServerHealth>>({})
  const [showModal, setShowModal] = useState(false)
  const [editingServer, setEditingServer] = useState<ServerData | null>(null)
  const [activeTab, setActiveTab] = useState<'list' | 'monitoring'>('list')
  const [monitoringData, setMonitoringData] = useState<MonitoringData | null>(null)
  const [monitoringLoading, setMonitoringLoading] = useState(false)
  const [testingConnection, setTestingConnection] = useState<number | null>(null)
  const [formData, setFormData] = useState({
    name: '',
    flag_emoji: '🇩🇪',
    ip_address: '',
    api_domain: '',
    admin_user: '',
    admin_pass: '',
    api_key: '',
    capacity: 100,
    type: 'single',
    location_tag: 'DE',
    is_active: true,
    panel_type: 'marzban' as 'marzban' | 'hiddify',
  })

  useEffect(() => {
    loadServers()
  }, [])

  const loadServers = async () => {
    setLoading(true)
    try {
      const response = await serversApi.list()
      setServers(response.data)
    } catch (error) {
      console.error('Failed to load servers:', error)
    } finally {
      setLoading(false)
    }
  }

  const loadMonitoring = async () => {
    setMonitoringLoading(true)
    try {
      const response = await serversApi.monitoring()
      setMonitoringData(response.data)
    } catch (error) {
      console.error('Failed to load monitoring:', error)
    } finally {
      setMonitoringLoading(false)
    }
  }

  const checkHealth = async (serverId: number) => {
    try {
      const response = await serversApi.health(serverId)
      setHealthData(prev => ({ ...prev, [serverId]: response.data }))
    } catch (error) {
      setHealthData(prev => ({ ...prev, [serverId]: { status: 'error', cpu: 0, ram: 0 } }))
    }
  }

  const testConnection = async (serverId: number) => {
    setTestingConnection(serverId)
    try {
      const response = await serversApi.testConnection(serverId)
      if (response.data.success) {
        alert('✅ اتصال موفق!')
      } else {
        alert(`❌ اتصال ناموفق: ${response.data.message}`)
      }
    } catch (error: any) {
      alert(`❌ خطا در تست اتصال: ${error.response?.data?.message || error.message}`)
    } finally {
      setTestingConnection(null)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    // Validate based on panel type
    if (formData.panel_type === 'marzban' && !editingServer) {
      if (!formData.admin_user || !formData.admin_pass) {
        alert('نام کاربری و رمز عبور ادمین برای پنل مرزبان الزامی است')
        return
      }
    } else if (formData.panel_type === 'hiddify' && !editingServer) {
      if (!formData.api_key) {
        alert('API Key برای پنل هیدیفای الزامی است')
        return
      }
    }

    try {
      if (editingServer) {
        await serversApi.update(editingServer.id, formData)
      } else {
        await serversApi.create(formData)
      }
      setShowModal(false)
      setEditingServer(null)
      resetForm()
      loadServers()
    } catch (error: any) {
      console.error('Failed to save server:', error)
      alert(error.response?.data?.error || 'خطا در ذخیره سرور')
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('آیا از حذف این سرور اطمینان دارید؟')) return
    
    try {
      await serversApi.delete(id)
      loadServers()
    } catch (error) {
      console.error('Failed to delete server:', error)
      alert('خطا در حذف سرور')
    }
  }

  const openEditModal = (server: ServerData) => {
    setEditingServer(server)
    setFormData({
      name: server.name,
      flag_emoji: server.flag_emoji,
      ip_address: server.ip_address,
      api_domain: server.api_domain,
      admin_user: '',
      admin_pass: '',
      api_key: '',
      capacity: server.capacity,
      type: server.type,
      location_tag: server.location_tag,
      is_active: server.is_active,
      panel_type: server.panel_type || 'marzban',
    })
    setShowModal(true)
  }

  const resetForm = () => {
    setFormData({
      name: '',
      flag_emoji: '🇩🇪',
      ip_address: '',
      api_domain: '',
      admin_user: '',
      admin_pass: '',
      api_key: '',
      capacity: 100,
      type: 'single',
      location_tag: 'DE',
      is_active: true,
      panel_type: 'marzban',
    })
  }

  const getUsagePercent = (active: number, capacity: number) => {
    return Math.round((active / capacity) * 100)
  }

  const getUsageColor = (percent: number) => {
    if (percent >= 90) return 'bg-red-500'
    if (percent >= 70) return 'bg-yellow-500'
    return 'bg-emerald-500'
  }

  const getPanelTypeColor = (type: string) => {
    return type === 'hiddify' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">مدیریت سرورها</h1>
        <div className="flex items-center gap-3">
          {/* Tabs */}
          <div className="flex bg-slate-100 rounded-lg p-1">
            <button
              onClick={() => setActiveTab('list')}
              className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                activeTab === 'list' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-600 hover:text-slate-800'
              }`}
            >
              لیست سرورها
            </button>
            <button
              onClick={() => { setActiveTab('monitoring'); loadMonitoring() }}
              className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                activeTab === 'monitoring' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-600 hover:text-slate-800'
              }`}
            >
              مانیتورینگ
            </button>
          </div>
          <button 
            onClick={() => { resetForm(); setEditingServer(null); setShowModal(true) }}
            className="flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition-colors"
          >
            <Plus className="w-5 h-5" />
            افزودن سرور
          </button>
        </div>
      </div>

      {/* Monitoring View */}
      {activeTab === 'monitoring' && (
        <div className="space-y-6">
          {/* Summary Cards */}
          {monitoringData && (
            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
              <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div className="text-2xl font-bold text-slate-800">{monitoringData.summary.total_servers}</div>
                <div className="text-sm text-slate-500">کل سرورها</div>
              </div>
              <div className="bg-white rounded-xl shadow-sm border border-emerald-200 p-4">
                <div className="text-2xl font-bold text-emerald-600">{monitoringData.summary.online_servers}</div>
                <div className="text-sm text-slate-500">آنلاین</div>
              </div>
              <div className="bg-white rounded-xl shadow-sm border border-red-200 p-4">
                <div className="text-2xl font-bold text-red-600">{monitoringData.summary.offline_servers}</div>
                <div className="text-sm text-slate-500">آفلاین</div>
              </div>
              <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div className="text-2xl font-bold text-slate-800">{monitoringData.summary.total_capacity}</div>
                <div className="text-sm text-slate-500">ظرفیت کل</div>
              </div>
              <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div className="text-2xl font-bold text-slate-800">{monitoringData.summary.total_active_users}</div>
                <div className="text-sm text-slate-500">کاربران فعال</div>
              </div>
              <div className="bg-white rounded-xl shadow-sm border border-blue-200 p-4">
                <div className="text-2xl font-bold text-blue-600">{monitoringData.summary.marzban_servers}</div>
                <div className="text-sm text-slate-500">مرزبان</div>
              </div>
              <div className="bg-white rounded-xl shadow-sm border border-purple-200 p-4">
                <div className="text-2xl font-bold text-purple-600">{monitoringData.summary.hiddify_servers}</div>
                <div className="text-sm text-slate-500">هیدیفای</div>
              </div>
            </div>
          )}

          {/* Refresh Button */}
          <div className="flex justify-end">
            <button
              onClick={loadMonitoring}
              disabled={monitoringLoading}
              className="flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50"
            >
              <RefreshCw className={`w-4 h-4 ${monitoringLoading ? 'animate-spin' : ''}`} />
              بروزرسانی
            </button>
          </div>

          {/* Monitoring Table */}
          {monitoringLoading && !monitoringData ? (
            <div className="flex items-center justify-center h-64">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500"></div>
            </div>
          ) : monitoringData ? (
            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-slate-50">
                    <tr>
                      <th className="px-4 py-3 text-right text-sm font-medium text-slate-600">سرور</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">وضعیت</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">پنل</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">CPU</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">RAM</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">کاربران</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">آنلاین</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">ظرفیت</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200">
                    {monitoringData.servers.map((server) => (
                      <tr key={server.id} className="hover:bg-slate-50">
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-2">
                            <span className="text-xl">{server.flag_emoji}</span>
                            <div>
                              <div className="font-medium text-slate-800">{server.name}</div>
                              <div className="text-xs text-slate-500">{server.api_domain}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-center">
                          {server.health.status === 'online' ? (
                            <span className="inline-flex items-center gap-1 px-2 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs">
                              <Wifi className="w-3 h-3" /> آنلاین
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">
                              <WifiOff className="w-3 h-3" /> آفلاین
                            </span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-center">
                          <span className={`px-2 py-1 rounded-full text-xs ${getPanelTypeColor(server.panel_type)}`}>
                            {server.panel_type === 'hiddify' ? 'هیدیفای' : 'مرزبان'}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-center">
                          <div className="flex items-center justify-center gap-1">
                            <Cpu className="w-4 h-4 text-slate-400" />
                            <span className={`font-mono ${server.health.cpu > 80 ? 'text-red-600' : 'text-slate-700'}`}>
                              {server.health.cpu || 0}%
                            </span>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-center">
                          <div className="flex items-center justify-center gap-1">
                            <HardDrive className="w-4 h-4 text-slate-400" />
                            <span className={`font-mono ${server.health.ram > 80 ? 'text-red-600' : 'text-slate-700'}`}>
                              {server.health.ram || 0}%
                            </span>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-center">
                          <span className="font-mono text-slate-700">
                            {server.active_users_count} / {server.capacity}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-center">
                          <span className="font-mono text-emerald-600">
                            {server.health.online_users || 0}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <div className="w-full bg-slate-200 rounded-full h-2">
                            <div
                              className={`h-2 rounded-full ${getUsageColor(server.usage_percentage)}`}
                              style={{ width: `${Math.min(server.usage_percentage, 100)}%` }}
                            />
                          </div>
                          <div className="text-xs text-center text-slate-500 mt-1">{server.usage_percentage}%</div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : null}
        </div>
      )}

      {/* Server List View */}
      {activeTab === 'list' && (
        <>
          {loading ? (
            <div className="flex items-center justify-center h-64">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500"></div>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {servers.map((server) => {
                const usagePercent = getUsagePercent(server.active_users_count, server.capacity)
                const health = healthData[server.id]
                
                return (
                  <div key={server.id} className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="p-6">
                      <div className="flex items-center justify-between mb-4">
                        <div className="flex items-center gap-3">
                          <span className="text-3xl">{server.flag_emoji}</span>
                          <div>
                            <h3 className="font-semibold text-slate-800">{server.name}</h3>
                            <p className="text-sm text-slate-500">{server.location_tag}</p>
                          </div>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                          <div className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs ${
                            server.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                          }`}>
                            {server.is_active ? (
                              <><CheckCircle className="w-3 h-3" /> فعال</>
                            ) : (
                              <><XCircle className="w-3 h-3" /> غیرفعال</>
                            )}
                          </div>
                          <span className={`px-2 py-0.5 rounded-full text-xs ${getPanelTypeColor(server.panel_type || 'marzban')}`}>
                            {server.panel_type === 'hiddify' ? 'هیدیفای' : 'مرزبان'}
                          </span>
                        </div>
                      </div>

                      <div className="space-y-3">
                        <div className="flex items-center justify-between text-sm">
                          <span className="text-slate-500">IP:</span>
                          <span className="font-mono text-slate-700">{server.ip_address}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                          <span className="text-slate-500">دامنه:</span>
                          <span className="font-mono text-slate-700 text-xs">{server.api_domain}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                          <span className="text-slate-500">نوع:</span>
                          <span className="text-slate-700">{server.type === 'single' ? 'تک سرور' : 'چند سرور'}</span>
                        </div>
                      </div>

                      {/* Usage Bar */}
                      <div className="mt-4">
                        <div className="flex items-center justify-between text-sm mb-1">
                          <span className="text-slate-500">ظرفیت مصرفی</span>
                          <span className="text-slate-700">{server.active_users_count} / {server.capacity}</span>
                        </div>
                        <div className="w-full bg-slate-200 rounded-full h-2">
                          <div 
                            className={`h-2 rounded-full ${getUsageColor(usagePercent)}`}
                            style={{ width: `${Math.min(usagePercent, 100)}%` }}
                          ></div>
                        </div>
                      </div>

                      {/* Health Status */}
                      {health && (
                        <div className="mt-4 p-3 bg-slate-50 rounded-lg">
                          <div className="flex items-center justify-between text-sm mb-2">
                            <span className="text-slate-500">وضعیت:</span>
                            <span className={`px-2 py-0.5 rounded-full text-xs ${
                              health.status === 'online' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                            }`}>
                              {health.status === 'online' ? 'آنلاین' : 'آفلاین'}
                            </span>
                          </div>
                          <div className="flex items-center justify-between text-sm">
                            <span className="text-slate-500">CPU:</span>
                            <span className="text-slate-700">{health.cpu}%</span>
                          </div>
                          <div className="flex items-center justify-between text-sm mt-1">
                            <span className="text-slate-500">RAM:</span>
                            <span className="text-slate-700">{health.ram}%</span>
                          </div>
                          {health.online_users !== undefined && (
                            <div className="flex items-center justify-between text-sm mt-1">
                              <span className="text-slate-500">آنلاین:</span>
                              <span className="text-emerald-600">{health.online_users} نفر</span>
                            </div>
                          )}
                        </div>
                      )}
                    </div>

                    {/* Actions */}
                    <div className="px-6 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <button 
                          onClick={() => checkHealth(server.id)}
                          className="flex items-center gap-1 text-sm text-slate-600 hover:text-emerald-600"
                        >
                          <Activity className="w-4 h-4" />
                          سلامت
                        </button>
                        <button 
                          onClick={() => testConnection(server.id)}
                          disabled={testingConnection === server.id}
                          className="flex items-center gap-1 text-sm text-slate-600 hover:text-blue-600 disabled:opacity-50"
                        >
                          <TestTube className={`w-4 h-4 ${testingConnection === server.id ? 'animate-pulse' : ''}`} />
                          تست
                        </button>
                      </div>
                      <div className="flex items-center gap-2">
                        <button 
                          onClick={() => openEditModal(server)}
                          className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                        >
                          <Edit className="w-4 h-4" />
                        </button>
                        <button 
                          onClick={() => handleDelete(server.id)}
                          className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </>
      )}

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b border-slate-200">
              <h2 className="text-xl font-bold text-slate-800">
                {editingServer ? 'ویرایش سرور' : 'افزودن سرور جدید'}
              </h2>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              {/* Panel Type Selection */}
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">نوع پنل</label>
                <div className="flex gap-4">
                  <label className={`flex-1 flex items-center justify-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-colors ${
                    formData.panel_type === 'marzban' ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'
                  }`}>
                    <input
                      type="radio"
                      name="panel_type"
                      value="marzban"
                      checked={formData.panel_type === 'marzban'}
                      onChange={(e) => setFormData({ ...formData, panel_type: e.target.value as 'marzban' | 'hiddify' })}
                      className="sr-only"
                    />
                    <span className={`font-medium ${formData.panel_type === 'marzban' ? 'text-blue-700' : 'text-slate-600'}`}>
                      مرزبان
                    </span>
                  </label>
                  <label className={`flex-1 flex items-center justify-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition-colors ${
                    formData.panel_type === 'hiddify' ? 'border-purple-500 bg-purple-50' : 'border-slate-200 hover:border-slate-300'
                  }`}>
                    <input
                      type="radio"
                      name="panel_type"
                      value="hiddify"
                      checked={formData.panel_type === 'hiddify'}
                      onChange={(e) => setFormData({ ...formData, panel_type: e.target.value as 'marzban' | 'hiddify' })}
                      className="sr-only"
                    />
                    <span className={`font-medium ${formData.panel_type === 'hiddify' ? 'text-purple-700' : 'text-slate-600'}`}>
                      هیدیفای
                    </span>
                  </label>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">نام سرور</label>
                  <input
                    type="text"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">پرچم</label>
                  <input
                    type="text"
                    value={formData.flag_emoji}
                    onChange={(e) => setFormData({ ...formData, flag_emoji: e.target.value })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">آدرس IP</label>
                  <input
                    type="text"
                    value={formData.ip_address}
                    onChange={(e) => setFormData({ ...formData, ip_address: e.target.value })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">دامنه API</label>
                  <input
                    type="text"
                    value={formData.api_domain}
                    onChange={(e) => setFormData({ ...formData, api_domain: e.target.value })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    required
                  />
                </div>
              </div>

              {/* Marzban Auth Fields */}
              {formData.panel_type === 'marzban' && (
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">نام کاربری ادمین</label>
                    <input
                      type="text"
                      value={formData.admin_user}
                      onChange={(e) => setFormData({ ...formData, admin_user: e.target.value })}
                      className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                      required={!editingServer}
                      placeholder={editingServer ? 'خالی بگذارید برای عدم تغییر' : ''}
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">رمز عبور ادمین</label>
                    <input
                      type="password"
                      value={formData.admin_pass}
                      onChange={(e) => setFormData({ ...formData, admin_pass: e.target.value })}
                      className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                      required={!editingServer}
                      placeholder={editingServer ? 'خالی بگذارید برای عدم تغییر' : ''}
                    />
                  </div>
                </div>
              )}

              {/* Hiddify Auth Fields */}
              {formData.panel_type === 'hiddify' && (
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">API Key</label>
                  <input
                    type="password"
                    value={formData.api_key}
                    onChange={(e) => setFormData({ ...formData, api_key: e.target.value })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 font-mono"
                    required={!editingServer}
                    placeholder={editingServer ? 'خالی بگذارید برای عدم تغییر' : 'API Key از پنل هیدیفای'}
                  />
                  <p className="text-xs text-slate-500 mt-1">
                    API Key را از تنظیمات پنل هیدیفای دریافت کنید
                  </p>
                </div>
              )}

              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">ظرفیت</label>
                  <input
                    type="number"
                    value={formData.capacity}
                    onChange={(e) => setFormData({ ...formData, capacity: parseInt(e.target.value) })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">تگ لوکیشن</label>
                  <input
                    type="text"
                    value={formData.location_tag}
                    onChange={(e) => setFormData({ ...formData, location_tag: e.target.value })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">نوع</label>
                  <select
                    value={formData.type}
                    onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                  >
                    <option value="single">تک سرور</option>
                    <option value="multi_relay">چند سرور</option>
                  </select>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="is_active"
                  checked={formData.is_active}
                  onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                  className="rounded border-slate-300"
                />
                <label htmlFor="is_active" className="text-sm text-slate-700">سرور فعال باشد</label>
              </div>

              <div className="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
                >
                  انصراف
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600"
                >
                  {editingServer ? 'ذخیره تغییرات' : 'افزودن سرور'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
