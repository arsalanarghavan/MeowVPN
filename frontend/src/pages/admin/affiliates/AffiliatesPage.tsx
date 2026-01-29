import { useState, useEffect } from 'react'
import { usersApi, affiliatesApi } from '../../../services/api'
import { Users, TrendingUp, CreditCard, Check, X, Clock } from 'lucide-react'

interface User {
  id: number
  username: string
  email: string
  wallet_balance: number
  created_at: string
}

interface PayoutRequest {
  id: number
  user_id: number
  amount: number
  card_number: string
  card_holder: string
  status: 'pending' | 'approved' | 'rejected' | 'paid'
  admin_note: string
  paid_at: string
  created_at: string
  user: User
}

export default function AffiliatesPage() {
  const [affiliates, setAffiliates] = useState<User[]>([])
  const [payoutRequests, setPayoutRequests] = useState<PayoutRequest[]>([])
  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState<'affiliates' | 'payouts'>('affiliates')

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    try {
      const [usersRes, payoutsRes] = await Promise.all([
        usersApi.list({ role: 'affiliate' }),
        affiliatesApi.payouts(),
      ])
      setAffiliates(usersRes.data.data || usersRes.data)
      setPayoutRequests(payoutsRes.data.data || payoutsRes.data)
    } catch (error) {
      console.error('Failed to load data:', error)
    } finally {
      setLoading(false)
    }
  }

  const handleApprovePayout = async (id: number) => {
    if (!confirm('آیا از تایید این درخواست اطمینان دارید؟')) return
    try {
      await affiliatesApi.approvePayout(id)
      loadData()
    } catch (error: any) {
      alert(error.response?.data?.error || 'خطا در تایید درخواست')
    }
  }

  const handleRejectPayout = async (id: number) => {
    const reason = prompt('دلیل رد درخواست (اختیاری):')
    try {
      await fetch(`/api/affiliates/payouts/${id}/reject`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
        body: JSON.stringify({ reason }),
      })
      loadData()
    } catch (error) {
      alert('خطا در رد درخواست')
    }
  }

  const formatCurrency = (amount: number) => {
    return amount.toLocaleString() + ' ﷼'
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'pending':
        return <span className="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">در انتظار</span>
      case 'approved':
        return <span className="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">تایید شده</span>
      case 'rejected':
        return <span className="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">رد شده</span>
      case 'paid':
        return <span className="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700">پرداخت شده</span>
      default:
        return null
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-500"></div>
      </div>
    )
  }

  const pendingPayouts = payoutRequests.filter(p => p.status === 'pending')

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-slate-800">مدیریت بازاریابان</h1>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-500">کل بازاریابان</p>
              <p className="text-2xl font-bold text-slate-800">{affiliates.length}</p>
            </div>
            <div className="p-4 bg-blue-100 rounded-xl">
              <Users className="w-6 h-6 text-blue-600" />
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-500">کل موجودی‌ها</p>
              <p className="text-2xl font-bold text-emerald-600">
                {formatCurrency(affiliates.reduce((sum, a) => sum + (a.wallet_balance || 0), 0))}
              </p>
            </div>
            <div className="p-4 bg-emerald-100 rounded-xl">
              <TrendingUp className="w-6 h-6 text-emerald-600" />
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-500">درخواست‌های در انتظار</p>
              <p className="text-2xl font-bold text-orange-600">{pendingPayouts.length}</p>
            </div>
            <div className="p-4 bg-orange-100 rounded-xl">
              <Clock className="w-6 h-6 text-orange-600" />
            </div>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-slate-200">
        <nav className="flex gap-4">
          <button
            onClick={() => setActiveTab('affiliates')}
            className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'affiliates'
                ? 'border-emerald-500 text-emerald-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            بازاریابان ({affiliates.length})
          </button>
          <button
            onClick={() => setActiveTab('payouts')}
            className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'payouts'
                ? 'border-emerald-500 text-emerald-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            درخواست‌های تسویه ({payoutRequests.length})
            {pendingPayouts.length > 0 && (
              <span className="mr-2 px-2 py-0.5 text-xs bg-orange-500 text-white rounded-full">
                {pendingPayouts.length}
              </span>
            )}
          </button>
        </nav>
      </div>

      {/* Content */}
      <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        {activeTab === 'affiliates' ? (
          affiliates.length === 0 ? (
            <div className="p-12 text-center text-slate-500">
              <Users className="w-16 h-16 mx-auto mb-4 text-slate-300" />
              <p>هیچ بازاریابی وجود ندارد</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50 border-b border-slate-200">
                  <tr>
                    <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">بازاریاب</th>
                    <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">موجودی</th>
                    <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">تاریخ عضویت</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {affiliates.map((affiliate) => (
                    <tr key={affiliate.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4">
                        <div>
                          <p className="font-medium text-slate-800">{affiliate.username || '-'}</p>
                          <p className="text-sm text-slate-500">{affiliate.email || '-'}</p>
                        </div>
                      </td>
                      <td className="px-6 py-4 font-medium text-emerald-600">
                        {formatCurrency(affiliate.wallet_balance || 0)}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">
                        {new Date(affiliate.created_at).toLocaleDateString('fa-IR')}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )
        ) : (
          payoutRequests.length === 0 ? (
            <div className="p-12 text-center text-slate-500">
              <CreditCard className="w-16 h-16 mx-auto mb-4 text-slate-300" />
              <p>هیچ درخواست تسویه‌ای وجود ندارد</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-50 border-b border-slate-200">
                  <tr>
                    <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">کاربر</th>
                    <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">مبلغ</th>
                    <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">شماره کارت</th>
                    <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">وضعیت</th>
                    <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">تاریخ</th>
                    <th className="px-6 py-4 text-right text-sm font-semibold text-slate-600">عملیات</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {payoutRequests.map((payout) => (
                    <tr key={payout.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4">
                        <div>
                          <p className="font-medium text-slate-800">{payout.user?.username || '-'}</p>
                          <p className="text-sm text-slate-500">#{payout.user_id}</p>
                        </div>
                      </td>
                      <td className="px-6 py-4 font-medium text-emerald-600">
                        {formatCurrency(payout.amount)}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600 font-mono">
                        {payout.card_number}
                        {payout.card_holder && <p className="text-xs text-slate-400">{payout.card_holder}</p>}
                      </td>
                      <td className="px-6 py-4">
                        {getStatusBadge(payout.status)}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-600">
                        {new Date(payout.created_at).toLocaleDateString('fa-IR')}
                      </td>
                      <td className="px-6 py-4">
                        {payout.status === 'pending' && (
                          <div className="flex items-center gap-2">
                            <button
                              onClick={() => handleApprovePayout(payout.id)}
                              className="p-2 text-green-600 hover:bg-green-50 rounded-lg"
                              title="تایید"
                            >
                              <Check className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleRejectPayout(payout.id)}
                              className="p-2 text-red-600 hover:bg-red-50 rounded-lg"
                              title="رد"
                            >
                              <X className="w-4 h-4" />
                            </button>
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )
        )}
      </div>
    </div>
  )
}

