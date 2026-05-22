<script setup lang="ts">
import { ref, watch } from 'vue'
import { Search, RefreshCw, Database, FileText } from 'lucide-vue-next'
import DataTablePagination from '@/components/DataTablePagination.vue'
import TableSkeleton from '@/components/TableSkeleton.vue'
import { Card, CardContent } from '@/components/ui/card'

const props = defineProps<{
  // Data
  data: any[]
  loading?: boolean
  // Table columns (array of { key, label, align?, width? })
  columns: { key: string; label: string; align?: 'left' | 'center' | 'right'; width?: string }[]
  // Skeleton cols count fallback
  skeletonRows?: number
  // Pagination
  currentPage?: number
  lastPage?: number
  total?: number
  perPage?: number
  // Search
  modelValue?: string   // v-model for search
  searchPlaceholder?: string
  // Title / subtitle shown in toolbar
  title?: string
  subtitle?: string
  // Hide search or pagination
  hideSearch?: boolean
  hidePagination?: boolean
  // Empty state message
  emptyMessage?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'search', value: string): void
  (e: 'page-change', page: number): void
  (e: 'refresh'): void
}>()

const localSearch = ref(props.modelValue ?? '')
let debounceTimer: ReturnType<typeof setTimeout>

watch(() => props.modelValue, (v) => { if (v !== localSearch.value) localSearch.value = v ?? '' })

const onInput = () => {
  emit('update:modelValue', localSearch.value)
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => emit('search', localSearch.value), 350)
}
</script>

<template>
  <Card class="border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden animate-fadeup delay-150">
    <!-- ── Toolbar ── -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <slot name="icon">
          <FileText class="h-5 w-5 text-primary" />
        </slot>
        <h2 v-if="title" class="font-semibold text-slate-800 dark:text-slate-100">{{ title }}</h2>
        <span v-if="total !== undefined" class="text-xs text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">{{ total }}</span>
      </div>
      
      <div class="flex items-center gap-3 w-full sm:w-auto">
        <slot name="toolbar-actions" />
        
        <div v-if="!hideSearch" class="relative w-full sm:w-72">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
          <input
            v-model="localSearch"
            @input="onInput"
            type="text"
            :placeholder="searchPlaceholder ?? 'Cari...'"
            class="form-input-std pl-9 bg-slate-50 dark:bg-slate-900"
          />
        </div>
        
        <button v-if="!hideSearch" @click="emit('refresh')" :disabled="loading" class="h-10 w-10 shrink-0 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
          <RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" />
        </button>
      </div>
    </div>

    <CardContent class="p-0">
      <!-- ── Table ── -->
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th
                v-for="col in columns"
                :key="col.key"
                :style="col.width ? `width:${col.width}` : ''"
                :class="[col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left']"
              >
                {{ col.label }}
              </th>
            </tr>
          </thead>

          <!-- Skeleton -->
          <TableSkeleton v-if="loading" :rows="skeletonRows ?? 6" :cols="columns.length" />

          <!-- Empty -->
          <tbody v-else-if="!data.length">
            <tr>
              <td :colspan="columns.length">
                <slot name="empty">
                  <div class="flex flex-col items-center py-16 text-slate-400">
                    <Database class="h-12 w-12 opacity-20 mb-3" />
                    <p class="font-medium">{{ emptyMessage ?? (modelValue ? `Tidak ada hasil untuk "${modelValue}"` : 'Tidak ada data.') }}</p>
                  </div>
                </slot>
              </td>
            </tr>
          </tbody>

          <!-- Data rows via scoped slot -->
          <tbody v-else>
            <tr v-for="(row, index) in data" :key="row.id ?? index" class="group">
              <slot name="row" :row="row" :index="index">
                <td v-for="col in columns" :key="col.key" :class="col.align === 'right' ? 'text-right' : ''">
                  {{ row[col.key] ?? '—' }}
                </td>
              </slot>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Pagination ── -->
      <div v-if="!hidePagination && lastPage !== undefined" class="border-t border-slate-100 dark:border-slate-800">
        <DataTablePagination
          :current-page="currentPage ?? 1"
          :last-page="lastPage"
          :total="total ?? 0"
          :per-page="perPage ?? 10"
          :loading="loading"
          @page-change="emit('page-change', $event)"
        />
      </div>
    </CardContent>
  </Card>
</template>

