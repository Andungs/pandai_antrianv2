import { ref, watch } from 'vue'

export type ThemeMode = 'light' | 'semi-dark' | 'dark'

// ── Singleton reactive state ────────────────────────────────────────────────
const themeMode = ref<ThemeMode>(
  (localStorage.getItem('themeMode') as ThemeMode) || 'light'
)

function applyHtmlClass(mode: ThemeMode) {
  document.documentElement.classList.toggle('dark', mode === 'dark')
}

applyHtmlClass(themeMode.value)

watch(themeMode, (mode) => {
  applyHtmlClass(mode)
  localStorage.setItem('themeMode', mode)
})

export function useTheme() {
  const setTheme = (mode: ThemeMode) => {
    themeMode.value = mode
  }

  const isDark = (section: 'hero' | 'sidebar' | 'other' = 'other'): boolean => {
    if (themeMode.value === 'dark') return true
    if (themeMode.value === 'semi-dark' && (section === 'hero' || section === 'sidebar')) return true
    return false
  }

  return { themeMode, setTheme, isDark }
}
