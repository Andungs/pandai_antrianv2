import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
  interface Window { Pusher: typeof Pusher }
}

window.Pusher = Pusher

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8092/api'

export const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
  wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
  enabledTransports: ['ws', 'wss'],
  authEndpoint: API_BASE + '/broadcasting/auth',
  auth: {
    headers: {
      get Authorization() {
        return `Bearer ${localStorage.getItem('auth_token')}`
      }
    }
  }
})

// Expose Echo to window so axios interceptor can read socketId()
;(window as any).Echo = echo
