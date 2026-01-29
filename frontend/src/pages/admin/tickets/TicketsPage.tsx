import { useEffect, useState } from 'react'
import { ticketsApi } from '../../../services/api'
import { MessageSquare, Plus, Eye, Clock, CheckCircle, AlertCircle, User } from 'lucide-react'

interface Ticket {
  id: number
  user: { id: number; username: string }
  assignee?: { id: number; username: string }
  subject: string
  status: 'open' | 'pending' | 'answered' | 'closed'
  priority: 'low' | 'medium' | 'high' | 'urgent'
  department: string
  created_at: string
  latest_message?: { message: string; created_at: string }
}

interface TicketMessage {
  id: number
  user: { username: string }
  message: string
  is_staff_reply: boolean
  created_at: string
}

interface Stats {
  total: number
  open: number
  pending: number
  answered: number
  closed: number
  by_priority: { urgent: number; high: number; medium: number; low: number }
}

export default function TicketsPage() {
  const [tickets, setTickets] = useState<Ticket[]>([])
  const [stats, setStats] = useState<Stats | null>(null)
  const [loading, setLoading] = useState(true)
  const [selectedTicket, setSelectedTicket] = useState<Ticket | null>(null)
  const [ticketMessages, setTicketMessages] = useState<TicketMessage[]>([])
  const [replyText, setReplyText] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [priorityFilter, setPriorityFilter] = useState('')

  useEffect(() => {
    loadTickets()
    loadStats()
  }, [statusFilter, priorityFilter])

  const loadTickets = async () => {
    setLoading(true)
    try {
      const params: any = {}
      if (statusFilter) params.status = statusFilter
      if (priorityFilter) params.priority = priorityFilter
      const response = await ticketsApi.list(params)
      setTickets(response.data.data || response.data)
    } catch (error) {
      console.error('Failed to load tickets:', error)
    } finally {
      setLoading(false)
    }
  }

  const loadStats = async () => {
    try {
      const response = await ticketsApi.stats()
      setStats(response.data)
    } catch (error) {
      console.error('Failed to load stats:', error)
    }
  }

  const loadTicketDetails = async (ticket: Ticket) => {
    try {
      const response = await ticketsApi.get(ticket.id)
      setSelectedTicket(response.data)
      setTicketMessages(response.data.messages || [])
    } catch (error) {
      console.error('Failed to load ticket details:', error)
    }
  }

  const handleReply = async () => {
    if (!selectedTicket || !replyText.trim()) return
    
    try {
      await ticketsApi.reply(selectedTicket.id, replyText)
      setReplyText('')
      loadTicketDetails(selectedTicket)
      loadTickets()
    } catch (error) {
      console.error('Failed to reply:', error)
      alert('خطا در ارسال پاسخ')
    }
  }

  const handleClose = async (ticketId: number) => {
    try {
      await ticketsApi.close(ticketId)
      loadTickets()
      if (selectedTicket?.id === ticketId) {
        setSelectedTicket(null)
      }
    } catch (error) {
      console.error('Failed to close ticket:', error)
    }
  }

  const handleReopen = async (ticketId: number) => {
    try {
      await ticketsApi.reopen(ticketId)
      loadTickets()
    } catch (error) {
      console.error('Failed to reopen ticket:', error)
    }
  }

  const getStatusBadge = (status: string) => {
    const styles: Record<string, string> = {
      open: 'bg-blue-100 text-blue-700',
      pending: 'bg-yellow-100 text-yellow-700',
      answered: 'bg-green-100 text-green-700',
      closed: 'bg-slate-100 text-slate-700',
    }
    const labels: Record<string, string> = {
      open: 'باز',
      pending: 'در انتظار',
      answered: 'پاسخ داده شده',
      closed: 'بسته شده',
    }
    return (
      <span className={`px-2 py-1 rounded-full text-xs font-medium ${styles[status] || styles.open}`}>
        {labels[status] || status}
      </span>
    )
  }

  const getPriorityBadge = (priority: string) => {
    const styles: Record<string, string> = {
      low: 'bg-slate-100 text-slate-600',
      medium: 'bg-blue-100 text-blue-600',
      high: 'bg-orange-100 text-orange-600',
      urgent: 'bg-red-100 text-red-600',
    }
    const labels: Record<string, string> = {
      low: 'کم',
      medium: 'متوسط',
      high: 'بالا',
      urgent: 'فوری',
    }
    return (
      <span className={`px-2 py-1 rounded-full text-xs font-medium ${styles[priority] || styles.medium}`}>
        {labels[priority] || priority}
      </span>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-800">مدیریت تیکت‌ها</h1>
      </div>

      {/* Stats Cards */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-blue-100 rounded-lg">
                <MessageSquare className="w-5 h-5 text-blue-600" />
              </div>
              <div>
                <p className="text-sm text-slate-500">باز</p>
                <p className="text-xl font-bold text-slate-800">{stats.open}</p>
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
                <p className="text-xl font-bold text-slate-800">{stats.pending}</p>
              </div>
            </div>
          </div>
          <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-green-100 rounded-lg">
                <CheckCircle className="w-5 h-5 text-green-600" />
              </div>
              <div>
                <p className="text-sm text-slate-500">پاسخ داده شده</p>
                <p className="text-xl font-bold text-slate-800">{stats.answered}</p>
              </div>
            </div>
          </div>
          <div className="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-red-100 rounded-lg">
                <AlertCircle className="w-5 h-5 text-red-600" />
              </div>
              <div>
                <p className="text-sm text-slate-500">فوری</p>
                <p className="text-xl font-bold text-slate-800">{stats.by_priority?.urgent || 0}</p>
              </div>
            </div>
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
          <option value="open">باز</option>
          <option value="pending">در انتظار</option>
          <option value="answered">پاسخ داده شده</option>
          <option value="closed">بسته شده</option>
        </select>
        <select
          value={priorityFilter}
          onChange={(e) => setPriorityFilter(e.target.value)}
          className="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
        >
          <option value="">همه اولویت‌ها</option>
          <option value="urgent">فوری</option>
          <option value="high">بالا</option>
          <option value="medium">متوسط</option>
          <option value="low">کم</option>
        </select>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Tickets List */}
        <div className="lg:col-span-1 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
          <div className="p-4 border-b border-slate-200">
            <h2 className="font-semibold text-slate-800">لیست تیکت‌ها</h2>
          </div>
          <div className="divide-y divide-slate-200 max-h-[600px] overflow-y-auto">
            {loading ? (
              <div className="p-8 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500 mx-auto"></div>
              </div>
            ) : tickets.length === 0 ? (
              <div className="p-8 text-center text-slate-500">تیکتی یافت نشد</div>
            ) : (
              tickets.map((ticket) => (
                <div
                  key={ticket.id}
                  onClick={() => loadTicketDetails(ticket)}
                  className={`p-4 cursor-pointer hover:bg-slate-50 transition-colors ${
                    selectedTicket?.id === ticket.id ? 'bg-emerald-50 border-r-4 border-emerald-500' : ''
                  }`}
                >
                  <div className="flex items-start justify-between mb-2">
                    <span className="font-medium text-slate-800 truncate flex-1">
                      #{ticket.id} - {ticket.subject}
                    </span>
                  </div>
                  <div className="flex items-center gap-2 mb-2">
                    {getStatusBadge(ticket.status)}
                    {getPriorityBadge(ticket.priority)}
                  </div>
                  <div className="flex items-center gap-2 text-sm text-slate-500">
                    <User className="w-4 h-4" />
                    <span>{ticket.user?.username || 'کاربر'}</span>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Ticket Details */}
        <div className="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
          {selectedTicket ? (
            <div className="flex flex-col h-[600px]">
              {/* Ticket Header */}
              <div className="p-4 border-b border-slate-200">
                <div className="flex items-center justify-between">
                  <div>
                    <h2 className="font-semibold text-slate-800">#{selectedTicket.id} - {selectedTicket.subject}</h2>
                    <div className="flex items-center gap-2 mt-2">
                      {getStatusBadge(selectedTicket.status)}
                      {getPriorityBadge(selectedTicket.priority)}
                      <span className="text-sm text-slate-500">
                        از {selectedTicket.user?.username || 'کاربر'}
                      </span>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    {selectedTicket.status !== 'closed' ? (
                      <button
                        onClick={() => handleClose(selectedTicket.id)}
                        className="px-3 py-1 text-sm bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200"
                      >
                        بستن تیکت
                      </button>
                    ) : (
                      <button
                        onClick={() => handleReopen(selectedTicket.id)}
                        className="px-3 py-1 text-sm bg-emerald-100 text-emerald-600 rounded-lg hover:bg-emerald-200"
                      >
                        بازکردن تیکت
                      </button>
                    )}
                  </div>
                </div>
              </div>

              {/* Messages */}
              <div className="flex-1 overflow-y-auto p-4 space-y-4">
                {ticketMessages.map((msg) => (
                  <div
                    key={msg.id}
                    className={`flex ${msg.is_staff_reply ? 'justify-start' : 'justify-end'}`}
                  >
                    <div
                      className={`max-w-[80%] p-3 rounded-lg ${
                        msg.is_staff_reply
                          ? 'bg-emerald-100 text-emerald-900'
                          : 'bg-slate-100 text-slate-900'
                      }`}
                    >
                      <div className="text-xs text-slate-500 mb-1">
                        {msg.user?.username || 'کاربر'} - {new Date(msg.created_at).toLocaleString('fa-IR')}
                      </div>
                      <p className="whitespace-pre-wrap">{msg.message}</p>
                    </div>
                  </div>
                ))}
              </div>

              {/* Reply Box */}
              {selectedTicket.status !== 'closed' && (
                <div className="p-4 border-t border-slate-200">
                  <div className="flex gap-2">
                    <textarea
                      value={replyText}
                      onChange={(e) => setReplyText(e.target.value)}
                      placeholder="پاسخ خود را بنویسید..."
                      className="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 resize-none"
                      rows={3}
                    />
                    <button
                      onClick={handleReply}
                      disabled={!replyText.trim()}
                      className="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      ارسال
                    </button>
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="h-[600px] flex items-center justify-center text-slate-500">
              <div className="text-center">
                <MessageSquare className="w-12 h-12 mx-auto mb-4 text-slate-300" />
                <p>برای مشاهده جزئیات، یک تیکت انتخاب کنید</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

