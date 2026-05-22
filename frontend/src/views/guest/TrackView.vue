<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { TicketCheck, Clock, CheckCircle2, XCircle, Phone, ArrowLeft } from 'lucide-vue-next'
import axios from 'axios'
import { echo } from '@/lib/echo'

const route = useRoute()
const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8092/api'

interface TrackData {
  id: number
  queue_number: string
  service_name: string
  counter_name: string | null
  status: string
  position: number
  called_at: string | null
  served_at: string | null
  created_at: string
}

const trackData = ref<TrackData | null>(null)
const loading = ref(true)
const notFound = ref(false)

const queueNumber = computed(() => route.params.queueNumber as string)

const statusConfig: Record<string, { label: string; color: string; bgColor: string; icon: any }> = {
  menunggu: { label: 'Menunggu', color: 'text-amber-500', bgColor: 'bg-amber-500/10 border-amber-500/20', icon: Clock },
  dipanggil: { label: 'Dipanggil', color: 'text-sky-500', bgColor: 'bg-sky-500/10 border-sky-500/20', icon: Phone },
  dilayani: { label: 'Dilayani', color: 'text-emerald-500', bgColor: 'bg-emerald-500/10 border-emerald-500/20', icon: CheckCircle2 },
  terlewat: { label: 'Terlewat', color: 'text-red-500', bgColor: 'bg-red-500/10 border-red-500/20', icon: XCircle },
}

const currentStatus = computed(() => statusConfig[trackData.value?.status ?? 'menunggu']!)

async function fetchTrack() {
  try {
    const res = await axios.get(`${API_BASE}/guest/queues/${queueNumber.value}/track`)
    trackData.value = res.data.data
    notFound.value = false
  } catch (e: any) {
    if (e.response?.status === 404) notFound.value = true
  } finally {
    loading.value = false
  }
}

// WebSocket listener
let echoChannel: any = null

onMounted(() => {
  fetchTrack()

  echoChannel = echo.channel('queue.updates')
  echoChannel.listen('.App\\Events\\QueueUpdated', (data: any) => {
    if (data.queue_number === queueNumber.value) {
      if (trackData.value) {
        trackData.value.status = data.status
        trackData.value.counter_name = data.counter_name
        trackData.value.called_at = data.called_at
        trackData.value.served_at = data.served_at
      }
    }
  })

  onUnmounted(() => {
    if (echoChannel) {
      echoChannel.stopListening('.App\\Events\\QueueUpdated')
      echo.leaveChannel('queue.updates')
    }
  })
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-sky-50/30 to-blue-50/20 flex items-center justify-center p-4 font-sans">
    
    <!-- Loading -->
    <div v-if="loading" class="text-center">
      <div class="h-16 w-16 mx-auto rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shadow-lg shadow-sky-500/30 animate-pulse mb-4">
        <TicketCheck class="h-8 w-8 text-white" />
      </div>
      <p class="text-slate-500 font-medium">Memuat status antrean...</p>
    </div>

    <!-- Not Found -->
    <div v-else-if="notFound" class="text-center max-w-sm">
      <div class="h-16 w-16 mx-auto rounded-2xl bg-slate-200 flex items-center justify-center mb-4">
        <XCircle class="h-8 w-8 text-slate-400" />
      </div>
      <h2 class="text-xl font-bold text-slate-800 mb-2">Antrean Tidak Ditemukan</h2>
      <p class="text-slate-500 text-sm">Nomor antrean <strong>{{ queueNumber }}</strong> tidak ditemukan untuk hari ini.</p>
    </div>

    <!-- Track Card -->
    <div v-else-if="trackData" class="w-full max-w-sm">
      <div class="bg-white rounded-3xl border-2 border-slate-200 shadow-xl overflow-hidden">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-sky-500 to-blue-600 p-6 text-center text-white">
          <div class="flex items-center justify-center gap-2 mb-3">
            <TicketCheck class="h-5 w-5" />
            <span class="text-sm font-bold opacity-80">Status Antrean</span>
          </div>
          <h1 class="text-5xl font-black tracking-tighter">{{ trackData.queue_number }}</h1>
          <p class="text-sm font-medium text-white/70 mt-1">{{ trackData.service_name }}</p>
        </div>

        <!-- Status Badge -->
        <div class="px-6 -mt-4">
          <div class="flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border-2 bg-white shadow-sm" :class="currentStatus.bgColor">
            <component :is="currentStatus.icon" class="h-5 w-5" :class="currentStatus.color" />
            <span class="font-bold text-base" :class="currentStatus.color">{{ currentStatus.label }}</span>
          </div>
        </div>

        <!-- Details -->
        <div class="p-6 space-y-3">
          <div v-if="trackData.status === 'menunggu' && trackData.position > 0" class="flex items-center justify-between px-4 py-3 rounded-xl bg-amber-50 border border-amber-100">
            <span class="text-sm text-amber-700 font-medium">Posisi Antrean</span>
            <span class="text-lg font-black text-amber-600">Ke-{{ trackData.position }}</span>
          </div>

          <div v-if="trackData.counter_name" class="flex items-center justify-between px-4 py-3 rounded-xl bg-sky-50 border border-sky-100">
            <span class="text-sm text-sky-700 font-medium">Menuju Loket</span>
            <span class="text-sm font-bold text-sky-600">{{ trackData.counter_name }}</span>
          </div>

          <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-slate-50">
            <span class="text-sm text-slate-500 font-medium">Waktu Ambil</span>
            <span class="text-sm font-bold text-slate-700">
              {{ new Date(trackData.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
            </span>
          </div>

          <div v-if="trackData.called_at" class="flex items-center justify-between px-4 py-3 rounded-xl bg-slate-50">
            <span class="text-sm text-slate-500 font-medium">Waktu Panggil</span>
            <span class="text-sm font-bold text-slate-700">
              {{ new Date(trackData.called_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
            </span>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 pb-6">
          <p class="text-center text-xs text-slate-400 font-medium">
            Status diperbarui secara otomatis
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
