import axios from 'axios'

const API_URL = import.meta.env.VITE_API_URL || 'http://api.localhost'

const api = axios.create({
  baseURL: `${API_URL}/api`,
  headers: {
    'Content-Type': 'application/json',
  },
})

// Add auth token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle 401 errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

// Auth
export const authApi = {
  login: (email: string, password: string) => api.post('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
}

// Dashboard
export const dashboardApi = {
  stats: () => api.get('/dashboard/stats'),
  sales: (period: string = 'month') => api.get(`/dashboard/sales?period=${period}`),
  recentTransactions: () => api.get('/dashboard/recent-transactions'),
}

// Users
export const usersApi = {
  list: (params?: { role?: string; page?: number }) => api.get('/users', { params }),
  get: (id: number) => api.get(`/users/${id}`),
  update: (id: number, data: any) => api.put(`/users/${id}`, data),
  delete: (id: number) => api.delete(`/users/${id}`),
}

// Servers
export const serversApi = {
  list: (params?: { location_tag?: string; region?: string; server_category?: string; is_active?: boolean; panel_type?: string }) => api.get('/servers', { params }),
  available: (params?: { region?: string; server_category?: string; location_tag?: string }) =>
    api.get('/servers/available', { params }),
  monitoring: () => api.get('/servers/monitoring'),
  panelTypes: () => api.get('/servers/panel-types'),
  regionCategoryOptions: () => api.get('/servers/region-category-options'),
  get: (id: number) => api.get(`/servers/${id}`),
  create: (data: any) => api.post('/servers', data),
  update: (id: number, data: any) => api.put(`/servers/${id}`, data),
  delete: (id: number) => api.delete(`/servers/${id}`),
  health: (id: number) => api.get(`/servers/${id}/health`),
  testConnection: (id: number) => api.post(`/servers/${id}/test-connection`),
  restartPanel: (id: number) => api.post(`/servers/${id}/restart-panel`),
  reboot: (id: number) => api.post(`/servers/${id}/reboot`),
  suspend: (id: number) => api.post(`/servers/${id}/suspend`),
  resume: (id: number) => api.post(`/servers/${id}/resume`),
  reinstall: (id: number, data?: { os?: string; recipe?: string; password?: string }) =>
    api.post(`/servers/${id}/reinstall`, data ?? {}),
  changeRootPassword: (id: number, password: string) =>
    api.post(`/servers/${id}/change-root-password`, { password }),
  vpsStats: (id: number) => api.get(`/servers/${id}/vps-stats`),
  inbounds: (id: number) => api.get(`/servers/${id}/inbounds`),
  users: (id: number, params?: { offset?: number; limit?: number }) => api.get(`/servers/${id}/users`, { params }),
  syncUserCount: (id: number) => api.post(`/servers/${id}/sync-user-count`),
}

// Plans
export const plansApi = {
  list: () => api.get('/plans'),
  get: (id: number) => api.get(`/plans/${id}`),
  create: (data: any) => api.post('/plans', data),
  update: (id: number, data: any) => api.put(`/plans/${id}`, data),
  delete: (id: number) => api.delete(`/plans/${id}`),
}

// Subscriptions
export const subscriptionsApi = {
  list: (params?: { status?: string }) => api.get('/subscriptions', { params }),
  get: (id: number) => api.get(`/subscriptions/${id}`),
  create: (data: any) => api.post('/subscriptions', data),
  renew: (id: number, data?: any) => api.post(`/subscriptions/${id}/renew`, data),
  changeLocation: (id: number, location_tag: string) => api.post(`/subscriptions/${id}/change-location`, { location_tag }),
  sync: (id: number) => api.post(`/subscriptions/${id}/sync`),
  delete: (id: number) => api.delete(`/subscriptions/${id}`),
  enable: (id: number) => api.post(`/subscriptions/${id}/enable`),
  disable: (id: number) => api.post(`/subscriptions/${id}/disable`),
  updateMaxDevices: (id: number, max_devices: number) => api.put(`/subscriptions/${id}/max-devices`, { max_devices }),
}

// Transactions
export const transactionsApi = {
  list: (params?: { type?: string; status?: string; page?: number }) => api.get('/transactions', { params }),
  pending: () => api.get('/transactions/pending'),
  stats: () => api.get('/transactions/stats'),
  get: (id: number) => api.get(`/transactions/${id}`),
  deposit: (data: any) => api.post('/transactions/deposit', data),
  approve: (id: number) => api.post(`/transactions/${id}/approve`),
  reject: (id: number, reason?: string) => api.post(`/transactions/${id}/reject`, { reason }),
}

// Resellers
export const resellersApi = {
  list: () => api.get('/resellers'),
  get: (id: number) => api.get(`/resellers/${id}`),
  create: (data: any) => api.post('/resellers', data),
  update: (id: number, data: any) => api.put(`/resellers/${id}`, data),
  users: (id: number) => api.get(`/resellers/${id}/users`),
  invoices: (id: number) => api.get(`/resellers/${id}/invoices`),
  payDebt: (id: number, amount: number) => api.post(`/resellers/${id}/pay-debt`, { amount }),
}

// Affiliates
export const affiliatesApi = {
  stats: () => api.get('/affiliates/stats'),
  link: () => api.get('/affiliates/link'),
  requestPayout: (card_number: string, card_holder?: string) => 
    api.post('/affiliates/payout-request', { card_number, card_holder }),
  myPayouts: () => api.get('/affiliates/my-payouts'),
  payouts: (params?: { status?: string }) => api.get('/affiliates/payouts', { params }),
  pendingPayouts: () => api.get('/affiliates/payouts/pending'),
  approvePayout: (id: number) => api.post(`/affiliates/payouts/${id}/approve`),
  rejectPayout: (id: number, reason?: string) => api.post(`/affiliates/payouts/${id}/reject`, { reason }),
}

// Tickets (Support System)
export const ticketsApi = {
  list: (params?: { status?: string; priority?: string }) => api.get('/tickets', { params }),
  stats: () => api.get('/tickets/stats'),
  get: (id: number) => api.get(`/tickets/${id}`),
  create: (data: { subject: string; message: string; priority?: string }) => api.post('/tickets', data),
  reply: (id: number, message: string) => api.post(`/tickets/${id}/reply`, { message }),
  close: (id: number) => api.post(`/tickets/${id}/close`),
  reopen: (id: number) => api.post(`/tickets/${id}/reopen`),
  assign: (id: number, admin_id: number) => api.post(`/tickets/${id}/assign`, { admin_id }),
  updatePriority: (id: number, priority: string) => api.post(`/tickets/${id}/priority`, { priority }),
}

// Invoices
export const invoicesApi = {
  list: (params?: { status?: string; reseller_id?: number; page?: number }) => api.get('/invoices', { params }),
  stats: () => api.get('/invoices/stats'),
  get: (id: number) => api.get(`/invoices/${id}`),
  generate: (id: number) => api.post(`/invoices/${id}/generate`),
  download: (id: number) => `${api.defaults.baseURL}/invoices/${id}/download`,
  /** URL for opening in new tab (includes token for auth) */
  getDownloadUrl: (id: number) => {
    const token = localStorage.getItem('token')
    return `${api.defaults.baseURL}/invoices/${id}/download${token ? `?token=${token}` : ''}`
  },
  markPaid: (id: number) => api.post(`/invoices/${id}/mark-paid`),
  transactionReceipt: (transactionId: number) => `${api.defaults.baseURL}/transactions/${transactionId}/receipt`,
  /** URL for opening receipt in new tab (includes token for auth) */
  getReceiptDownloadUrl: (transactionId: number) => {
    const token = localStorage.getItem('token')
    return `${api.defaults.baseURL}/transactions/${transactionId}/receipt${token ? `?token=${token}` : ''}`
  },
}

// AEZA provisioning (admin)
export const aezaApi = {
  products: () => api.get('/aeza/products'),
  os: () => api.get('/aeza/os'),
  createOrder: (data: { productId: string; term: string; name: string; autoProlong?: boolean }) =>
    api.post('/aeza/orders', data),
  getOrder: (orderId: string) => api.get(`/aeza/orders/${orderId}`),
  registerServer: (data: {
    order_id: string
    name: string
    flag_emoji?: string
    ip_address: string
    api_domain: string
    admin_user: string
    admin_pass: string
    capacity: number
    location_tag: string
    region: 'iran' | 'foreign'
    server_category: 'tunnel_entry' | 'tunnel_exit' | 'direct'
  }) => api.post('/aeza/register-server', data),
}

export default api

