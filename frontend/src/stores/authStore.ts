import { create } from 'zustand'
import axios from 'axios'

interface User {
  id: number
  username: string
  email: string
  role: 'admin' | 'reseller' | 'affiliate' | 'user'
  credit_limit?: number
  current_debt?: number
}

interface AuthState {
  user: User | null
  token: string | null
  isSetupComplete: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => void
  checkSetup: () => Promise<void>
  checkAuth: () => Promise<void>
}

const API_URL = import.meta.env.VITE_API_URL || 'http://api.localhost'

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  token: localStorage.getItem('token'),
  isSetupComplete: true,

  checkSetup: async () => {
    try {
      const response = await axios.get(`${API_URL}/api/setup/status`)
      set({ isSetupComplete: response.data.setup_complete })
    } catch (error) {
      // If API is not available, assume setup is needed
      set({ isSetupComplete: false })
    }
  },

  checkAuth: async () => {
    const token = localStorage.getItem('token')
    if (!token) return

    try {
      const response = await axios.get(`${API_URL}/api/auth/me`, {
        headers: { Authorization: `Bearer ${token}` },
      })
      set({ user: response.data, token })
    } catch (error) {
      localStorage.removeItem('token')
      set({ user: null, token: null })
    }
  },

  login: async (email: string, password: string) => {
    const response = await axios.post(`${API_URL}/api/auth/login`, {
      email,
      password,
    })
    const { user, token } = response.data
    localStorage.setItem('token', token)
    set({ user, token })
  },

  logout: () => {
    localStorage.removeItem('token')
    set({ user: null, token: null })
  },
}))

