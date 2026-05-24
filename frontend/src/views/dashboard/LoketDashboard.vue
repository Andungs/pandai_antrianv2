<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { api, useAuthStore } from '@/stores/auth'
import {
  PhoneForwarded, PhoneCall, CheckCircle2, SkipForward,
  RotateCcw, Users, Clock, Loader2, TicketCheck, Monitor
} from 'lucide-vue-next'
import Swal from 'sweetalert2'
import { echo } from '@/lib/echo'

const auth = useAuthStore()
const loading = ref(true)
const actionLoading = ref(false)

interface CounterInfo { id: number; name: string; service_name: string }
interface CurrentQueue {
  id: number
  queue_number: string
  service_name: string
  status: string
  called_at: string
  served_at: string | null
  photo_url: string | null
}

const counterInfo = ref<CounterInfo | null>(null)
const currentQueue = ref<CurrentQueue | null>(null)
const waitingCount = ref(0)
const history = ref<any[]>([])

// Admin counter selection
const showCounterSelect = ref(false)
const availableCounters = ref<any[]>([])
const isAdmin = computed(() => auth.user?.role_type === 'superadmin')

const statusLabel = computed(() => {
  if (!currentQueue.value) return ''
  const map: Record<string, string> = {
    dipanggil: 'Dipanggil',
    dilayani: 'Sedang Dilayani',
  }
  return map[currentQueue.value.status] ?? currentQueue.value.status
})

const statusBadgeClass = computed(() => {
  if (!currentQueue.value) return ''
  return currentQueue.value.status === 'dilayani'
    ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-700/40'
    : 'bg-sky-100 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 border-sky-200 dark:border-sky-700/40'
})

async function fetchCurrent() {
  try {
    const res = await api.get('/loket/queues/current')
    counterInfo.value = res.data.data.counter
    currentQueue.value = res.data.data.current_queue
    waitingCount.value = res.data.data.waiting_count
  } catch (e: any) {
    if (e.response?.status === 422) {
      // Belum assign ke counter (atau belum memilih)
      loadAvailableCounters()
      showCounterSelect.value = true
    }
  } finally { loading.value = false }
}

async function fetchHistory() {
  try {
    const res = await api.get('/loket/queues/history')
    history.value = res.data.data
  } catch (e) { console.error(e) }
}

async function loadAvailableCounters() {
  try {
    const res = await api.get('/loket/available-counters')
    availableCounters.value = res.data.data
  } catch (e) { console.error(e) }
}

async function selectCounter(counterId: number) {
  try {
    const res = await api.post(`/loket/select-counter/${counterId}`)
    Swal.fire('Berhasil', res.data.message, 'success')
    showCounterSelect.value = false
    fetchCurrent()
    fetchHistory()
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal memilih loket', 'error')
  }
}

async function nextCall() {
  actionLoading.value = true
  try {
    const res = await api.post('/loket/queues/next')
    if (res.data.data) {
      currentQueue.value = {
        id: res.data.data.id,
        queue_number: res.data.data.queue_number,
        service_name: res.data.data.service_name,
        status: 'dipanggil',
        called_at: res.data.data.called_at,
        served_at: null,
        photo_url: null,
      }
      waitingCount.value = res.data.data.waiting_count
    } else {
      Swal.fire('Info', res.data.message, 'info')
    }
    fetchHistory()
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal memanggil', 'error')
  } finally { actionLoading.value = false }
}

async function recallCurrent() {
  if (!currentQueue.value) return
  actionLoading.value = true
  try {
    await api.post(`/loket/queues/${currentQueue.value.id}/recall`)
    // Reset status to dipanggil (re-announce on display)
    if (currentQueue.value) {
      currentQueue.value.status = 'dipanggil'
      currentQueue.value.called_at = new Date().toISOString()
    }
    fetchHistory()
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal recall', 'error')
  } finally { actionLoading.value = false }
}

async function recallFromHistory(id: number) {
  actionLoading.value = true
  try {
    const res = await api.post(`/loket/queues/${id}/recall`)
    currentQueue.value = {
      id: res.data.data.id,
      queue_number: res.data.data.queue_number,
      service_name: res.data.data.service_name,
      status: 'dipanggil',
      called_at: res.data.data.called_at,
      served_at: null,
      photo_url: null,
    }
    fetchHistory()
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal recall', 'error')
  } finally { actionLoading.value = false }
}

async function serveQueue() {
  if (!currentQueue.value) return
  actionLoading.value = true
  try {
    const res = await api.post(`/loket/queues/${currentQueue.value.id}/serve`)
    // Keep the queue visible but update status to 'dilayani'
    if (currentQueue.value && res.data.data) {
      currentQueue.value.status = 'dilayani'
      currentQueue.value.served_at = res.data.data.served_at
    }
    waitingCount.value = res.data.data.waiting_count
    fetchHistory()
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal', 'error')
  } finally { actionLoading.value = false }
}

async function skipQueue() {
  if (!currentQueue.value) return
  const result = await Swal.fire({
    title: 'Lewati Antrean?',
    text: `Nomor ${currentQueue.value.queue_number} akan ditandai sebagai terlewat.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Lewati',
    cancelButtonText: 'Batal'
  })
  if (!result.isConfirmed) return
  actionLoading.value = true
  try {
    await api.post(`/loket/queues/${currentQueue.value.id}/skip`)
    currentQueue.value = null
    fetchCurrent()
    fetchHistory()
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal', 'error')
  } finally { actionLoading.value = false }
}

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
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

let echoChannel: any = null

onMounted(() => {
  fetchCurrent()
  fetchHistory()

  // Listen for real-time queue updates to refresh waiting count
  echoChannel = echo.channel('queue.updates')
  echoChannel.listen('.App\\Events\\QueueUpdated', () => {
    fetchCurrent()
    fetchHistory()
  })
  echoChannel.listen('.App\\Events\\QueueCalled', () => {
    fetchCurrent()
    fetchHistory()
  })
})

onUnmounted(() => {
  if (echoChannel) {
    echoChannel.stopListening('.App\\Events\\QueueUpdated')
    echoChannel.stopListening('.App\\Events\\QueueCalled')
    echo.leaveChannel('queue.updates')
  }
})
</script>

<template>
  <!-- Counter Selection for Admin / Loket -->
  <div v-if="showCounterSelect" class="space-y-6">
    <div>
      <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white">Pilih Loket</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Pilih loket yang ingin Anda operasikan</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <button
        v-for="counter in availableCounters"
        :key="counter.id"
        @click="selectCounter(counter.id)"
        class="group rounded-2xl border-2 border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/80 p-6 text-left hover:border-sky-400 dark:hover:border-sky-600 hover:shadow-lg transition-all"
      >
        <div class="flex items-center gap-3 mb-3">
          <div class="h-10 w-10 rounded-xl bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
            <Monitor class="h-5 w-5 text-sky-600 dark:text-sky-400" />
          </div>
          <div>
            <p class="font-bold text-slate-800 dark:text-white">{{ counter.name }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ counter.service_name }}</p>
          </div>
        </div>
      </button>
    </div>
    <div v-if="availableCounters.length === 0" class="text-center p-8 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
        <p class="text-slate-500 dark:text-slate-400">Anda belum di-assign ke loket manapun. Silakan hubungi Administrator.</p>
    </div>
  </div>

  <!-- Main Loket Dashboard -->
  <div class="space-y-6" v-else-if="!loading">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white">Dashboard Loket</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          <span v-if="counterInfo">{{ counterInfo.name }} — Layanan: {{ counterInfo.service_name }}</span>
          <span v-else>Belum memilih loket</span>
        </p>
      </div>
      <div class="flex items-center gap-3">
        <!-- Change counter button -->
        <button
          @click="showCounterSelect = true; loadAvailableCounters()"
          class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all"
        >
          <Monitor class="h-4 w-4" />
          Ganti Loket
        </button>
        <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40">
          <Users class="h-4 w-4 text-amber-600 dark:text-amber-400" />
          <span class="text-sm font-bold text-amber-700 dark:text-amber-300">{{ waitingCount }} menunggu</span>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- ═══ LEFT: Current Queue + Actions ════════════════════════════ -->
      <div class="lg:col-span-2 space-y-5">
        <!-- Current Queue Card -->
        <div class="rounded-3xl border-2 bg-white dark:bg-slate-900/80 overflow-hidden"
             :class="currentQueue
               ? (currentQueue.status === 'dilayani' ? 'border-emerald-200 dark:border-emerald-800/40' : 'border-sky-200 dark:border-sky-800/40')
               : 'border-slate-200 dark:border-slate-800'">
          <div v-if="currentQueue" class="p-8 text-center">
            <!-- Status Badge -->
            <div class="flex justify-center mb-4">
              <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-bold border" :class="statusBadgeClass">
                <CheckCircle2 v-if="currentQueue.status === 'dilayani'" class="h-4 w-4" />
                <PhoneCall v-else class="h-4 w-4" />
                {{ statusLabel }}
              </span>
            </div>
            <h2 class="text-7xl sm:text-8xl font-black tracking-tighter leading-none mb-4 animate-number-pop"
                :class="currentQueue.status === 'dilayani'
                  ? 'text-transparent bg-clip-text bg-gradient-to-br from-emerald-500 to-teal-600'
                  : 'text-transparent bg-clip-text bg-gradient-to-br from-sky-500 to-blue-600'">
              {{ currentQueue.queue_number }}
            </h2>
            <p class="text-lg font-semibold text-slate-600 dark:text-slate-400">{{ currentQueue.service_name }}</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-2 flex items-center justify-center gap-1">
              <Clock class="h-3 w-3" />
              Dipanggil {{ formatTime(currentQueue.called_at) }}
              <span v-if="currentQueue.served_at" class="ml-2">• Dilayani {{ formatTime(currentQueue.served_at) }}</span>
            </p>
          </div>
          <div v-else class="p-12 text-center">
            <div class="h-20 w-20 mx-auto rounded-3xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
              <TicketCheck class="h-10 w-10 text-slate-300 dark:text-slate-600" />
            </div>
            <p class="text-lg font-semibold text-slate-400 dark:text-slate-600">Belum ada antrean dipanggil</p>
            <p class="text-sm text-slate-400 dark:text-slate-600 mt-1">Tekan "Panggil Berikutnya" untuk memulai</p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <!-- Panggil Berikutnya -->
          <button
            @click="nextCall"
            :disabled="actionLoading"
            class="h-14 rounded-2xl bg-gradient-to-r from-sky-500 to-blue-600 text-white font-bold text-sm shadow-lg shadow-sky-500/25 hover:shadow-xl hover:shadow-sky-500/30 transition-all active:scale-[0.97] flex items-center justify-center gap-2 disabled:opacity-50"
          >
            <Loader2 v-if="actionLoading" class="h-4 w-4 animate-spin" />
            <PhoneForwarded v-else class="h-4 w-4" />
            Panggil Berikutnya
          </button>
          <!-- Panggil Ulang (next to Panggil Berikutnya — re-announce on display) -->
          <button
            v-if="currentQueue && currentQueue.status === 'dipanggil'"
            @click="recallCurrent"
            :disabled="actionLoading"
            class="h-14 rounded-2xl bg-amber-500/10 border-2 border-amber-500/30 text-amber-600 dark:text-amber-400 font-bold text-sm hover:bg-amber-500/15 transition-all active:scale-[0.97] flex items-center justify-center gap-2 disabled:opacity-50"
          >
            <RotateCcw class="h-4 w-4" />
            Panggil Ulang
          </button>
          <!-- Layani -->
          <button
            v-if="currentQueue && currentQueue.status === 'dipanggil'"
            @click="serveQueue"
            :disabled="actionLoading"
            class="h-14 rounded-2xl bg-emerald-500/10 border-2 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 font-bold text-sm hover:bg-emerald-500/15 transition-all active:scale-[0.97] flex items-center justify-center gap-2 disabled:opacity-50"
          >
            <CheckCircle2 class="h-4 w-4" />
            Layani
          </button>
          <!-- Lewati -->
          <button
            v-if="currentQueue && currentQueue.status === 'dipanggil'"
            @click="skipQueue"
            :disabled="actionLoading"
            class="h-14 rounded-2xl bg-red-500/10 border-2 border-red-500/30 text-red-600 dark:text-red-400 font-bold text-sm hover:bg-red-500/15 transition-all active:scale-[0.97] flex items-center justify-center gap-2 disabled:opacity-50"
          >
            <SkipForward class="h-4 w-4" />
            Lewati
          </button>
        </div>
      </div>

      <!-- ═══ RIGHT: History ════════════════════════════════════════════ -->
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/80 overflow-hidden flex flex-col max-h-[600px]">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 shrink-0">
          <h3 class="text-sm font-bold text-slate-800 dark:text-white">Riwayat Hari Ini</h3>
        </div>
        <div class="flex-1 overflow-y-auto scrollbar-thin">
          <div v-if="history.length === 0" class="p-8 text-center">
            <p class="text-sm text-slate-400 dark:text-slate-600">Belum ada riwayat</p>
          </div>
          <div v-for="item in history" :key="item.id" class="flex items-center justify-between px-5 py-3 border-b border-slate-100 dark:border-slate-800/60 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
            <div class="flex items-center gap-3">
              <span class="font-black text-slate-800 dark:text-white text-sm">{{ item.queue_number }}</span>
              <span class="px-2 py-0.5 rounded-md text-[10px] font-bold" :class="statusColor(item.status)">
                {{ item.status }}
              </span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-400 dark:text-slate-500">{{ formatTime(item.called_at) }}</span>
              <button
                v-if="item.status === 'terlewat'"
                @click="recallFromHistory(item.id)"
                class="ml-1 p-1.5 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20 text-amber-500 transition-colors"
                title="Panggil Ulang"
              >
                <RotateCcw class="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Loading state -->
  <div v-else class="flex items-center justify-center h-64">
    <Loader2 class="h-8 w-8 animate-spin text-sky-500" />
  </div>
</template>
