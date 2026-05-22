import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8092/api'

// Axios instance with auto token header
const api = axios.create({ baseURL: API_BASE })

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`

  // Attach WebSocket socket ID so broadcast()->toOthers() works correctly
  if ((window as any).Echo?.socketId?.()) {
    config.headers['X-Socket-ID'] = (window as any).Echo.socketId()
  }

  return config
})

export interface UserData {
  id: number
  name: string
  username: string
  role_type: 'superadmin' | 'loket'
  default_route: string
  counter?: {
    id: number
    name: string
    service_id: number
    service_name: string
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const user  = ref<UserData | null>(JSON.parse(localStorage.getItem('auth_user') ?? 'null'))
  const loading = ref(false)
  const error  = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(username: string, password: string) {
    loading.value = true
    error.value   = null
    try {
      const res = await api.post('/auth/login', { username, password })
      _setSession(res.data.token, res.data.user)
      return res.data
    } catch (e: any) {
      error.value = e.response?.data?.message ?? 'Login gagal.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchMe() {
    if (!token.value) return
    try {
      const res = await api.get('/me')
      user.value = res.data.user
      localStorage.setItem('auth_user', JSON.stringify(res.data.user))
    } catch {
      logout()
    }
  }

  async function logout() {
    try { await api.post('/auth/logout') } catch {}
    _clearSession()
  }

  function _setSession(t: string, u: UserData) {
    token.value = t
    user.value  = u
    localStorage.setItem('auth_token', t)
    localStorage.setItem('auth_user', JSON.stringify(u))
  }

  function _clearSession() {
    token.value = null
    user.value  = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
  }

  return {
    token, user, loading, error,
    isAuthenticated,
    login, fetchMe, logout,
    _setSession
  }
})

// Export axios instance for other composables
export { api }
