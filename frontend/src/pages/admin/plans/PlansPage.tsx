import { useEffect, useState } from 'react'
import { plansApi } from '../../../services/api'
import { Package, Plus, Edit, Trash2, CheckCircle, XCircle, Smartphone, Users } from 'lucide-react'

interface Plan {
  id: number
  name: string
  price_base: number
  duration_days: number
  traffic_bytes: number
  max_concurrent_users: number
  max_devices: number
  description: string | null
  is_active: boolean
  created_at: string
}

export default function PlansPage() {
  const [plans, setPlans] = useState<Plan[]>([])
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)
  const [editingPlan, setEditingPlan] = useState<Plan | null>(null)
  const [formData, setFormData] = useState({
    name: '',
    price_base: 0,
    duration_days: 30,
    traffic_bytes: 0,
    max_concurrent_users: 1,
    max_devices: 1,
    description: '',
    is_active: true,
  })

  useEffect(() => {
    loadPlans()
  }, [])

  const loadPlans = async () => {
    setLoading(true)
    try {
      const response = await plansApi.list()
      setPlans(response.data)
    } catch (error) {
      console.error('Failed to load plans:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      const data = {
        ...formData,
        traffic_bytes: formData.traffic_bytes * 1024 * 1024 * 1024, // Convert GB to bytes
      }
      
      if (editingPlan) {
        await plansApi.update(editingPlan.id, data)
      } else {
        await plansApi.create(data)
      }
      setShowModal(false)
      setEditingPlan(null)
      resetForm()
      loadPlans()
    } catch (error) {
      console.error('Failed to save plan:', error)
      alert('خطا در ذخیره پلن')
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('آیا از حذف این پلن اطمینان دارید؟')) return
    
    try {
      await plansApi.delete(id)
      loadPlans()
    } catch (error) {
      console.error('Failed to delete plan:', error)
      alert('خطا در حذف پلن')
    }
  }

  const openEditModal = (plan: Plan) => {
    setEditingPlan(plan)
    setFormData({
      name: plan.name,
      price_base: plan.price_base,
      duration_days: plan.duration_days,
      traffic_bytes: Math.round(plan.traffic_bytes / (1024 * 1024 * 1024)), // Convert bytes to GB
      max_concurrent_users: plan.max_concurrent_users,
      max_devices: plan.max_devices || 1,
      description: plan.description || '',
      is_active: plan.is_active,
    })
    setShowModal(true)
  }

  const resetForm = () => {
    setFormData({
      name: '',
      price_base: 0,
      duration_days: 30,
      traffic_bytes: 0,
      max_concurrent_users: 1,
      max_devices: 1,
      description: '',
      is_active: true,
    })
  }

  const formatTraffic = (bytes: number) => {
    if (bytes === 0) return 'نامحدود'
    const gb = bytes / (1024 * 1024 * 1024)
    return `${gb} گیگابایت`
  }

  const formatPrice = (price: number) => {
    return price.toLocaleString() + ' تومان'
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">مدیریت پلن‌ها</h1>
        <button 
          onClick={() => { resetForm(); setEditingPlan(null); setShowModal(true) }}
          className="flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition-colors"
        >
          <Plus className="w-5 h-5" />
          افزودن پلن
        </button>
      </div>

      {/* Plans Grid */}
      {loading ? (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500"></div>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          {plans.map((plan) => (
            <div 
              key={plan.id} 
              className={`bg-white rounded-xl shadow-sm border-2 overflow-hidden ${
                plan.is_active ? 'border-emerald-200' : 'border-slate-200 opacity-60'
              }`}
            >
              <div className="p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className="p-3 bg-emerald-100 rounded-xl">
                    <Package className="w-6 h-6 text-emerald-600" />
                  </div>
                  <div className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs ${
                    plan.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'
                  }`}>
                    {plan.is_active ? (
                      <><CheckCircle className="w-3 h-3" /> فعال</>
                    ) : (
                      <><XCircle className="w-3 h-3" /> غیرفعال</>
                    )}
                  </div>
                </div>

                <h3 className="text-xl font-bold text-slate-800 mb-2">{plan.name}</h3>
                
                <div className="text-3xl font-bold text-emerald-600 mb-4">
                  {formatPrice(plan.price_base)}
                </div>

                <div className="space-y-2 text-sm">
                  <div className="flex items-center justify-between">
                    <span className="text-slate-500">مدت:</span>
                    <span className="text-slate-700">{plan.duration_days} روز</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-slate-500">حجم:</span>
                    <span className="text-slate-700">{formatTraffic(plan.traffic_bytes)}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-slate-500 flex items-center gap-1">
                      <Users className="w-3 h-3" /> کاربر همزمان:
                    </span>
                    <span className="text-slate-700">{plan.max_concurrent_users}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-slate-500 flex items-center gap-1">
                      <Smartphone className="w-3 h-3" /> تعداد دستگاه:
                    </span>
                    <span className="text-slate-700">{plan.max_devices || 1}</span>
                  </div>
                </div>

                {plan.description && (
                  <p className="mt-4 text-sm text-slate-500 border-t border-slate-100 pt-4">
                    {plan.description}
                  </p>
                )}
              </div>

              <div className="px-6 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
                <button 
                  onClick={() => openEditModal(plan)}
                  className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                  title="ویرایش"
                >
                  <Edit className="w-4 h-4" />
                </button>
                <button 
                  onClick={() => handleDelete(plan.id)}
                  className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                  title="حذف"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b border-slate-200">
              <h2 className="text-xl font-bold text-slate-800">
                {editingPlan ? 'ویرایش پلن' : 'افزودن پلن جدید'}
              </h2>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">نام پلن</label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                  placeholder="مثال: پلن ماهانه"
                  required
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">قیمت (تومان)</label>
                  <input
                    type="number"
                    value={formData.price_base}
                    onChange={(e) => setFormData({ ...formData, price_base: parseInt(e.target.value) })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">مدت (روز)</label>
                  <input
                    type="number"
                    value={formData.duration_days}
                    onChange={(e) => setFormData({ ...formData, duration_days: parseInt(e.target.value) })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    required
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">حجم (گیگابایت)</label>
                  <input
                    type="number"
                    value={formData.traffic_bytes}
                    onChange={(e) => setFormData({ ...formData, traffic_bytes: parseInt(e.target.value) })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    placeholder="0 = نامحدود"
                  />
                  <p className="text-xs text-slate-500 mt-1">برای حجم نامحدود 0 وارد کنید</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">کاربر همزمان (ضریب قیمت)</label>
                  <input
                    type="number"
                    value={formData.max_concurrent_users}
                    onChange={(e) => setFormData({ ...formData, max_concurrent_users: parseInt(e.target.value) })}
                    className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    min={1}
                    required
                  />
                  <p className="text-xs text-slate-500 mt-1">تأثیر بر قیمت نهایی دارد</p>
                </div>
              </div>
              
              {/* Max Devices Field */}
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">
                  <span className="flex items-center gap-2">
                    <Smartphone className="w-4 h-4" />
                    حداکثر تعداد دستگاه
                  </span>
                </label>
                <input
                  type="number"
                  value={formData.max_devices}
                  onChange={(e) => setFormData({ ...formData, max_devices: parseInt(e.target.value) })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                  min={1}
                  max={10}
                  required
                />
                <p className="text-xs text-slate-500 mt-1">
                  تعداد دستگاه‌هایی که می‌توانند همزمان از این اشتراک استفاده کنند (برای پنل هیدیفای)
                </p>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">توضیحات</label>
                <textarea
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                  rows={3}
                  placeholder="توضیحات اختیاری..."
                />
              </div>
              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="is_active"
                  checked={formData.is_active}
                  onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                  className="rounded border-slate-300"
                />
                <label htmlFor="is_active" className="text-sm text-slate-700">پلن فعال باشد</label>
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
                  {editingPlan ? 'ذخیره تغییرات' : 'افزودن پلن'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
