import { useEffect, useState } from 'react'
import { usersApi } from '../../../services/api'
import { Search, UserPlus, Edit, Trash2, Eye, Filter, X } from 'lucide-react'

interface User {
  id: number
  username: string
  email: string
  phone: string | null
  telegram_id: number | null
  role: string
  wallet_balance: number
  referral_earnings: number
  parent?: { username: string }
  created_at: string
  subscriptions_count?: number
}

interface Pagination {
  current_page: number
  last_page: number
  total: number
}

export default function UsersPage() {
  const [users, setUsers] = useState<User[]>([])
  const [pagination, setPagination] = useState<Pagination | null>(null)
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [roleFilter, setRoleFilter] = useState('')
  
  // Modal states
  const [showAddModal, setShowAddModal] = useState(false)
  const [showEditModal, setShowEditModal] = useState(false)
  const [showViewModal, setShowViewModal] = useState(false)
  const [selectedUser, setSelectedUser] = useState<User | null>(null)
  
  // Form state
  const [formData, setFormData] = useState({
    username: '',
    email: '',
    phone: '',
    password: '',
    role: 'user',
    wallet_balance: 0,
  })
  const [formError, setFormError] = useState('')
  const [formLoading, setFormLoading] = useState(false)

  useEffect(() => {
    loadUsers()
  }, [roleFilter])

  const loadUsers = async (page = 1) => {
    setLoading(true)
    try {
      const response = await usersApi.list({ role: roleFilter || undefined, page })
      setUsers(response.data.data)
      setPagination({
        current_page: response.data.current_page,
        last_page: response.data.last_page,
        total: response.data.total,
      })
    } catch (error) {
      console.error('Failed to load users:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('آیا از حذف این کاربر اطمینان دارید؟')) return
    
    try {
      await usersApi.delete(id)
      loadUsers()
    } catch (error) {
      console.error('Failed to delete user:', error)
      alert('خطا در حذف کاربر')
    }
  }

  const handleAdd = () => {
    setFormData({
      username: '',
      email: '',
      phone: '',
      password: '',
      role: 'user',
      wallet_balance: 0,
    })
    setFormError('')
    setShowAddModal(true)
  }

  const handleEdit = (user: User) => {
    setSelectedUser(user)
    setFormData({
      username: user.username || '',
      email: user.email || '',
      phone: user.phone || '',
      password: '',
      role: user.role,
      wallet_balance: user.wallet_balance || 0,
    })
    setFormError('')
    setShowEditModal(true)
  }

  const handleView = async (user: User) => {
    try {
      const response = await usersApi.get(user.id)
      setSelectedUser(response.data)
      setShowViewModal(true)
    } catch (error) {
      console.error('Failed to load user details:', error)
    }
  }

  const handleSubmitAdd = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormLoading(true)
    setFormError('')
    
    try {
      // Use the register endpoint for creating users
      const response = await fetch(`${import.meta.env.VITE_API_URL || 'http://api.localhost'}/api/auth/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
        body: JSON.stringify({
          username: formData.username,
          email: formData.email,
          phone: formData.phone || undefined,
          password: formData.password,
        }),
      })
      
      if (!response.ok) {
        const error = await response.json()
        throw new Error(error.message || 'خطا در ایجاد کاربر')
      }
      
      const newUser = await response.json()
      
      // Update user role if not default
      if (formData.role !== 'user') {
        await usersApi.update(newUser.user.id, { role: formData.role })
      }
      
      setShowAddModal(false)
      loadUsers()
    } catch (error: any) {
      setFormError(error.message || 'خطا در ایجاد کاربر')
    } finally {
      setFormLoading(false)
    }
  }

  const handleSubmitEdit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedUser) return
    
    setFormLoading(true)
    setFormError('')
    
    try {
      const updateData: any = {
        username: formData.username,
        email: formData.email,
        phone: formData.phone || null,
        role: formData.role,
        wallet_balance: formData.wallet_balance,
      }
      
      // Only include password if provided
      if (formData.password) {
        updateData.password = formData.password
      }
      
      await usersApi.update(selectedUser.id, updateData)
      setShowEditModal(false)
      loadUsers()
    } catch (error: any) {
      setFormError(error.response?.data?.message || 'خطا در ویرایش کاربر')
    } finally {
      setFormLoading(false)
    }
  }

  const getRoleBadgeColor = (role: string) => {
    switch (role) {
      case 'admin': return 'bg-red-100 text-red-700'
      case 'reseller': return 'bg-purple-100 text-purple-700'
      case 'affiliate': return 'bg-blue-100 text-blue-700'
      default: return 'bg-slate-100 text-slate-700'
    }
  }

  const getRoleLabel = (role: string) => {
    switch (role) {
      case 'admin': return 'مدیر'
      case 'reseller': return 'نماینده'
      case 'affiliate': return 'بازاریاب'
      default: return 'کاربر'
    }
  }

  const filteredUsers = users.filter(user => 
    user.username?.toLowerCase().includes(searchQuery.toLowerCase()) ||
    user.email?.toLowerCase().includes(searchQuery.toLowerCase())
  )

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">مدیریت کاربران</h1>
        <button 
          onClick={handleAdd}
          className="flex items-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition-colors"
        >
          <UserPlus className="w-5 h-5" />
          افزودن کاربر
        </button>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
        <div className="flex flex-wrap gap-4">
          <div className="flex-1 min-w-[200px]">
            <div className="relative">
              <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
              <input
                type="text"
                placeholder="جستجو..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-4 pr-10 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Filter className="w-5 h-5 text-slate-400" />
            <select
              value={roleFilter}
              onChange={(e) => setRoleFilter(e.target.value)}
              className="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
            >
              <option value="">همه نقش‌ها</option>
              <option value="admin">مدیر</option>
              <option value="reseller">نماینده</option>
              <option value="affiliate">بازاریاب</option>
              <option value="user">کاربر</option>
            </select>
          </div>
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
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">نام کاربری</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">ایمیل</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">نقش</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">موجودی</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">تاریخ عضویت</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">عملیات</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {filteredUsers.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-6 py-12 text-center text-slate-500">
                      کاربری یافت نشد
                    </td>
                  </tr>
                ) : (
                  filteredUsers.map((user) => (
                    <tr key={user.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4 text-sm text-slate-600">#{user.id}</td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600 font-medium">
                            {user.username?.[0]?.toUpperCase() || '?'}
                          </div>
                          <span className="font-medium text-slate-800">{user.username || '-'}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">{user.email || '-'}</td>
                      <td className="px-6 py-4">
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${getRoleBadgeColor(user.role)}`}>
                          {getRoleLabel(user.role)}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">
                        {user.wallet_balance?.toLocaleString() || 0} ﷼
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">
                        {new Date(user.created_at).toLocaleDateString('fa-IR')}
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-2">
                          <button 
                            onClick={() => handleView(user)}
                            className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" 
                            title="مشاهده"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          <button 
                            onClick={() => handleEdit(user)}
                            className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" 
                            title="ویرایش"
                          >
                            <Edit className="w-4 h-4" />
                          </button>
                          <button 
                            onClick={() => handleDelete(user.id)}
                            className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" 
                            title="حذف"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {pagination && pagination.last_page > 1 && (
          <div className="flex items-center justify-between px-6 py-4 border-t border-slate-200">
            <p className="text-sm text-slate-600">
              نمایش صفحه {pagination.current_page} از {pagination.last_page} (مجموع: {pagination.total})
            </p>
            <div className="flex gap-2">
              <button
                disabled={pagination.current_page === 1}
                onClick={() => loadUsers(pagination.current_page - 1)}
                className="px-4 py-2 border border-slate-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
              >
                قبلی
              </button>
              <button
                disabled={pagination.current_page === pagination.last_page}
                onClick={() => loadUsers(pagination.current_page + 1)}
                className="px-4 py-2 border border-slate-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
              >
                بعدی
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Add User Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div className="flex items-center justify-between p-6 border-b border-slate-200">
              <h2 className="text-xl font-bold text-slate-800">افزودن کاربر جدید</h2>
              <button onClick={() => setShowAddModal(false)} className="p-2 hover:bg-slate-100 rounded-lg">
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleSubmitAdd} className="p-6 space-y-4">
              {formError && (
                <div className="p-3 bg-red-50 text-red-600 rounded-lg text-sm">{formError}</div>
              )}
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">نام کاربری</label>
                <input
                  type="text"
                  value={formData.username}
                  onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">ایمیل</label>
                <input
                  type="email"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">شماره تماس</label>
                <input
                  type="text"
                  value={formData.phone}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">رمز عبور</label>
                <input
                  type="password"
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  required
                  minLength={8}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">نقش</label>
                <select
                  value={formData.role}
                  onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                >
                  <option value="user">کاربر</option>
                  <option value="affiliate">بازاریاب</option>
                  <option value="reseller">نماینده</option>
                  <option value="admin">مدیر</option>
                </select>
              </div>
              <div className="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setShowAddModal(false)}
                  className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
                >
                  انصراف
                </button>
                <button
                  type="submit"
                  disabled={formLoading}
                  className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-50"
                >
                  {formLoading ? 'در حال ذخیره...' : 'ذخیره'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit User Modal */}
      {showEditModal && selectedUser && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
            <div className="flex items-center justify-between p-6 border-b border-slate-200">
              <h2 className="text-xl font-bold text-slate-800">ویرایش کاربر #{selectedUser.id}</h2>
              <button onClick={() => setShowEditModal(false)} className="p-2 hover:bg-slate-100 rounded-lg">
                <X className="w-5 h-5" />
              </button>
            </div>
            <form onSubmit={handleSubmitEdit} className="p-6 space-y-4">
              {formError && (
                <div className="p-3 bg-red-50 text-red-600 rounded-lg text-sm">{formError}</div>
              )}
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">نام کاربری</label>
                <input
                  type="text"
                  value={formData.username}
                  onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">ایمیل</label>
                <input
                  type="email"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">شماره تماس</label>
                <input
                  type="text"
                  value={formData.phone}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">رمز عبور جدید (اختیاری)</label>
                <input
                  type="password"
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  placeholder="برای تغییر ندادن خالی بگذارید"
                  minLength={8}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">نقش</label>
                <select
                  value={formData.role}
                  onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                >
                  <option value="user">کاربر</option>
                  <option value="affiliate">بازاریاب</option>
                  <option value="reseller">نماینده</option>
                  <option value="admin">مدیر</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">موجودی کیف پول (ریال)</label>
                <input
                  type="number"
                  value={formData.wallet_balance}
                  onChange={(e) => setFormData({ ...formData, wallet_balance: parseInt(e.target.value) || 0 })}
                  className="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                />
              </div>
              <div className="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setShowEditModal(false)}
                  className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
                >
                  انصراف
                </button>
                <button
                  type="submit"
                  disabled={formLoading}
                  className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-50"
                >
                  {formLoading ? 'در حال ذخیره...' : 'ذخیره'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* View User Modal */}
      {showViewModal && selectedUser && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
            <div className="flex items-center justify-between p-6 border-b border-slate-200">
              <h2 className="text-xl font-bold text-slate-800">جزئیات کاربر #{selectedUser.id}</h2>
              <button onClick={() => setShowViewModal(false)} className="p-2 hover:bg-slate-100 rounded-lg">
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="p-6 space-y-4">
              <div className="flex items-center gap-4 mb-6">
                <div className="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600 text-2xl font-bold">
                  {selectedUser.username?.[0]?.toUpperCase() || '?'}
                </div>
                <div>
                  <h3 className="text-lg font-bold text-slate-800">{selectedUser.username || '-'}</h3>
                  <span className={`px-2 py-1 rounded-full text-xs font-medium ${getRoleBadgeColor(selectedUser.role)}`}>
                    {getRoleLabel(selectedUser.role)}
                  </span>
                </div>
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-slate-500">ایمیل</p>
                  <p className="font-medium text-slate-800">{selectedUser.email || '-'}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">شماره تماس</p>
                  <p className="font-medium text-slate-800">{selectedUser.phone || '-'}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">شناسه تلگرام</p>
                  <p className="font-medium text-slate-800">{selectedUser.telegram_id || '-'}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">موجودی</p>
                  <p className="font-medium text-emerald-600">{selectedUser.wallet_balance?.toLocaleString() || 0} ﷼</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">درآمد معرفی</p>
                  <p className="font-medium text-slate-800">{selectedUser.referral_earnings?.toLocaleString() || 0} ﷼</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">معرف</p>
                  <p className="font-medium text-slate-800">{selectedUser.parent?.username || '-'}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">تعداد سرویس</p>
                  <p className="font-medium text-slate-800">{selectedUser.subscriptions_count || 0}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">تاریخ عضویت</p>
                  <p className="font-medium text-slate-800">{new Date(selectedUser.created_at).toLocaleDateString('fa-IR')}</p>
                </div>
              </div>
            </div>
            <div className="flex justify-end gap-3 p-6 border-t border-slate-200">
              <button
                onClick={() => setShowViewModal(false)}
                className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
              >
                بستن
              </button>
              <button
                onClick={() => {
                  setShowViewModal(false)
                  handleEdit(selectedUser)
                }}
                className="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
              >
                ویرایش
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
