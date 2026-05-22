<script setup lang="ts">
import { computed } from 'vue'
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next'

const props = defineProps<{
  currentPage: number
  lastPage: number
  total: number
  perPage: number
  loading?: boolean
}>()

const emit = defineEmits<{ (e: 'page-change', page: number): void }>()

const from = computed(() => props.total === 0 ? 0 : (props.currentPage - 1) * props.perPage + 1)
const to   = computed(() => Math.min(props.currentPage * props.perPage, props.total))

const pages = computed(() => {
  const p: (number | '...')[] = []
  const last = props.lastPage
  if (last <= 7) {
    for (let i = 1; i <= last; i++) p.push(i)
  } else {
    p.push(1)
    if (props.currentPage > 4) p.push('...')
    const start = Math.max(2, props.currentPage - 1)
    const end   = Math.min(last - 1, props.currentPage + 1)
    for (let i = start; i <= end; i++) p.push(i)
    if (props.currentPage < last - 3) p.push('...')
    p.push(last)
  }
  return p
})

const go = (page: number) => {
  if (page < 1 || page > props.lastPage || props.loading) return
  emit('page-change', page)
}
</script>

<template>
  <div class="pag-root">
    <!-- Info -->
    <p class="pag-info">
      Menampilkan
      <strong>{{ from }}–{{ to }}</strong>
      dari
      <strong>{{ total }}</strong>
      data
    </p>

    <!-- Controls -->
    <nav class="pag-nav">
      <!-- First -->
      <button class="pag-btn" @click="go(1)" :disabled="currentPage === 1 || loading" title="Halaman pertama">
        <ChevronsLeft class="h-3.5 w-3.5" />
      </button>
      <!-- Prev -->
      <button class="pag-btn" @click="go(currentPage - 1)" :disabled="currentPage === 1 || loading">
        <ChevronLeft class="h-4 w-4" />
      </button>

      <!-- Pages -->
      <template v-for="p in pages" :key="String(p)">
        <button
          v-if="p !== '...'"
          class="pag-page"
          :class="p === currentPage ? 'pag-page--active' : ''"
          :disabled="loading"
          @click="go(p as number)"
        >{{ p }}</button>
        <span v-else class="pag-dots">…</span>
      </template>

      <!-- Next -->
      <button class="pag-btn" @click="go(currentPage + 1)" :disabled="currentPage === lastPage || loading">
        <ChevronRight class="h-4 w-4" />
      </button>
      <!-- Last -->
      <button class="pag-btn" @click="go(lastPage)" :disabled="currentPage === lastPage || loading" title="Halaman terakhir">
        <ChevronsRight class="h-3.5 w-3.5" />
      </button>
    </nav>
  </div>
</template>

<style scoped>
.pag-root {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.75rem 1rem; gap: 1rem; flex-wrap: wrap;
}

.pag-info {
  font-size: 0.78rem; color: #94a3b8;
}
.pag-info strong { color: #475569; font-weight: 700; }
:global(.dark) .pag-info strong { color: #94a3b8; }

.pag-nav { display: flex; align-items: center; gap: 0.3rem; }

.pag-btn {
  height: 32px; width: 32px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid #e2e8f0; border-radius: 9px;
  color: #64748b; background: white;
  cursor: pointer; transition: all 0.15s;
}
.pag-btn:hover:not(:disabled) { border-color: #3b82f6; color: #3b82f6; background: rgba(59,130,246,0.06); }
.pag-btn:disabled { opacity: 0.35; cursor: not-allowed; }
:global(.dark) .pag-btn { background: transparent; border-color: rgba(255,255,255,0.08); color: #64748b; }
:global(.dark) .pag-btn:hover:not(:disabled) { border-color: #3b82f6; color: #3b82f6; }

.pag-page {
  height: 32px; min-width: 32px; padding: 0 0.5rem;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.82rem; font-weight: 600;
  border: 1px solid #e2e8f0; border-radius: 9px;
  color: #475569; background: white;
  cursor: pointer; transition: all 0.15s;
}
.pag-page:hover:not(:disabled):not(.pag-page--active) {
  border-color: #3b82f6; color: #3b82f6; background: rgba(59,130,246,0.05);
}
.pag-page--active {
  background: #3b82f6 !important; color: white !important;
  border-color: #3b82f6 !important;
  box-shadow: 0 2px 8px rgba(59,130,246,0.35);
}
:global(.dark) .pag-page { background: transparent; border-color: rgba(255,255,255,0.08); color: #64748b; }

.pag-dots {
  display: flex; align-items: center; justify-content: center;
  width: 28px; font-size: 0.82rem; color: #94a3b8;
}

@media (max-width: 480px) {
  .pag-root { justify-content: center; }
  .pag-info { display: none; }
}
</style>
