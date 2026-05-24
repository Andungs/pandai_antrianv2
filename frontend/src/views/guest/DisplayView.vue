<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { TicketCheck, Monitor, Volume2, VolumeX, PhoneCall, CheckCircle2 } from 'lucide-vue-next'
import axios from 'axios'
import { echo } from '@/lib/echo'

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8092/api'

interface CounterDisplay {
  id: number
  name: string
  service_name: string
  officer_name: string | null
  current_queue: {
    queue_number: string
    status: string
    called_at: string
  } | null
}

interface DisplaySettings {
  app_name: string
  app_logo: string | null
}

// State
const counters = ref<CounterDisplay[]>([])
const displaySettings = ref<DisplaySettings>({ app_name: 'Pandai Antrian', app_logo: null })
const currentTime = ref(new Date())
const loading = ref(true)

// Sound activation (browser autoplay policy requires user gesture)
const soundEnabled = ref(true)

// The counter that is currently calling (most recent call event)
const activeCallCounter = ref<{ counter_id: number; counter_name: string; queue_number: string; service_name: string } | null>(null)

// Animations
const flashingCounterId = ref<number | null>(null)
const isSpeaking = ref(false)



// Time
const formattedTime = computed(() =>
  currentTime.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
)
const formattedDate = computed(() =>
  currentTime.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

// Counter card colors (cycle through)
const counterColors = [
  { gradient: 'from-sky-500 to-blue-600', glow: 'shadow-sky-500/25', lightBg: 'bg-sky-50', border: 'border-sky-200', text: 'text-sky-700', badgeBg: 'bg-sky-100', iconBg: 'bg-sky-500' },
  { gradient: 'from-violet-500 to-purple-600', glow: 'shadow-violet-500/25', lightBg: 'bg-violet-50', border: 'border-violet-200', text: 'text-violet-700', badgeBg: 'bg-violet-100', iconBg: 'bg-violet-500' },
  { gradient: 'from-emerald-500 to-teal-600', glow: 'shadow-emerald-500/25', lightBg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-700', badgeBg: 'bg-emerald-100', iconBg: 'bg-emerald-500' },
  { gradient: 'from-amber-500 to-orange-600', glow: 'shadow-amber-500/25', lightBg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700', badgeBg: 'bg-amber-100', iconBg: 'bg-amber-500' },
  { gradient: 'from-rose-500 to-pink-600', glow: 'shadow-rose-500/25', lightBg: 'bg-rose-50', border: 'border-rose-200', text: 'text-rose-700', badgeBg: 'bg-rose-100', iconBg: 'bg-rose-500' },
  { gradient: 'from-cyan-500 to-indigo-600', glow: 'shadow-cyan-500/25', lightBg: 'bg-cyan-50', border: 'border-cyan-200', text: 'text-cyan-700', badgeBg: 'bg-cyan-100', iconBg: 'bg-cyan-500' },
]
const getColor = (i: number) => counterColors[i % counterColors.length]!

// Status helpers
const statusLabel = (status: string) => {
  const map: Record<string, string> = { dipanggil: 'Dipanggil', dilayani: 'Dilayani' }
  return map[status] ?? status
}

const statusBadge = (status: string) => {
  return status === 'dilayani'
    ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
    : 'bg-sky-100 text-sky-700 border-sky-200 animate-pulse'
}



// Fetch display data
async function fetchDisplay() {
  try {
    const res = await axios.get(`${API_BASE}/guest/display`)
    counters.value = res.data.data.counters
    displaySettings.value = res.data.data.settings

    // Set active call to the most recently called counter
    const calling = counters.value.find(c => c.current_queue?.status === 'dipanggil')
    if (calling && calling.current_queue) {
      activeCallCounter.value = {
        counter_id: calling.id,
        counter_name: calling.name,
        queue_number: calling.current_queue.queue_number,
        service_name: calling.service_name,
      }
    }
  } catch (e) {
    console.error('Failed to fetch display:', e)
  } finally {
    loading.value = false
  }
}

// TTS — with sound gate
function speakAnnouncement(queueNumber: string, counterName: string) {
  if (!('speechSynthesis' in window)) return
  if (!soundEnabled.value) return

  window.speechSynthesis.cancel()
  isSpeaking.value = true

  const spokenNumber = queueNumber
    .split('')
    .map(c => {
      const d: Record<string, string> = { '0': 'nol', '1': 'satu', '2': 'dua', '3': 'tiga', '4': 'empat', '5': 'lima', '6': 'enam', '7': 'tujuh', '8': 'delapan', '9': 'sembilan' }
      return d[c] ?? c
    })
    .join(' ')

  const text = `Nomor antrean ${spokenNumber}, silakan menuju ${counterName}`

  const speak = (times: number) => {
    const utterance = new SpeechSynthesisUtterance(text)
    utterance.lang = 'id-ID'
    utterance.rate = 0.85
    utterance.pitch = 1.0
    utterance.volume = 1.0
    const voices = window.speechSynthesis.getVoices()
    const idVoice = voices.find(v => v.lang.startsWith('id'))
    if (idVoice) utterance.voice = idVoice
    utterance.onend = () => {
      if (times > 1) setTimeout(() => speak(times - 1), 1200)
      else isSpeaking.value = false
    }
    utterance.onerror = () => { isSpeaking.value = false }
    window.speechSynthesis.speak(utterance)
  }
  speak(2)
}

function triggerFlash(counterId: number) {
  flashingCounterId.value = counterId
  setTimeout(() => { flashingCounterId.value = null }, 6000)
}

// Handle broadcast event
function handleQueueCalled(data: { queue_number: string; counter_id: number; counter_name: string; service_name: string; action: string }) {
  const counter = counters.value.find(c => c.id === data.counter_id)
  if (counter) {
    counter.current_queue = {
      queue_number: data.queue_number,
      status: 'dipanggil',
      called_at: new Date().toISOString(),
    }
  }

  activeCallCounter.value = {
    counter_id: data.counter_id,
    counter_name: data.counter_name,
    queue_number: data.queue_number,
    service_name: data.service_name,
  }

  triggerFlash(data.counter_id)
  speakAnnouncement(data.queue_number, data.counter_name)
}

// Handle queue status update
function handleQueueUpdated(data: { id: number; queue_number: string; status: string; counter_name: string | null }) {
  const counter = counters.value.find(c => c.current_queue?.queue_number === data.queue_number)
  if (counter && counter.current_queue) {
    counter.current_queue.status = data.status
  }
  if (data.status === 'terlewat') {
    if (activeCallCounter.value?.queue_number === data.queue_number) {
      activeCallCounter.value = null
    }
    if (counter) counter.current_queue = null
  }
}

let echoChannel: any = null
let echoUpdatesChannel: any = null

onMounted(() => {
  fetchDisplay()
  const refreshInterval = setInterval(fetchDisplay, 30000)
  const clockInterval = setInterval(() => { currentTime.value = new Date() }, 1000)

  if ('speechSynthesis' in window) window.speechSynthesis.getVoices()

  echoChannel = echo.channel('queue.display')
  echoChannel.listen('.App\\Events\\QueueCalled', handleQueueCalled)

  echoUpdatesChannel = echo.channel('queue.updates')
  echoUpdatesChannel.listen('.App\\Events\\QueueUpdated', handleQueueUpdated)

  onUnmounted(() => {
    clearInterval(refreshInterval)
    clearInterval(clockInterval)
    if (echoChannel) {
      echoChannel.stopListening('.App\\Events\\QueueCalled')
      echo.leaveChannel('queue.display')
    }
    if (echoUpdatesChannel) {
      echoUpdatesChannel.stopListening('.App\\Events\\QueueUpdated')
      echo.leaveChannel('queue.updates')
    }
  })
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-sky-50/50 text-slate-900 font-sans overflow-hidden relative flex flex-col">



    <!-- ═══ HEADER ══════════════════════════════════════════════════════ -->
    <header class="relative z-10 flex items-center justify-between px-8 py-5 bg-white/80 backdrop-blur-sm border-b border-slate-200 shadow-sm">
      <div class="flex items-center gap-4">
        <div v-if="displaySettings.app_logo" class="h-14 w-14 rounded-2xl overflow-hidden shadow-md">
          <img :src="displaySettings.app_logo" alt="Logo" class="h-full w-full object-contain" />
        </div>
        <div v-else class="h-14 w-14 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shadow-lg shadow-sky-500/30">
          <TicketCheck class="h-7 w-7 text-white" />
        </div>
        <div>
          <h1 class="text-2xl font-black tracking-tight text-slate-800">{{ displaySettings.app_name }}</h1>
          <p class="text-sm text-slate-500 font-medium flex items-center gap-2">
            <Monitor class="h-3.5 w-3.5" />
            Display Antrean
          </p>
        </div>
      </div>
      <div class="flex items-center gap-6">
        <!-- Sound status -->
        <button
          @click="soundEnabled = !soundEnabled"
          class="flex items-center gap-2 px-4 py-2 rounded-xl transition-all"
          :class="soundEnabled
            ? 'bg-emerald-50 border border-emerald-200 text-emerald-700'
            : 'bg-red-50 border border-red-200 text-red-600'"
        >
          <Volume2 v-if="soundEnabled" class="h-4 w-4" :class="isSpeaking ? 'animate-pulse' : ''" />
          <VolumeX v-else class="h-4 w-4" />
          <span class="text-sm font-semibold">{{ soundEnabled ? (isSpeaking ? 'Memanggil...' : 'Suara Aktif') : 'Suara Mati' }}</span>
        </button>
        <div class="text-right">
          <p class="text-4xl font-black tracking-tight tabular-nums text-slate-800">{{ formattedTime }}</p>
          <p class="text-sm text-slate-500 font-medium mt-1">{{ formattedDate }}</p>
        </div>
      </div>
    </header>

    <!-- ═══ MAIN DISPLAY ════════════════════════════════════════════════ -->
    <main class="flex-1 flex relative z-10 p-6 gap-6 overflow-hidden">

      <!-- ═══ LEFT: Active Calling Card ═══════════════════════════════ -->
      <div class="w-2/5 flex flex-col">
        <div class="flex-1 rounded-3xl overflow-hidden relative">
          <!-- Active Call Card -->
          <div
            v-if="activeCallCounter"
            class="h-full rounded-3xl border-2 flex flex-col items-center justify-center relative overflow-hidden transition-all duration-500"
            :class="[
              flashingCounterId === activeCallCounter.counter_id
                ? 'border-sky-400 shadow-2xl shadow-sky-500/20 animate-pulse-glow'
                : 'border-sky-200 shadow-xl shadow-sky-500/10',
              'bg-white'
            ]"
          >
            <!-- Decorative background glow -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
              <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-sky-500/5 rounded-full blur-[100px]"></div>
            </div>

            <!-- Top gradient bar -->
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-sky-500 to-blue-600"></div>

            <div class="text-center p-8 w-full relative z-10">
              <div class="flex justify-center mb-3">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-bold bg-sky-100 text-sky-700 border border-sky-200"
                      :class="flashingCounterId === activeCallCounter.counter_id ? 'animate-pulse' : ''">
                  <PhoneCall class="h-4 w-4" />
                  Dipanggil
                </span>
              </div>
              <p class="text-sm font-black text-sky-600 uppercase tracking-widest mb-3">Nomor Antrean</p>
              <h2
                class="font-black tracking-tighter leading-none mb-6"
                :class="[
                  flashingCounterId === activeCallCounter.counter_id ? 'animate-number-pop' : '',
                  'text-[8rem] lg:text-[10rem] text-transparent bg-clip-text bg-gradient-to-br from-sky-500 to-blue-600'
                ]"
              >
                {{ activeCallCounter.queue_number }}
              </h2>
              <div class="space-y-3">
                <div class="inline-flex items-center gap-3 px-6 py-3 rounded-2xl bg-gradient-to-r from-sky-500 to-blue-600 shadow-lg shadow-sky-500/25">
                  <Monitor class="h-5 w-5 text-white" />
                  <span class="text-xl font-bold text-white">{{ activeCallCounter.counter_name }}</span>
                </div>
                <p class="text-base font-semibold text-slate-500">{{ activeCallCounter.service_name }}</p>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="h-full rounded-3xl border-2 border-dashed border-slate-200 bg-white/60 flex flex-col items-center justify-center">
            <div class="h-24 w-24 rounded-3xl bg-slate-100 flex items-center justify-center mb-4">
              <TicketCheck class="h-12 w-12 text-slate-300" />
            </div>
            <p class="text-xl font-bold text-slate-400">Menunggu Panggilan</p>
            <p class="text-sm text-slate-400 mt-2">Nomor antrean akan muncul di sini</p>
          </div>
        </div>
      </div>

      <!-- ═══ RIGHT: Counter List with Status ═════════════════════════ -->
      <div class="flex-1 flex flex-col gap-4 overflow-y-auto scrollbar-thin pr-1">
        <h3 class="text-sm font-black text-slate-500 uppercase tracking-widest px-1">Daftar Loket</h3>

        <div
          v-for="(counter, idx) in counters"
          :key="counter.id"
          class="rounded-2xl border-2 overflow-hidden transition-all duration-500"
          :class="[
            flashingCounterId === counter.id
              ? `${getColor(idx).border} shadow-xl ${getColor(idx).glow} scale-[1.02] animate-pulse-glow`
              : counter.current_queue
                ? `${getColor(idx).border} shadow-md ${getColor(idx).glow}`
                : 'border-slate-200 shadow-sm hover:shadow-md'
          ]"
        >
          <!-- Top color bar -->
          <div class="h-1 w-full bg-gradient-to-r" :class="getColor(idx).gradient"></div>

          <div class="p-5 flex items-center gap-5 bg-white">
            <!-- Counter icon with gradient -->
            <div
              class="h-14 w-14 rounded-2xl bg-gradient-to-br flex items-center justify-center shrink-0 shadow-md transition-transform duration-300"
              :class="[
                getColor(idx).gradient, getColor(idx).glow,
                flashingCounterId === counter.id ? 'scale-110' : ''
              ]"
            >
              <Monitor class="h-6 w-6 text-white" />
            </div>

            <!-- Counter info -->
            <div class="flex-1 min-w-0">
              <h4 class="text-lg font-bold text-slate-800">{{ counter.name }}</h4>
              <p class="text-sm font-medium" :class="getColor(idx).text">{{ counter.service_name }}</p>
            </div>

            <!-- Queue number & status -->
            <div v-if="counter.current_queue" class="text-right shrink-0">
              <p
                class="text-4xl font-black tracking-tight text-slate-800 transition-all"
                :class="flashingCounterId === counter.id ? 'animate-number-pop' : ''"
              >
                {{ counter.current_queue.queue_number }}
              </p>
              <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border mt-1"
                :class="statusBadge(counter.current_queue.status)"
              >
                <component :is="counter.current_queue.status === 'dilayani' ? CheckCircle2 : PhoneCall" class="h-3 w-3" />
                {{ statusLabel(counter.current_queue.status) }}
              </span>
            </div>
            <div v-else class="text-right shrink-0">
              <p class="text-lg font-semibold text-slate-300">—</p>
              <p class="text-xs text-slate-400 mt-1">Menunggu</p>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="counters.length === 0 && !loading" class="flex-1 flex items-center justify-center">
          <p class="text-slate-400 font-medium">Tidak ada loket aktif</p>
        </div>
      </div>
    </main>

    <!-- ═══ FOOTER ══════════════════════════════════════════════════════ -->
    <footer class="relative z-10 text-center py-3 bg-white/80 backdrop-blur-sm border-t border-slate-200">
      <p class="text-xs text-slate-500 font-medium">
        &copy; {{ new Date().getFullYear() }} {{ displaySettings.app_name }} &bull; Pandai Antrian System
      </p>
    </footer>
  </div>
</template>

<style scoped>
.fade-enter-active { transition: opacity 0.3s ease; }
.fade-leave-active { transition: opacity 0.5s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
