<script setup lang="ts">
import { onMounted } from 'vue'
import DataTable from '@/components/DataTable.vue'
import { useDataTable } from '@/composables/useDataTable'

const { data: queues, loading, search, currentPage, perPage, total, lastPage, fetchData, onSearch, goToPage, refresh } = useDataTable({ url: '/loket/queues/history', perPageDefault: 20 })

const columns = [
  { key: 'queue_number', label: 'No. Antrean' },
  { key: 'service_name', label: 'Layanan' },
  { key: 'status', label: 'Status' },
  { key: 'called_at', label: 'Dipanggil' },
  { key: 'served_at', label: 'Selesai' },
]

const statusColor = (status: string) => {
  const map: Record<string, string> = {
    dipanggil: 'bg-sky-100 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400',
    dilayani: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400',
    terlewat: 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400',
    menunggu: 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400',
  }
  return map[status] ?? 'bg-slate-100 text-slate-600'
}

const formatTime = (dt: string | null) => {
  if (!dt) return '-'
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

onMounted(fetchData)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white">Riwayat Antrean</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Histori antrean yang sudah dilayani di loket Anda</p>
    </div>

    <DataTable
      :data="queues"
      :loading="loading"
      :columns="columns"
      v-model="search"
      :current-page="currentPage"
      :per-page="perPage"
      :total="total"
      :last-page="lastPage"
      @search="onSearch"
      @page-change="goToPage"
      @refresh="refresh"
      search-placeholder="Cari nomor antrean..."
    >
      <template #row="{ row }">
        <td class="font-black text-slate-800 dark:text-white">{{ row.queue_number }}</td>
        <td class="text-slate-600 dark:text-slate-400">{{ row.service_name }}</td>
        <td>
          <span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="statusColor(row.status)">
            {{ row.status }}
          </span>
        </td>
        <td class="text-slate-600 dark:text-slate-400 text-xs">{{ formatTime(row.called_at) }}</td>
        <td class="text-slate-600 dark:text-slate-400 text-xs">{{ formatTime(row.served_at) }}</td>
      </template>
    </DataTable>
  </div>
</template>
