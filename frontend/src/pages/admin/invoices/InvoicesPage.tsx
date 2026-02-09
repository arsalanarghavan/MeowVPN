import { useEffect, useState } from 'react'
import { invoicesApi } from '../../../services/api'
import { FileText, Download, CheckCircle, Clock, AlertCircle, Eye, ChevronRight, ChevronLeft } from 'lucide-react'

interface Invoice {
  id: number
  reseller: { id: number; username: string }
  start_date: string
  end_date: string
  total_amount: number
  status: 'unpaid' | 'paid'
  file_path: string | null
  created_at: string
}

interface Stats {
  total: number
  unpaid: number
  paid: number
  overdue: number
  total_unpaid_amount: number
  total_paid_amount: number
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export default function InvoicesPage() {
  const [invoices, setInvoices] = useState<Invoice[]>([])
  const [stats, setStats] = useState<Stats | null>(null)
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState('')
  const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null)
  const [pagination, setPagination] = useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
  })

  useEffect(() => {
    loadInvoices(1)
    loadStats()
  }, [statusFilter])

  const loadInvoices = async (page: number = 1) => {
    setLoading(true)
    try {
      const params: { status?: string; page?: number } = {}
      if (statusFilter) params.status = statusFilter
      params.page = page
      const response = await invoicesApi.list(params)
      const data = response.data
      setInvoices(data.data ?? data)
      if (data.current_page !== undefined) {
        setPagination({
          current_page: data.current_page,
          last_page: data.last_page,
          per_page: data.per_page,
          total: data.total,
        })
      }
    } catch (error) {
      console.error('Failed to load invoices:', error)
    } finally {
      setLoading(false)
    }
  }

  const loadStats = async () => {
    try {
      const response = await invoicesApi.stats()
      setStats(response.data)
    } catch (error) {
      console.error('Failed to load stats:', error)
    }
  }

  const handleGeneratePdf = async (invoiceId: number) => {
    try {
      await invoicesApi.generate(invoiceId)
      loadInvoices(pagination.current_page)
      alert('فاکتور با موفقیت ایجاد شد')
    } catch (error) {
      console.error('Failed to generate PDF:', error)
      alert('خطا در ایجاد فاکتور')
    }
  }

  const handleMarkPaid = async (invoiceId: number) => {
    if (!confirm('آیا این فاکتور پرداخت شده است؟')) return
    
    try {
      await invoicesApi.markPaid(invoiceId)
      loadInvoices(pagination.current_page)
      loadStats()
    } catch (error) {
      console.error('Failed to mark as paid:', error)
      alert('خطا در ثبت پرداخت')
    }
  }

  const handleDownload = (invoiceId: number) => {
    window.open(invoicesApi.getDownloadUrl(invoiceId), '_blank')
  }

  const getStatusBadge = (status: string, endDate?: string) => {
    const isOverdue = status === 'unpaid' && endDate && new Date(endDate) < new Date()
    const displayStatus = isOverdue ? 'overdue' : status
    const styles: Record<string, string> = {
      unpaid: 'bg-yellow-100 text-yellow-700',
      paid: 'bg-green-100 text-green-700',
      overdue: 'bg-red-100 text-red-700',
    }
    const labels: Record<string, string> = {
      unpaid: 'در انتظار پرداخت',
      paid: 'پرداخت شده',
      overdue: 'معوق',
    }
    return (
      <span className={`px-2 py-1 rounded-full text-xs font-medium ${styles[displayStatus] || styles.unpaid}`}>
        {labels[displayStatus] || status}
      </span>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">مدیریت فاکتورها</h1>
      </div>

      {/* Stats Cards */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-blue-100 rounded-lg">
                <FileText className="w-5 h-5 text-blue-600" />
              </div>
              <div>
                <p className="text-sm text-slate-500">کل فاکتورها</p>
                <p className="text-xl font-bold text-slate-800">{stats.total}</p>
              </div>
            </div>
          </div>
          <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-yellow-100 rounded-lg">
                <Clock className="w-5 h-5 text-yellow-600" />
              </div>
              <div>
                <p className="text-sm text-slate-500">در انتظار</p>
                <p className="text-xl font-bold text-slate-800">{stats.unpaid}</p>
              </div>
            </div>
          </div>
          <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-green-100 rounded-lg">
                <CheckCircle className="w-5 h-5 text-green-600" />
              </div>
              <div>
                <p className="text-sm text-slate-500">پرداخت شده</p>
                <p className="text-xl font-bold text-slate-800">{stats.paid}</p>
              </div>
            </div>
          </div>
          <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-red-100 rounded-lg">
                <AlertCircle className="w-5 h-5 text-red-600" />
              </div>
              <div>
                <p className="text-sm text-slate-500">معوق</p>
                <p className="text-xl font-bold text-slate-800">{stats.overdue}</p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Amount Summary */}
      {stats && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl shadow-sm p-6 text-white">
            <p className="text-sm opacity-80">مبلغ در انتظار پرداخت</p>
            <p className="text-3xl font-bold mt-2">{stats.total_unpaid_amount?.toLocaleString() || 0} ﷼</p>
          </div>
          <div className="bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl shadow-sm p-6 text-white">
            <p className="text-sm opacity-80">مبلغ پرداخت شده</p>
            <p className="text-3xl font-bold mt-2">{stats.total_paid_amount?.toLocaleString() || 0} ﷼</p>
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200 flex flex-wrap gap-4">
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
        >
          <option value="">همه وضعیت‌ها</option>
          <option value="unpaid">در انتظار پرداخت</option>
          <option value="paid">پرداخت شده</option>
        </select>
      </div>

      {/* Invoices Table */}
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
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">نماینده</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">دوره</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">مبلغ</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">وضعیت</th>
                  <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">عملیات</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {invoices.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                      فاکتوری یافت نشد
                    </td>
                  </tr>
                ) : (
                  invoices.map((invoice) => (
                    <tr key={invoice.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4 text-sm text-slate-600">#{invoice.id}</td>
                      <td className="px-6 py-4">
                        <span className="font-medium text-slate-800">{invoice.reseller?.username || '-'}</span>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">
                        {new Date(invoice.start_date).toLocaleDateString('fa-IR')} تا{' '}
                        {new Date(invoice.end_date).toLocaleDateString('fa-IR')}
                      </td>
                      <td className="px-6 py-4 text-sm font-medium text-slate-800">
                        {invoice.total_amount?.toLocaleString() || 0} ﷼
                      </td>
                      <td className="px-6 py-4">{getStatusBadge(invoice.status, invoice.end_date)}</td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => setSelectedInvoice(invoice)}
                            className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                            title="مشاهده"
                          >
                            <Eye className="w-4 h-4" />
                          </button>
                          {invoice.file_path ? (
                            <button
                              onClick={() => handleDownload(invoice.id)}
                              className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                              title="دانلود"
                            >
                              <Download className="w-4 h-4" />
                            </button>
                          ) : (
                            <button
                              onClick={() => handleGeneratePdf(invoice.id)}
                              className="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                              title="ایجاد PDF"
                            >
                              <FileText className="w-4 h-4" />
                            </button>
                          )}
                          {invoice.status !== 'paid' && (
                            <button
                              onClick={() => handleMarkPaid(invoice.id)}
                              className="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                              title="ثبت پرداخت"
                            >
                              <CheckCircle className="w-4 h-4" />
                            </button>
                          )}
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
        {!loading && pagination.last_page > 1 && (
          <div className="flex items-center justify-between px-6 py-4 border-t border-slate-200 bg-slate-50">
            <p className="text-sm text-slate-600">
              صفحه {pagination.current_page} از {pagination.last_page} (مجموع {pagination.total} فاکتور)
            </p>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => loadInvoices(pagination.current_page - 1)}
                disabled={pagination.current_page <= 1}
                className="p-2 rounded-lg border border-slate-300 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed"
                title="صفحه قبل"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
              <button
                type="button"
                onClick={() => loadInvoices(pagination.current_page + 1)}
                disabled={pagination.current_page >= pagination.last_page}
                className="p-2 rounded-lg border border-slate-300 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed"
                title="صفحه بعد"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Invoice Detail Modal */}
      {selectedInvoice && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
            <div className="p-6 border-b border-slate-200">
              <h2 className="text-xl font-bold text-slate-800">
                جزئیات فاکتور #{selectedInvoice.id}
              </h2>
            </div>
            <div className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-slate-500">نماینده</p>
                  <p className="font-medium text-slate-800">{selectedInvoice.reseller?.username || '-'}</p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">وضعیت</p>
                  <div className="mt-1">{getStatusBadge(selectedInvoice.status, selectedInvoice.end_date)}</div>
                </div>
                <div>
                  <p className="text-sm text-slate-500">تاریخ شروع</p>
                  <p className="font-medium text-slate-800">
                    {new Date(selectedInvoice.start_date).toLocaleDateString('fa-IR')}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-slate-500">تاریخ پایان</p>
                  <p className="font-medium text-slate-800">
                    {new Date(selectedInvoice.end_date).toLocaleDateString('fa-IR')}
                  </p>
                </div>
                <div className="col-span-2">
                  <p className="text-sm text-slate-500">مبلغ کل</p>
                  <p className="text-2xl font-bold text-emerald-600">
                    {selectedInvoice.total_amount?.toLocaleString() || 0} ﷼
                  </p>
                </div>
              </div>
            </div>
            <div className="flex justify-end gap-3 p-6 border-t border-slate-200">
              <button
                onClick={() => setSelectedInvoice(null)}
                className="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
              >
                بستن
              </button>
              {selectedInvoice.file_path ? (
                <button
                  onClick={() => handleDownload(selectedInvoice.id)}
                  className="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                >
                  دانلود PDF
                </button>
              ) : (
                <button
                  onClick={() => {
                    handleGeneratePdf(selectedInvoice.id)
                    setSelectedInvoice(null)
                  }}
                  className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600"
                >
                  ایجاد PDF
                </button>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

