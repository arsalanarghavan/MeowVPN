import { useState, useEffect } from 'react'
import { resellersApi } from '../../../services/api'
import { Users, Plus, Eye, Edit, Trash2, CreditCard, Receipt } from 'lucide-react'

interface Reseller {
  id: number
  username: string
  email: string
  credit_limit: number
  current_debt: number
  created_at: string
  reseller_profile?: {
    brand_name: string
    contact_number: string
    is_active: boolean
  }
}

export default function ResellersPage() {
  const [resellers, setResellers] = useState<Reseller[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [selectedReseller, setSelectedReseller] = useState<Reseller | null>(null)
  const [formData, setFormData] = useState({
    username: '',
    email: '',
    password: '',
    credit_limit: 0,
    brand_name: '',
    contact_number: '',
  })

  useEffect(() => {
    loadResellers()
  }, [])

  const loadResellers = async () => {
    try {
      const response = await resellersApi.list()
      setResellers(response.data)
    } catch (error) {
      console.error('Failed to load resellers:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleCreateReseller = async (e: React.FormEvent) => {
    e.preventDefault()
    try {
      await resellersApi.create(formData)
      setShowCreateModal(false)
      setFormData({
        username: '',
        email: '',
        password: '',
        credit_limit: 0,
        brand_name: '',
        contact_number: '',
      })
      loadResellers()
    } catch (error: any) {
      alert(error.response?.data?.message || 'خطا در ایجاد نماینده')
    }
  }

  const handleUpdateReseller = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedReseller) return
    try {
      await resellersApi.update(selectedReseller.id, {
        credit_limit: formData.credit_limit,
        brand_name: formData.brand_name,
        contact_number: formData.contact_number,
      })
      setSelectedReseller(null)
      loadResellers()
    } catch (error: any) {
      alert(error.response?.data?.message || 'خطا در بروزرسانی')
    }
  }

  const formatCurrency = (amount: number) => {
    return amount.toLocaleString() + ' ﷼'
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500"></div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">مدیریت نمایندگان</h1>
        <button
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600"
        >
          <Plus className="w-5 h-5" />
          افزودن نماینده
        </button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-500">کل نمایندگان</p>
              <p className="text-2xl font-bold text-slate-800">{resellers.length}</p>
            </div>
            <div className="p-4 bg-blue-100 rounded-xl">
              <Users className="w-6 h-6 text-blue-600" />
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-500">کل اعتبار</p>
              <p className="text-2xl font-bold text-emerald-600">
                {formatCurrency(resellers.reduce((sum, r) => sum + (r.credit_limit || 0), 0))}
              </p>
            </div>
            <div className="p-4 bg-emerald-100 rounded-xl">
              <CreditCard className="w-6 h-6 text-emerald-600" />
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-500">کل بدهی</p>
              <p className="text-2xl font-bold text-red-600">
                {formatCurrency(resellers.reduce((sum, r) => sum + (r.current_debt || 0), 0))}
              </p>
            </div>
            <div className="p-4 bg-red-100 rounded-xl">
              <Receipt className="w-6 h-6 text-red-600" />
            </div>
          </div>
        </div>
      </div>

      {/* Resellers Table */}
      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        {resellers.length === 0 ? (
          <div className="p-12 text-center text-slate-500">
            <Users className="w-16 h-16 mx-auto mb-4 text-slate-300" />
            <p>هیچ نماینده‌ای وجود ندارد</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">نماینده</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">برند</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">سقف اعتبار</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">بدهی</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">تاریخ عضویت</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">عملیات</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {resellers.map((reseller) => (
                  <tr key={reseller.id} className="hover:bg-slate-50">
                    <td className="px-6 py-4">
                      <div>
                        <p className="font-medium text-slate-800">{reseller.username}</p>
                        <p className="text-sm text-slate-500">{reseller.email}</p>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-600">
                      {reseller.reseller_profile?.brand_name || '-'}
                    </td>
                    <td className="px-6 py-4 text-sm font-medium text-emerald-600">
                      {formatCurrency(reseller.credit_limit || 0)}
                    </td>
                    <td className="px-6 py-4 text-sm font-medium text-red-600">
                      {formatCurrency(reseller.current_debt || 0)}
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-600">
                      {new Date(reseller.created_at).toLocaleDateString('fa-IR')}
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => {
                            setSelectedReseller(reseller)
                            setFormData({
                              username: reseller.username,
                              email: reseller.email,
                              password: '',
                              credit_limit: reseller.credit_limit || 0,
                              brand_name: reseller.reseller_profile?.brand_name || '',
                              contact_number: reseller.reseller_profile?.contact_number || '',
                            })
                          }}
                          className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg"
                        >
                          <Edit className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => window.location.href = `/resellers/${reseller.id}/users`}
                          className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg"
                        >
                          <Eye className="w-4 h-4" />
                        </button>
                      </div>
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
              <h2 className="text-xl font-bold text-slate-800">افزودن نماینده جدید</h2>
            </div>
            <form onSubmit={handleCreateReseller} className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">نام کاربری</label>
                  <input
                    type="text"
                    value={formData.username}
                    onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">ایمیل</label>
                  <input
                    type="email"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    required
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">رمز عبور</label>
                  <input
                    type="password"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">سقف اعتبار (ریال)</label>
                  <input
                    type="number"
                    value={formData.credit_limit}
                    onChange={(e) => setFormData({ ...formData, credit_limit: Number(e.target.value) })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    required
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">نام برند</label>
                  <input
                    type="text"
                    value={formData.brand_name}
                    onChange={(e) => setFormData({ ...formData, brand_name: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">شماره تماس</label>
                  <input
                    type="text"
                    value={formData.contact_number}
                    onChange={(e) => setFormData({ ...formData, contact_number: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  />
                </div>
              </div>
              <div className="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
                >
                  انصراف
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600"
                >
                  ایجاد نماینده
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Modal */}
      {selectedReseller && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
            <div className="p-6 border-b border-slate-200">
              <h2 className="text-xl font-bold text-slate-800">ویرایش نماینده</h2>
            </div>
            <form onSubmit={handleUpdateReseller} className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">سقف اعتبار (ریال)</label>
                <input
                  type="number"
                  value={formData.credit_limit}
                  onChange={(e) => setFormData({ ...formData, credit_limit: Number(e.target.value) })}
                  className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  required
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">نام برند</label>
                  <input
                    type="text"
                    value={formData.brand_name}
                    onChange={(e) => setFormData({ ...formData, brand_name: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1">شماره تماس</label>
                  <input
                    type="text"
                    value={formData.contact_number}
                    onChange={(e) => setFormData({ ...formData, contact_number: e.target.value })}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  />
                </div>
              </div>
              <div className="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setSelectedReseller(null)}
                  className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
                >
                  انصراف
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600"
                >
                  ذخیره تغییرات
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}

