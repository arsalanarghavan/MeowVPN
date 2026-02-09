import { useEffect, useState, useRef } from 'react'
import { serversApi, aezaApi } from '../../../services/api'
import { Plus, Edit, Trash2, Activity, RefreshCw, CheckCircle, XCircle, Cpu, HardDrive, Users, Wifi, WifiOff, TestTube, ShoppingCart, Copy, MoreVertical, RotateCw, Key, BarChart3, PauseCircle, PlayCircle } from 'lucide-react'

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
  region?: 'iran' | 'foreign'
  server_category?: 'tunnel_entry' | 'tunnel_exit' | 'direct'
  is_active: boolean
  is_central?: boolean
  panel_type: 'marzban' | 'hiddify'
  provider?: string | null
  aeza_server_id?: string | null
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
  incoming_bandwidth?: number
  outgoing_bandwidth?: number
}

interface MonitoringServer extends ServerData {
  health: ServerHealth
  usage_percentage: number
  available_slots: number
  region?: string
  server_category?: string
  is_central?: boolean
  provider?: string | null
  aeza_server_id?: string | null
}

interface MonitoringData {
  servers: MonitoringServer[]
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

function formatUptime(seconds: number | null | undefined): string {
  if (seconds == null || seconds < 0) return '—'
  const d = Math.floor(seconds / 86400)
  const h = Math.floor((seconds % 86400) / 3600)
  if (d > 0) return `${d} روز و ${h} ساعت`
  if (h > 0) return `${h} ساعت`
  const m = Math.floor((seconds % 3600) / 60)
  return m > 0 ? `${m} دقیقه` : 'کمتر از یک دقیقه'
}

function formatBytes(bytes: number | null | undefined): string {
  if (bytes == null || bytes < 0) return '—'
  const gb = bytes / (1024 ** 3)
  if (gb >= 1) return `${gb.toFixed(2)} GB`
  const mb = bytes / (1024 ** 2)
  return `${mb.toFixed(2)} MB`
}

export default function ServersPage() {
  const [servers, setServers] = useState<ServerData[]>([])
  const [loading, setLoading] = useState(true)
  const [healthData, setHealthData] = useState<Record<number, ServerHealth>>({})
  const [showModal, setShowModal] = useState(false)
  const [editingServer, setEditingServer] = useState<ServerData | null>(null)
  const [activeTab, setActiveTab] = useState<'list' | 'monitoring' | 'aeza'>('list')
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
    region: 'foreign' as 'iran' | 'foreign',
    server_category: 'direct' as 'tunnel_entry' | 'tunnel_exit' | 'direct',
    is_active: true,
    is_central: false,
    panel_type: 'marzban' as 'marzban' | 'hiddify',
  })
  const [listFilters, setListFilters] = useState<{ region?: string; server_category?: string }>({})

  // AEZA tab state
  const [aezaProducts, setAezaProducts] = useState<{ id: string; title?: string; name?: string }[]>([])
  const [aezaProductsLoading, setAezaProductsLoading] = useState(false)
  const [aezaOrderId, setAezaOrderId] = useState<string | null>(null)
  const [aezaOrderStatus, setAezaOrderStatus] = useState<'idle' | 'pending' | 'ready' | 'failed'>('idle')
  const [aezaOrderDetail, setAezaOrderDetail] = useState<{
    ip_address?: string
    root_password?: string
    install_command?: string
    install_note?: string
    error_message?: string
  } | null>(null)
  const [aezaCreateLoading, setAezaCreateLoading] = useState(false)
  const [aezaForm, setAezaForm] = useState({ productId: '', term: 'month' as 'hour' | 'month' | 'year', name: '', autoProlong: false })
  const [aezaRegisterForm, setAezaRegisterForm] = useState({
    order_id: '',
    name: '',
    flag_emoji: '🌐',
    ip_address: '',
    api_domain: '',
    admin_user: 'admin',
    admin_pass: '',
    capacity: 100,
    location_tag: 'DE',
    region: 'foreign' as 'iran' | 'foreign',
    server_category: 'direct' as 'tunnel_entry' | 'tunnel_exit' | 'direct',
  })
  const [aezaRegisterLoading, setAezaRegisterLoading] = useState(false)
  const aezaPollRef = useRef<ReturnType<typeof setInterval> | null>(null)

  // Server actions menu & modals
  const [actionMenuServerId, setActionMenuServerId] = useState<number | null>(null)
  const [actionLoading, setActionLoading] = useState<string | null>(null)
  const [monitoringAutoRefresh, setMonitoringAutoRefresh] = useState(false)
  const [reinstallModalServer, setReinstallModalServer] = useState<MonitoringServer | ServerData | null>(null)
  const [reinstallForm, setReinstallForm] = useState({ os: '', recipe: '', password: '' })
  const [changePasswordModalServer, setChangePasswordModalServer] = useState<MonitoringServer | ServerData | null>(null)
  const [changePasswordValue, setChangePasswordValue] = useState('')
  const [vpsStatsModal, setVpsStatsModal] = useState<{ server: MonitoringServer | ServerData; data: any } | null>(null)
  const monitoringIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null)

  useEffect(() => {
    loadServers()
  }, [listFilters.region, listFilters.server_category])

  useEffect(() => {
    if (activeTab === 'aeza' && aezaProducts.length === 0) {
      setAezaProductsLoading(true)
      aezaApi.products()
        .then((res) => setAezaProducts(res.data?.items ?? []))
        .catch(() => setAezaProducts([]))
        .finally(() => setAezaProductsLoading(false))
    }
  }, [activeTab])

  // Auto-refresh monitoring every 60s when enabled
  useEffect(() => {
    if (!monitoringAutoRefresh || activeTab !== 'monitoring') return
    monitoringIntervalRef.current = setInterval(loadMonitoring, 60000)
    return () => {
      if (monitoringIntervalRef.current) {
        clearInterval(monitoringIntervalRef.current)
        monitoringIntervalRef.current = null
      }
    }
  }, [monitoringAutoRefresh, activeTab])

  useEffect(() => {
    if (!aezaOrderId) return
    const poll = () => {
      aezaApi.getOrder(aezaOrderId).then((res) => {
        const st = res.data?.status
        if (st === 'ready') {
          setAezaOrderStatus('ready')
          setAezaOrderDetail({
            ip_address: res.data.ip_address,
            root_password: res.data.root_password,
            install_command: res.data.install_command,
            install_note: res.data.install_note,
          })
          setAezaRegisterForm((prev) => ({
            ...prev,
            order_id: aezaOrderId!,
            ip_address: res.data.ip_address || prev.ip_address,
            api_domain: res.data.ip_address || prev.api_domain,
          }))
          if (aezaPollRef.current) {
            clearInterval(aezaPollRef.current)
            aezaPollRef.current = null
          }
        } else if (st === 'failed') {
          setAezaOrderStatus('failed')
          setAezaOrderDetail({ error_message: res.data?.error_message || 'خطا' })
          if (aezaPollRef.current) {
            clearInterval(aezaPollRef.current)
            aezaPollRef.current = null
          }
        }
      }).catch(() => {})
    }
    poll()
    aezaPollRef.current = setInterval(poll, 5000)
    return () => {
      if (aezaPollRef.current) clearInterval(aezaPollRef.current)
    }
  }, [aezaOrderId])

  const loadServers = async () => {
    setLoading(true)
    try {
      const params: { region?: string; server_category?: string } = {}
      if (listFilters.region) params.region = listFilters.region
      if (listFilters.server_category) params.server_category = listFilters.server_category
      const response = await serversApi.list(params)
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
    setActionMenuServerId(null)
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

  const handleRestartPanel = async (serverId: number) => {
    setActionMenuServerId(null)
    if (!confirm('آیا از ریستارت پنل این سرور اطمینان دارید؟')) return
    setActionLoading('restart-panel')
    try {
      await serversApi.restartPanel(serverId)
      alert('✅ درخواست ریستارت پنل ارسال شد.')
      if (activeTab === 'monitoring') loadMonitoring()
    } catch (error: any) {
      alert(error.response?.data?.message || error.response?.status === 501 ? 'این پنل از ریستارت پشتیبانی نمی‌کند.' : 'خطا در ریستارت پنل')
    } finally {
      setActionLoading(null)
    }
  }

  const handleReboot = async (serverId: number) => {
    setActionMenuServerId(null)
    if (!confirm('آیا از ریبوت VPS اطمینان دارید؟')) return
    setActionLoading('reboot')
    try {
      await serversApi.reboot(serverId)
      alert('✅ درخواست ریبوت ارسال شد.')
      if (activeTab === 'monitoring') loadMonitoring()
    } catch (error: any) {
      alert(error.response?.data?.message || 'خطا در ریبوت')
    } finally {
      setActionLoading(null)
    }
  }

  const handleSuspend = async (serverId: number) => {
    setActionMenuServerId(null)
    if (!confirm('آیا از تعلیق VPS اطمینان دارید؟')) return
    setActionLoading('suspend')
    try {
      await serversApi.suspend(serverId)
      alert('✅ درخواست تعلیق VPS ارسال شد.')
      if (activeTab === 'monitoring') loadMonitoring()
    } catch (error: any) {
      alert(error.response?.data?.message || 'خطا در تعلیق')
    } finally {
      setActionLoading(null)
    }
  }

  const handleResume = async (serverId: number) => {
    setActionMenuServerId(null)
    if (!confirm('آیا از ازسرگیری VPS اطمینان دارید؟')) return
    setActionLoading('resume')
    try {
      await serversApi.resume(serverId)
      alert('✅ درخواست ازسرگیری VPS ارسال شد.')
      if (activeTab === 'monitoring') loadMonitoring()
    } catch (error: any) {
      alert(error.response?.data?.message || 'خطا در ازسرگیری')
    } finally {
      setActionLoading(null)
    }
  }

  const handleReinstallSubmit = async () => {
    if (!reinstallModalServer) return
    setActionLoading('reinstall')
    try {
      await serversApi.reinstall(reinstallModalServer.id, {
        ...(reinstallForm.os && { os: reinstallForm.os }),
        ...(reinstallForm.recipe && { recipe: reinstallForm.recipe }),
        ...(reinstallForm.password && { password: reinstallForm.password }),
      })
      alert('درخواست ری‌اینستال ارسال شد. توجه: سرویس فعلی (مثلاً مرزبان) ممکن است از بین برود.')
      setReinstallModalServer(null)
      setReinstallForm({ os: '', recipe: '', password: '' })
      if (activeTab === 'monitoring') loadMonitoring()
    } catch (error: any) {
      alert(error.response?.data?.message || 'خطا در ری‌اینستال')
    } finally {
      setActionLoading(null)
    }
  }

  const handleChangeRootPasswordSubmit = async () => {
    if (!changePasswordModalServer || !changePasswordValue.trim()) {
      alert('رمز عبور را وارد کنید.')
      return
    }
    setActionLoading('change-password')
    try {
      await serversApi.changeRootPassword(changePasswordModalServer.id, changePasswordValue.trim())
      alert('رمز root با موفقیت تغییر کرد.')
      setChangePasswordModalServer(null)
      setChangePasswordValue('')
    } catch (error: any) {
      alert(error.response?.data?.message || 'خطا در تغییر رمز')
    } finally {
      setActionLoading(null)
    }
  }

  const openVpsStats = async (server: MonitoringServer | ServerData) => {
    setActionMenuServerId(null)
    try {
      const res = await serversApi.vpsStats(server.id)
      setVpsStatsModal({ server, data: res.data })
    } catch (error: any) {
      alert(error.response?.data?.message || 'خطا در دریافت آمار VPS')
    }
  }

  const isAezaServer = (s: { provider?: string | null; aeza_server_id?: string | null }) =>
    s.provider === 'aeza' && s.aeza_server_id

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
      flag_emoji: server.flag_emoji || '🇩🇪',
      ip_address: server.ip_address,
      api_domain: server.api_domain,
      admin_user: '',
      admin_pass: '',
      api_key: '',
      capacity: server.capacity,
      type: server.type,
      location_tag: server.location_tag,
      region: server.region || 'foreign',
      server_category: server.server_category || 'direct',
      is_active: server.is_active,
      is_central: server.is_central ?? false,
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
      region: 'foreign',
      server_category: 'direct',
      is_active: true,
      is_central: false,
      panel_type: 'marzban',
    })
  }

  const getRegionLabel = (r: string) => (r === 'iran' ? 'ایران' : 'خارج')
  const getCategoryLabel = (c: string) =>
    c === 'tunnel_entry' ? 'ورودی تانل' : c === 'tunnel_exit' ? 'خروجی تانل' : 'مستقیم'

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
            <button
              onClick={() => setActiveTab('aeza')}
              className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                activeTab === 'aeza' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-600 hover:text-slate-800'
              }`}
            >
              خرید از AEZA
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

          {/* Refresh + Auto-refresh */}
          <div className="flex items-center justify-end gap-4">
            <label className="flex items-center gap-2 text-sm text-slate-600">
              <input
                type="checkbox"
                checked={monitoringAutoRefresh}
                onChange={(e) => setMonitoringAutoRefresh(e.target.checked)}
                className="rounded border-slate-300"
              />
              بروزرسانی خودکار هر ۶۰ ثانیه
            </label>
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
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">منطقه / دسته</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">وضعیت</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">پنل</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">آپتایم</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">نسخه</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">پهنای‌باند ورودی</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">پهنای‌باند خروجی</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">CPU</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">RAM</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">کاربران</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">آنلاین</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">ظرفیت</th>
                      <th className="px-4 py-3 text-center text-sm font-medium text-slate-600">عملیات</th>
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
                              <div className="text-xs text-slate-500">{server.location_tag} · {server.api_domain}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-center">
                          <span className="text-xs text-slate-600">
                            {server.region === 'iran' ? 'ایران' : 'خارج'}
                            <br />
                            {server.server_category === 'tunnel_entry' ? 'ورودی تانل' : server.server_category === 'tunnel_exit' ? 'خروجی تانل' : 'مستقیم'}
                            {server.is_central && (
                              <>
                                <br />
                                <span className="inline-block mt-0.5 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-xs">مرکزی</span>
                              </>
                            )}
                          </span>
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
                        <td className="px-4 py-3 text-center text-sm text-slate-700">
                          {formatUptime(server.health.uptime)}
                        </td>
                        <td className="px-4 py-3 text-center text-sm font-mono text-slate-700">
                          {server.health.version ?? '—'}
                        </td>
                        <td className="px-4 py-3 text-center text-sm text-slate-700">
                          {formatBytes(server.health.incoming_bandwidth)}
                        </td>
                        <td className="px-4 py-3 text-center text-sm text-slate-700">
                          {formatBytes(server.health.outgoing_bandwidth)}
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
                        <td className="px-4 py-3 text-center">
                          <div className="relative inline-block">
                            <button
                              type="button"
                              onClick={() => setActionMenuServerId(actionMenuServerId === server.id ? null : server.id)}
                              className="p-2 rounded-lg hover:bg-slate-100 text-slate-600"
                            >
                              <MoreVertical className="w-4 h-4" />
                            </button>
                            {actionMenuServerId === server.id && (
                              <>
                                <div className="fixed inset-0 z-10" onClick={() => setActionMenuServerId(null)} />
                                <div className="absolute left-0 top-full mt-1 py-1 bg-white border border-slate-200 rounded-lg shadow-lg z-20 min-w-[180px] text-right">
                                  <button
                                    type="button"
                                    onClick={() => testConnection(server.id)}
                                    disabled={testingConnection === server.id}
                                    className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                  >
                                    <TestTube className="w-4 h-4" /> تست اتصال
                                  </button>
                                  {server.panel_type === 'marzban' && (
                                    <button
                                      type="button"
                                      onClick={() => handleRestartPanel(server.id)}
                                      disabled={actionLoading === 'restart-panel'}
                                      className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                    >
                                      <RotateCw className="w-4 h-4" /> ریستارت پنل
                                    </button>
                                  )}
                                  {isAezaServer(server) && (
                                    <>
                                      <button
                                        type="button"
                                        onClick={() => handleReboot(server.id)}
                                        disabled={actionLoading === 'reboot'}
                                        className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                      >
                                        <RotateCw className="w-4 h-4" /> ریبوت VPS
                                      </button>
                                      <button
                                        type="button"
                                        onClick={() => handleSuspend(server.id)}
                                        disabled={actionLoading === 'suspend'}
                                        className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                      >
                                        <PauseCircle className="w-4 h-4" /> تعلیق VPS
                                      </button>
                                      <button
                                        type="button"
                                        onClick={() => handleResume(server.id)}
                                        disabled={actionLoading === 'resume'}
                                        className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                      >
                                        <PlayCircle className="w-4 h-4" /> ازسرگیری VPS
                                      </button>
                                      <button
                                        type="button"
                                        onClick={() => { setReinstallModalServer(server); setActionMenuServerId(null) }}
                                        className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                      >
                                        <Activity className="w-4 h-4" /> ری‌اینستال
                                      </button>
                                      <button
                                        type="button"
                                        onClick={() => { setChangePasswordModalServer(server); setActionMenuServerId(null) }}
                                        className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                      >
                                        <Key className="w-4 h-4" /> تغییر رمز root
                                      </button>
                                      <button
                                        type="button"
                                        onClick={() => openVpsStats(server)}
                                        className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                      >
                                        <BarChart3 className="w-4 h-4" /> آمار VPS
                                      </button>
                                    </>
                                  )}
                                </div>
                              </>
                            )}
                          </div>
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

      {/* AEZA Buy View */}
      {activeTab === 'aeza' && (
        <div className="space-y-6">
          <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h2 className="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
              <ShoppingCart className="w-5 h-5" />
              خرید سرور از AEZA
            </h2>
            {aezaOrderStatus === 'idle' && (
              <form
                onSubmit={async (e) => {
                  e.preventDefault()
                  if (!aezaForm.productId || !aezaForm.name) {
                    alert('محصول و نام سرور را انتخاب کنید')
                    return
                  }
                  setAezaCreateLoading(true)
                  try {
                    const res = await aezaApi.createOrder({
                      productId: aezaForm.productId,
                      term: aezaForm.term,
                      name: aezaForm.name,
                      autoProlong: aezaForm.autoProlong,
                    })
                    setAezaOrderId(res.data.order_id)
                    setAezaOrderStatus('pending')
                  } catch (err: any) {
                    alert(err.response?.data?.error || 'خطا در ثبت سفارش')
                  } finally {
                    setAezaCreateLoading(false)
                  }
                }}
                className="space-y-4 max-w-md"
              >
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">محصول</label>
                  <select
                    value={aezaForm.productId}
                    onChange={(e) => setAezaForm((f) => ({ ...f, productId: e.target.value }))}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg"
                    disabled={aezaProductsLoading}
                  >
                    <option value="">انتخاب محصول</option>
                    {(aezaProducts as { id: string; title?: string; name?: string }[]).map((p) => (
                      <option key={p.id} value={p.id}>{p.title || p.name || p.id}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">مدت</label>
                  <select
                    value={aezaForm.term}
                    onChange={(e) => setAezaForm((f) => ({ ...f, term: e.target.value as 'hour' | 'month' | 'year' }))}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg"
                  >
                    <option value="hour">ساعتی</option>
                    <option value="month">ماهانه</option>
                    <option value="year">سالانه</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">نام سرور</label>
                  <input
                    type="text"
                    value={aezaForm.name}
                    onChange={(e) => setAezaForm((f) => ({ ...f, name: e.target.value }))}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg"
                    placeholder="مثال: vpn-node-1"
                  />
                </div>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={aezaForm.autoProlong}
                    onChange={(e) => setAezaForm((f) => ({ ...f, autoProlong: e.target.checked }))}
                  />
                  <span className="text-sm text-slate-600">تمدید خودکار</span>
                </label>
                <button
                  type="submit"
                  disabled={aezaCreateLoading || !aezaForm.productId || !aezaForm.name}
                  className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-50"
                >
                  {aezaCreateLoading ? 'در حال ثبت...' : 'ثبت سفارش'}
                </button>
              </form>
            )}
            {aezaOrderStatus === 'pending' && (
              <div className="space-y-2">
                <p className="text-slate-600">سرور در حال آماده‌سازی است. چند دقیقه صبر کنید...</p>
                <p className="text-sm text-slate-500">شناسه سفارش: {aezaOrderId}</p>
              </div>
            )}
            {aezaOrderStatus === 'ready' && aezaOrderDetail && (
              <div className="space-y-4">
                <p className="text-emerald-600 font-medium">سرور آماده است.</p>
                <div className="grid gap-2 text-sm">
                  <div><span className="text-slate-500">IP:</span> <code className="bg-slate-100 px-2 py-1 rounded">{aezaOrderDetail.ip_address}</code>
                    <button type="button" onClick={() => navigator.clipboard.writeText(aezaOrderDetail.ip_address || '')} className="mr-2 text-blue-600"><Copy className="w-4 h-4 inline" /></button>
                  </div>
                  {aezaOrderDetail.root_password && (
                    <div><span className="text-slate-500">رمز root:</span> <code className="bg-slate-100 px-2 py-1 rounded">{aezaOrderDetail.root_password}</code>
                      <button type="button" onClick={() => navigator.clipboard.writeText(aezaOrderDetail.root_password || '')} className="mr-2 text-blue-600"><Copy className="w-4 h-4 inline" /></button>
                    </div>
                  )}
                </div>
                <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                  <p className="text-sm font-medium text-amber-800 mb-2">دستور نصب مرزبان (در SSH سرور اجرا کنید):</p>
                  <pre className="text-xs bg-white p-3 rounded overflow-x-auto">{aezaOrderDetail.install_command}</pre>
                  <button type="button" onClick={() => navigator.clipboard.writeText(aezaOrderDetail.install_command || '')} className="mt-2 text-sm text-blue-600">کپی</button>
                </div>
                {aezaOrderDetail.install_note && <p className="text-sm text-slate-600">{aezaOrderDetail.install_note}</p>}
                <form
                  onSubmit={async (e) => {
                    e.preventDefault()
                    setAezaRegisterLoading(true)
                    try {
                      await aezaApi.registerServer(aezaRegisterForm)
                      alert('سرور با موفقیت به پنل اضافه شد')
                      setAezaOrderId(null)
                      setAezaOrderStatus('idle')
                      setAezaOrderDetail(null)
                      setAezaRegisterForm({ order_id: '', name: '', flag_emoji: '🌐', ip_address: '', api_domain: '', admin_user: 'admin', admin_pass: '', capacity: 100, location_tag: 'DE', region: 'foreign', server_category: 'direct' })
                      loadServers()
                      setActiveTab('list')
                    } catch (err: any) {
                      alert(err.response?.data?.error || 'خطا در ثبت سرور')
                    } finally {
                      setAezaRegisterLoading(false)
                    }
                  }}
                  className="border-t pt-4 mt-4 space-y-3 max-w-md"
                >
                  <h3 className="font-medium text-slate-800">ثبت سرور در پنل (پس از نصب مرزبان)</h3>
                  <input type="hidden" value={aezaRegisterForm.order_id} readOnly />
                  <input type="text" placeholder="نام سرور" value={aezaRegisterForm.name} onChange={(e) => setAezaRegisterForm((f) => ({ ...f, name: e.target.value }))} className="w-full px-3 py-2 border rounded-lg" required />
                  <input type="text" placeholder="کاربر ادمین مرزبان" value={aezaRegisterForm.admin_user} onChange={(e) => setAezaRegisterForm((f) => ({ ...f, admin_user: e.target.value }))} className="w-full px-3 py-2 border rounded-lg" required />
                  <input type="password" placeholder="رمز ادمین مرزبان" value={aezaRegisterForm.admin_pass} onChange={(e) => setAezaRegisterForm((f) => ({ ...f, admin_pass: e.target.value }))} className="w-full px-3 py-2 border rounded-lg" required />
                  <input type="number" placeholder="ظرفیت" value={aezaRegisterForm.capacity} onChange={(e) => setAezaRegisterForm((f) => ({ ...f, capacity: +e.target.value }))} className="w-full px-3 py-2 border rounded-lg" min={1} />
                  <div className="flex gap-2">
                    <select value={aezaRegisterForm.region} onChange={(e) => setAezaRegisterForm((f) => ({ ...f, region: e.target.value as 'iran' | 'foreign' }))} className="px-3 py-2 border rounded-lg">
                      <option value="iran">ایران</option>
                      <option value="foreign">خارج</option>
                    </select>
                    <select value={aezaRegisterForm.server_category} onChange={(e) => setAezaRegisterForm((f) => ({ ...f, server_category: e.target.value as 'tunnel_entry' | 'tunnel_exit' | 'direct' }))} className="px-3 py-2 border rounded-lg">
                      <option value="direct">مستقیم</option>
                      <option value="tunnel_exit">خروجی تانل</option>
                      <option value="tunnel_entry">ورودی تانل</option>
                    </select>
                    <input type="text" placeholder="location_tag" value={aezaRegisterForm.location_tag} onChange={(e) => setAezaRegisterForm((f) => ({ ...f, location_tag: e.target.value }))} className="w-24 px-3 py-2 border rounded-lg" />
                  </div>
                  <button type="submit" disabled={aezaRegisterLoading} className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-50">
                    {aezaRegisterLoading ? 'در حال ثبت...' : 'ثبت در پنل'}
                  </button>
                </form>
              </div>
            )}
            {aezaOrderStatus === 'failed' && aezaOrderDetail?.error_message && (
              <div className="text-red-600">
                <p>خطا: {aezaOrderDetail.error_message}</p>
                <button type="button" onClick={() => { setAezaOrderStatus('idle'); setAezaOrderId(null); setAezaOrderDetail(null) }} className="mt-2 text-sm text-slate-600 underline">بازگشت</button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Server List View */}
      {activeTab === 'list' && (
        <>
          {/* Filters */}
          <div className="flex flex-wrap items-center gap-3">
            <span className="text-sm text-slate-600">فیلتر:</span>
            <select
              value={listFilters.region ?? ''}
              onChange={(e) => setListFilters(f => ({ ...f, region: e.target.value || undefined }))}
              className="px-3 py-2 border border-slate-300 rounded-lg text-sm"
            >
              <option value="">همه مناطق</option>
              <option value="iran">ایران</option>
              <option value="foreign">خارج</option>
            </select>
            <select
              value={listFilters.server_category ?? ''}
              onChange={(e) => setListFilters(f => ({ ...f, server_category: e.target.value || undefined }))}
              className="px-3 py-2 border border-slate-300 rounded-lg text-sm"
            >
              <option value="">همه دسته‌ها</option>
              <option value="tunnel_entry">ورودی تانل</option>
              <option value="tunnel_exit">خروجی تانل</option>
              <option value="direct">مستقیم</option>
            </select>
          </div>

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
                            <p className="text-sm text-slate-500">
                              {server.flag_emoji} {server.location_tag}
                              {(server.region || server.server_category) && (
                                <span className="mr-1 text-slate-400">
                                  · {getRegionLabel(server.region || 'foreign')} · {getCategoryLabel(server.server_category || 'direct')}
                                </span>
                              )}
                            </p>
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
                          {server.is_central && (
                            <span className="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">مرکزی</span>
                          )}
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
                        <div className="relative inline-block">
                          <button
                            type="button"
                            onClick={() => setActionMenuServerId(actionMenuServerId === server.id ? null : server.id)}
                            className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                          >
                            <MoreVertical className="w-4 h-4" />
                          </button>
                          {actionMenuServerId === server.id && (
                            <>
                              <div className="fixed inset-0 z-10" onClick={() => setActionMenuServerId(null)} />
                              <div className="absolute right-0 bottom-full mb-1 py-1 bg-white border border-slate-200 rounded-lg shadow-lg z-20 min-w-[180px] text-right">
                                {server.panel_type === 'marzban' && (
                                  <button
                                    type="button"
                                    onClick={() => handleRestartPanel(server.id)}
                                    disabled={actionLoading === 'restart-panel'}
                                    className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                  >
                                    <RotateCw className="w-4 h-4" /> ریستارت پنل
                                  </button>
                                )}
                                {isAezaServer(server) && (
                                  <>
                                    <button
                                      type="button"
                                      onClick={() => handleReboot(server.id)}
                                      disabled={actionLoading === 'reboot'}
                                      className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                    >
                                      <RotateCw className="w-4 h-4" /> ریبوت VPS
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => handleSuspend(server.id)}
                                      disabled={actionLoading === 'suspend'}
                                      className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                    >
                                      <PauseCircle className="w-4 h-4" /> تعلیق VPS
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => handleResume(server.id)}
                                      disabled={actionLoading === 'resume'}
                                      className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                    >
                                      <PlayCircle className="w-4 h-4" /> ازسرگیری VPS
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => { setReinstallModalServer(server); setActionMenuServerId(null) }}
                                      className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                    >
                                      <Activity className="w-4 h-4" /> ری‌اینستال
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => { setChangePasswordModalServer(server); setActionMenuServerId(null) }}
                                      className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                    >
                                      <Key className="w-4 h-4" /> تغییر رمز root
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => openVpsStats(server)}
                                      className="w-full px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2"
                                    >
                                      <BarChart3 className="w-4 h-4" /> آمار VPS
                                    </button>
                                  </>
                                )}
                              </div>
                            </>
                          )}
                        </div>
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
                    required
                    placeholder="🇩🇪"
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

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">منطقه</label>
                  <select
                    value={formData.region}
                    onChange={(e) => {
                      const region = e.target.value as 'iran' | 'foreign'
                      const server_category = region === 'iran' ? 'tunnel_entry' : 'direct'
                      setFormData({ ...formData, region, server_category })
                    }}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    required
                  >
                    <option value="iran">ایران</option>
                    <option value="foreign">خارج</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">دسته‌بندی سرور</label>
                  <select
                    value={formData.server_category}
                    onChange={(e) => setFormData({ ...formData, server_category: e.target.value as 'tunnel_entry' | 'tunnel_exit' | 'direct' })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    required
                  >
                    {formData.region === 'iran' && <option value="tunnel_entry">ورودی تانل</option>}
                    {formData.region === 'foreign' && (
                      <>
                        <option value="tunnel_exit">خروجی تانل</option>
                        <option value="direct">مستقیم</option>
                      </>
                    )}
                  </select>
                </div>
              </div>

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
                    placeholder="DE, TR, IR"
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

              <div className="flex flex-wrap items-center gap-6">
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
                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="is_central"
                    checked={formData.is_central}
                    onChange={(e) => setFormData({ ...formData, is_central: e.target.checked })}
                    className="rounded border-slate-300"
                  />
                  <label htmlFor="is_central" className="text-sm text-slate-700">نود مرکزی</label>
                </div>
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

      {/* Reinstall Modal (AEZA) */}
      {reinstallModalServer && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
            <h2 className="text-lg font-bold text-slate-800 mb-4">ری‌اینستال VPS</h2>
            <p className="text-sm text-amber-700 mb-4">توجه: ری‌اینستال معمولاً سیستم‌عامل را عوض می‌کند و سرویس فعلی (مثلاً مرزبان) از بین می‌رود.</p>
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">OS (اختیاری)</label>
                <input
                  type="text"
                  value={reinstallForm.os}
                  onChange={(e) => setReinstallForm((f) => ({ ...f, os: e.target.value }))}
                  className="w-full px-3 py-2 border border-slate-300 rounded-lg"
                  placeholder="مثال: ubuntu-22.04"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Recipe (اختیاری)</label>
                <input
                  type="text"
                  value={reinstallForm.recipe}
                  onChange={(e) => setReinstallForm((f) => ({ ...f, recipe: e.target.value }))}
                  className="w-full px-3 py-2 border border-slate-300 rounded-lg"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">رمز root جدید (اختیاری)</label>
                <input
                  type="password"
                  value={reinstallForm.password}
                  onChange={(e) => setReinstallForm((f) => ({ ...f, password: e.target.value }))}
                  className="w-full px-3 py-2 border border-slate-300 rounded-lg"
                />
              </div>
            </div>
            <div className="flex justify-end gap-2 mt-6">
              <button
                type="button"
                onClick={() => { setReinstallModalServer(null); setReinstallForm({ os: '', recipe: '', password: '' }) }}
                className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
              >
                انصراف
              </button>
              <button
                type="button"
                onClick={handleReinstallSubmit}
                disabled={actionLoading === 'reinstall'}
                className="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 disabled:opacity-50"
              >
                {actionLoading === 'reinstall' ? 'در حال ارسال...' : 'ری‌اینستال'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Change root password Modal (AEZA) */}
      {changePasswordModalServer && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
            <h2 className="text-lg font-bold text-slate-800 mb-4">تغییر رمز root</h2>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">رمز عبور جدید</label>
              <input
                type="password"
                value={changePasswordValue}
                onChange={(e) => setChangePasswordValue(e.target.value)}
                className="w-full px-3 py-2 border border-slate-300 rounded-lg"
                placeholder="رمز عبور جدید"
              />
            </div>
            <div className="flex justify-end gap-2 mt-6">
              <button
                type="button"
                onClick={() => { setChangePasswordModalServer(null); setChangePasswordValue('') }}
                className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
              >
                انصراف
              </button>
              <button
                type="button"
                onClick={handleChangeRootPasswordSubmit}
                disabled={actionLoading === 'change-password' || !changePasswordValue.trim()}
                className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-50"
              >
                {actionLoading === 'change-password' ? 'در حال ارسال...' : 'تغییر رمز'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* VPS Stats Modal (AEZA getCharts) */}
      {vpsStatsModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={() => setVpsStatsModal(null)}>
          <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden mx-4 flex flex-col" onClick={(e) => e.stopPropagation()}>
            <div className="p-4 border-b border-slate-200 flex items-center justify-between">
              <h2 className="text-lg font-bold text-slate-800">آمار VPS — {vpsStatsModal.server.name}</h2>
              <button type="button" onClick={() => setVpsStatsModal(null)} className="text-slate-500 hover:text-slate-700">×</button>
            </div>
            <div className="p-4 overflow-y-auto flex-1">
              {vpsStatsModal.data && typeof vpsStatsModal.data === 'object' ? (
                <pre className="text-xs bg-slate-50 p-4 rounded-lg overflow-x-auto whitespace-pre-wrap">
                  {JSON.stringify(vpsStatsModal.data, null, 2)}
                </pre>
              ) : (
                <p className="text-slate-600">داده‌ای موجود نیست.</p>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
