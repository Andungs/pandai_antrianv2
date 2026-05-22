<script setup lang="ts">
import { ref, onMounted } from 'vue'
import StatsCard from '@/components/StatsCard.vue'
import { api } from '@/stores/auth'
import { BarChart3, Users, Clock, CheckCircle2 } from 'lucide-vue-next'

const loading = ref(true)
const stats = ref({
  total: 0, waiting: 0, served: 0, skipped: 0, called: 0, avg_service_time: 0
})
const perService = ref<any[]>([])

async function fetchDashboard() {
  loading.value = true
  try {
    const res = await api.get('/admin/dashboard')
    stats.value = res.data.data.today
    perService.value = res.data.data.per_service
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white">Dashboard</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Ringkasan statistik antrean hari ini</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <StatsCard title="Total Antrean" :value="String(stats.total)" subtitle="Hari ini" variant="primary" :loading="loading" :icon="BarChart3" />
      <StatsCard title="Menunggu" :value="String(stats.waiting)" subtitle="Saat ini" variant="amber" :loading="loading" :icon="Users" />
      <StatsCard title="Sudah Dilayani" :value="String(stats.served)" subtitle="Hari ini" variant="emerald" :loading="loading" :icon="CheckCircle2" />
      <StatsCard title="Rata-rata Waktu" :value="`${stats.avg_service_time} mnt`" subtitle="Per layanan" variant="red" :loading="loading" :icon="Clock" />
    </div>

    <!-- Per Service Table -->
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/80 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800">
        <h3 class="text-sm font-bold text-slate-800 dark:text-white">Antrean Per Layanan</h3>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Layanan</th>
            <th>Prefix</th>
            <th>Total</th>
            <th>Dilayani</th>
            <th>Menunggu</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading" v-for="i in 3" :key="i">
            <td v-for="j in 5" :key="j"><div class="skeleton h-4 w-16"></div></td>
          </tr>
          <tr v-else-if="perService.length === 0">
            <td colspan="5" class="text-center text-slate-400 py-8">Belum ada data antrean hari ini</td>
          </tr>
          <tr v-for="svc in perService" :key="svc.prefix_code" v-else>
            <td class="font-semibold text-slate-800 dark:text-white">{{ svc.service_name }}</td>
            <td><span class="px-2 py-0.5 rounded-md bg-sky-100 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 text-xs font-bold">{{ svc.prefix_code }}</span></td>
            <td class="font-bold">{{ svc.total }}</td>
            <td class="text-emerald-600 dark:text-emerald-400 font-bold">{{ svc.served }}</td>
            <td class="text-amber-600 dark:text-amber-400 font-bold">{{ svc.waiting }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
