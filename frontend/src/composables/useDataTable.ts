import { ref } from 'vue'
import { api } from '@/stores/auth'

export interface DataTableOptions {
  url: string
  perPageDefault?: number
}

export function useDataTable(options: DataTableOptions) {
  const data = ref<any[]>([])
  const loading = ref(false)
  const search = ref('')
  const currentPage = ref(1)
  const perPage = ref(options.perPageDefault ?? 10)
  const total = ref(0)
  const lastPage = ref(1)

  let searchTimer: ReturnType<typeof setTimeout> | null = null

  const fetchData = async () => {
    loading.value = true
    try {
      const res = await api.get(options.url, {
        params: {
          search: search.value || undefined,
          page: currentPage.value,
          per_page: perPage.value,
        }
      })
      // Support both paginated (res.data.data.data) and plain array (res.data.data)
      if (res.data?.data?.data) {
        data.value = res.data.data.data
        total.value = res.data.data.total
        lastPage.value = res.data.data.last_page
        currentPage.value = res.data.data.current_page
      } else {
        data.value = res.data.data ?? []
        total.value = data.value.length
        lastPage.value = 1
      }
    } finally {
      loading.value = false
    }
  }

  const onSearch = () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      currentPage.value = 1
      fetchData()
    }, 350)
  }

  const goToPage = (page: number) => {
    if (page < 1 || page > lastPage.value) return
    currentPage.value = page
    fetchData()
  }

  const refresh = () => fetchData()

  return {
    data,
    loading,
    search,
    currentPage,
    perPage,
    total,
    lastPage,
    fetchData,
    onSearch,
    goToPage,
    refresh,
  }
}
