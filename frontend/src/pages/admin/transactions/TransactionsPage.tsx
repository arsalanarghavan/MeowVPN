import { useEffect, useState } from 'react'
import { transactionsApi } from '../../../services/api'
import { CreditCard, Check, X, Eye, Filter, Clock, CheckCircle, XCircle, AlertCircle } from 'lucide-react'

interface Transaction {
  id: number
  user: { id: number; username: string; email: string }
  amount: number
  type: string
  gateway: string
  status: string
  proof_image: string | null
  ref_id: string | null
  description: string | null
  created_at: string
}

interface Pagination {
  current_page: number
  last_page: number
  total: number
}

export default function TransactionsPage() {
  const [transactions, setTransactions] = useState<Transaction[]>([])
  const [pagination, setPagination] = useState<Pagination | null>(null)
  const [loading, setLoading] = useState(true)
  const [typeFilter, setTypeFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [selectedTransaction, setSelectedTransaction] = useState<Transaction | null>(null)

  useEffect(() => {
    loadTransactions()
  }, [typeFilter, statusFilter])

  const loadTransactions = async (page = 1) => {
    setLoading(true)
    try {
      const response = await transactionsApi.list({
        type: typeFilter || undefined,
        status: statusFilter || undefined,
        page,
      })
      setTransactions(response.data.data)
      setPagination({
        current_page: response.data.current_page,
        last_page: response.data.last_page,
        total: response.data.total,
      })
    } catch (error) {
      console.error('Failed to load transactions:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleApprove = async (id: number) => {
    if (!confirm('آیا از تایید این تراکنش اطمینان دارید؟')) return
    
    try {
      await transactionsApi.approve(id)
      loadTransactions()
      setSelectedTransaction(null)
    } catch (error) {
      console.error('Failed to approve transaction:', error)
      alert('خطا در تایید تراکنش')
    }
  }

  const handleReject = async (id: number) => {
    const reason = prompt('دلیل رد تراکنش:')
    if (reason === null) return
    
    try {
      await transactionsApi.reject(id, reason)
      loadTransactions()
      setSelectedTransaction(null)
    } catch (error) {
      console.error('Failed to reject transaction:', error)
      alert('خطا در رد تراکنش')
    }
  }

  const getTypeBadge = (type: string) => {
    switch (type) {
      case 'deposit': return { label: 'شارژ', color: 'bg-emerald-100 text-emerald-700' }
      case 'purchase': return { label: 'خرید', color: 'bg-purple-100 text-purple-700' }
      case 'renewal': return { label: 'تمدید', color: 'bg-blue-100 text-blue-700' }
      case 'commission': return { label: 'کمیسیون', color: 'bg-orange-100 text-orange-700' }
      case 'reseller_payment': return { label: 'پرداخت نماینده', color: 'bg-pink-100 text-pink-700' }
      default: return { label: type, color: 'bg-slate-100 text-slate-700' }
    }
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed': return { label: 'موفق', color: 'bg-emerald-100 text-emerald-700', icon: CheckCircle }
      case 'pending': return { label: 'در انتظار', color: 'bg-yellow-100 text-yellow-700', icon: Clock }
      case 'failed': return { label: 'ناموفق', color: 'bg-red-100 text-red-700', icon: XCircle }
      case 'rejected': return { label: 'رد شده', color: 'bg-red-100 text-red-700', icon: XCircle }
      default: return { label: status, color: 'bg-slate-100 text-slate-700', icon: AlertCircle }
    }
  }

  const getGatewayLabel = (gateway: string) => {
    switch (gateway) {
      case 'zibal': return 'زیبال'
      case 'card_to_card': return 'کارت به کارت'
      case 'wallet': return 'کیف پول'
      case 'system': return 'سیستم'
      default: return gateway
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">مدیریت تراکنش‌ها</h1>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
        <div className="flex flex-wrap gap-4">
          <div className="flex items-center gap-2">
            <Filter className="w-5 h-5 text-slate-400" />
            <select
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value)}
              className="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
            >
              <option value="">همه انواع</option>
              <option value="deposit">شارژ</option>
              <option value="purchase">خرید</option>
              <option value="renewal">تمدید</option>
              <option value="commission">کمیسیون</option>
            </select>
          </div>
          <div className="flex items-center gap-2">
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
            >
              <option value="">همه وضعیت‌ها</option>
              <option value="completed">موفق</option>
              <option value="pending">در انتظار</option>
              <option value="failed">ناموفق</option>
              <option value="rejected">رد شده</option>
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
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">کاربر</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">مبلغ</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">نوع</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">درگاه</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">وضعیت</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">تاریخ</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">عملیات</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {transactions.map((tx) => {
                  const typeBadge = getTypeBadge(tx.type)
                  const statusBadge = getStatusBadge(tx.status)
                  const StatusIcon = statusBadge.icon

                  return (
                    <tr key={tx.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4 text-sm text-slate-600">#{tx.id}</td>
                      <td className="px-6 py-4">
                        <div>
                          <p className="font-medium text-slate-800">{tx.user?.username || '-'}</p>
                          <p className="text-sm text-slate-500">{tx.user?.email || '-'}</p>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <span className={`font-medium ${tx.amount >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                          {tx.amount >= 0 ? '+' : ''}{tx.amount.toLocaleString()} ﷼
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${typeBadge.color}`}>
                          {typeBadge.label}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">
                        {getGatewayLabel(tx.gateway)}
                      </td>
                      <td className="px-6 py-4">
                        <span className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium w-fit ${statusBadge.color}`}>
                          <StatusIcon className="w-3 h-3" />
                          {statusBadge.label}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">
                        {new Date(tx.created_at).toLocaleDateString('fa-IR')}
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-2">
                          <button 
                            onClick={() => setSelectedTransaction(tx)}
                            className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" 
                            title="مشاهده"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          {tx.status === 'pending' && tx.type === 'deposit' && (
                            <>
                              <button 
                                onClick={() => handleApprove(tx.id)}
                                className="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" 
                                title="تایید"
                              >
                                <Check className="w-4 h-4" />
                              </button>
                              <button 
                                onClick={() => handleReject(tx.id)}
                                className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" 
                                title="رد"
                              >
                                <X className="w-4 h-4" />
                              </button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {pagination && pagination.last_page > 1 && (
          <div className="flex items-center justify-between px-6 py-4 border-t border-slate-200">
            <p className="text-sm text-slate-600">
              صفحه {pagination.current_page} از {pagination.last_page} (مجموع: {pagination.total})
            </p>
            <div className="flex gap-2">
              <button
                disabled={pagination.current_page === 1}
                onClick={() => loadTransactions(pagination.current_page - 1)}
                className="px-4 py-2 border border-slate-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
              >
                قبلی
              </button>
              <button
                disabled={pagination.current_page === pagination.last_page}
                onClick={() => loadTransactions(pagination.current_page + 1)}
                className="px-4 py-2 border border-slate-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
              >
                بعدی
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Detail Modal */}
      {selectedTransaction && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
            <div className="p-6 border-b border-slate-200 flex items-center justify-between">
              <h2 className="text-xl font-bold text-slate-800">جزئیات تراکنش #{selectedTransaction.id}</h2>
              <button 
                onClick={() => setSelectedTransaction(null)}
                className="p-2 hover:bg-slate-100 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-slate-500">کاربر</p>
                  <p className="font-medium">{selectedTransaction.user?.username}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">مبلغ</p>
                  <p className={`font-medium ${selectedTransaction.amount >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                    {selectedTransaction.amount.toLocaleString()} ﷼
                  </p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">نوع</p>
                  <p className="font-medium">{getTypeBadge(selectedTransaction.type).label}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">درگاه</p>
                  <p className="font-medium">{getGatewayLabel(selectedTransaction.gateway)}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">وضعیت</p>
                  <p className="font-medium">{getStatusBadge(selectedTransaction.status).label}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">تاریخ</p>
                  <p className="font-medium">{new Date(selectedTransaction.created_at).toLocaleString('fa-IR')}</p>
                </div>
              </div>
              
              {selectedTransaction.ref_id && (
                <div>
                  <p className="text-sm text-slate-500">شناسه مرجع</p>
                  <p className="font-mono text-sm">{selectedTransaction.ref_id}</p>
                </div>
              )}
              
              {selectedTransaction.description && (
                <div>
                  <p className="text-sm text-slate-500">توضیحات</p>
                  <p>{selectedTransaction.description}</p>
                </div>
              )}
              
              {selectedTransaction.proof_image && (
                <div>
                  <p className="text-sm text-slate-500 mb-2">تصویر رسید</p>
                  <img 
                    src={`${import.meta.env.VITE_API_URL}/storage/${selectedTransaction.proof_image}`} 
                    alt="رسید" 
                    className="max-w-full rounded-lg border"
                  />
                </div>
              )}

              {selectedTransaction.status === 'pending' && selectedTransaction.type === 'deposit' && (
                <div className="flex gap-3 pt-4 border-t">
                  <button 
                    onClick={() => handleApprove(selectedTransaction.id)}
                    className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600"
                  >
                    <Check className="w-4 h-4" />
                    تایید تراکنش
                  </button>
                  <button 
                    onClick={() => handleReject(selectedTransaction.id)}
                    className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600"
                  >
                    <X className="w-4 h-4" />
                    رد تراکنش
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

