<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { TicketCheck, Monitor, Volume2, VolumeX, PhoneCall, CheckCircle2, Users } from 'lucide-vue-next'
import axios from 'axios'
import { echo } from '@/lib/echo'

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8092/api'

interface CounterDisplay {
  id: number
  name: string
  service_name: string
  waiting_count?: number
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
const totalWaiting = ref(0)

// Sound activation (browser autoplay policy requires user gesture)
const soundEnabled = ref(true)

// The counter that is currently calling (most recent call event)
const activeCallCounter = ref<{ counter_id: number; counter_name: string; queue_number: string; service_name: string } | null>(null)

// Animations
const flashingCounterId = ref<number | null>(null)
const isSpeaking = ref(false)

// Announcement Queue (untuk menangani panggilan bersamaan)
const announcementQueue = ref<{ queue_number: string; counter_id: number; counter_name: string; service_name: string }[]>([])
const isProcessingQueue = ref(false)



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

// Warna card tengah mengikuti warna card loket yang sedang dipanggil
const activeColor = computed(() => {
  const idx = counters.value.findIndex(c => c.id === activeCallCounter.value?.counter_id)
  return getColor(idx >= 0 ? idx : 0)
})

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
    totalWaiting.value = res.data.data.total_waiting ?? 0

    // Set active call to the most recently called counter
    // (jangan timpa kartu tengah saat pengumuman sedang diproses)
    if (!isProcessingQueue.value) {
      const calling = counters.value
        .filter(c => c.current_queue?.status === 'dipanggil')
        .sort((a, b) => new Date(b.current_queue!.called_at).getTime() - new Date(a.current_queue!.called_at).getTime())[0]
      if (calling && calling.current_queue) {
        activeCallCounter.value = {
          counter_id: calling.id,
          counter_name: calling.name,
          queue_number: calling.current_queue.queue_number,
          service_name: calling.service_name,
        }
      }
    }
  } catch (e) {
    console.error('Failed to fetch display:', e)
  } finally {
    loading.value = false
  }
}

// Referensi utterance aktif (mencegah garbage collection di Chrome)
let currentUtterance: SpeechSynthesisUtterance | null = null

// Autoplay policy: suara baru boleh bunyi setelah ada interaksi user pada halaman
const needsSoundUnlock = ref(false)
let pendingSpeech: { queueNumber: string; counterName: string } | null = null

function unlockSound() {
  if (!('speechSynthesis' in window)) return
  needsSoundUnlock.value = false
  window.removeEventListener('pointerdown', unlockSound)
  window.removeEventListener('keydown', unlockSound)
  window.removeEventListener('touchstart', unlockSound)
  // Ulangi panggilan terakhir yang sempat diblokir
  if (pendingSpeech) {
    const p = pendingSpeech
    pendingSpeech = null
    speakAnnouncement(p.queueNumber, p.counterName, () => {})
  }
}

// TTS — with sound gate
function speakAnnouncement(queueNumber: string, counterName: string, rawOnFinished: () => void) {
  // Pastikan callback hanya dipanggil sekali + watchdog agar antrean tidak macet
  // bila event onend dari SpeechSynthesis tidak pernah terpicu.
  let finished = false
  let watchdog: any = null
  const onFinished = () => {
    if (finished) return
    finished = true
    if (watchdog) clearTimeout(watchdog)
    rawOnFinished()
  }
  watchdog = setTimeout(() => {
    isSpeaking.value = false
    window.speechSynthesis?.cancel()
    onFinished()
  }, 30000)

  if (!('speechSynthesis' in window) || !soundEnabled.value) {
    // Tunggu visual sebentar jika suara dimatikan/tidak support, lalu lanjut ke antrean berikutnya
    setTimeout(onFinished, 4000)
    return
  }

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
      if (times > 1) {
        setTimeout(() => speak(times - 1), 1200)
      } else {
        isSpeaking.value = false
        onFinished()
      }
    }
    utterance.onerror = (e: SpeechSynthesisErrorEvent) => {
      if (e.error === 'not-allowed') {
        needsSoundUnlock.value = true
        pendingSpeech = { queueNumber, counterName }
      }
      isSpeaking.value = false
      onFinished()
    }
    // Simpan referensi agar utterance tidak di-garbage-collect (bug Chrome)
    currentUtterance = utterance
    window.speechSynthesis.speak(utterance)
  }
  speak(2)
}

let flashTimeout: any = null
function triggerFlash(counterId: number) {
  // Aktif selama suara berbunyi; dibersihkan di callback selesai bicara
  // (timeout 35 detik hanya sebagai pengaman)
  flashingCounterId.value = counterId
  if (flashTimeout) clearTimeout(flashTimeout)
  flashTimeout = setTimeout(() => { flashingCounterId.value = null }, 35000)
}

// Handle broadcast event
function handleQueueCalled(data: { queue_number: string; counter_id: number; counter_name: string; service_name: string; action: string }) {
  // Update the small cards list immediately
  const counter = counters.value.find(c => c.id === data.counter_id)
  if (counter) {
    counter.current_queue = {
      queue_number: data.queue_number,
      status: 'dipanggil',
      called_at: new Date().toISOString(),
    }
  }

  // Masukkan ke antrean panggilan
  announcementQueue.value.push({
    queue_number: data.queue_number,
    counter_id: data.counter_id,
    counter_name: data.counter_name,
    service_name: data.service_name
  })

  // Jika tidak ada yang sedang diproses, jalankan queue
  if (!isProcessingQueue.value) {
    processAnnouncementQueue()
  }
}

function processAnnouncementQueue() {
  if (announcementQueue.value.length === 0) {
    isProcessingQueue.value = false
    return
  }

  isProcessingQueue.value = true
  const currentAnnouncement = announcementQueue.value.shift()!

  // Update big screen
  activeCallCounter.value = {
    counter_id: currentAnnouncement.counter_id,
    counter_name: currentAnnouncement.counter_name,
    queue_number: currentAnnouncement.queue_number,
    service_name: currentAnnouncement.service_name,
  }

  triggerFlash(currentAnnouncement.counter_id)

  // Play sound & wait for it to finish
  speakAnnouncement(currentAnnouncement.queue_number, currentAnnouncement.counter_name, () => {
    // Suara selesai → hilangkan efek shadow/glow pada card loket
    flashingCounterId.value = null
    if (flashTimeout) clearTimeout(flashTimeout)

    // Jeda 1 detik antar panggilan jika ada panggilan beruntun
    setTimeout(() => {
      processAnnouncementQueue()
    }, 1000)
  })
}

// Handle queue status update
function handleQueueUpdated(data: { id: number; queue_number: string; status: string; counter_name: string | null; waiting_by_counter?: Record<string, number>; total_waiting?: number }) {
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
  applyWaitingCounts(data.waiting_by_counter)
  if (data.total_waiting !== undefined) totalWaiting.value = data.total_waiting
}

// Terapkan sisa antrean menunggu dari payload WebSocket (tanpa request HTTP, tidak menyentuh kartu panggilan / animasi)
function applyWaitingCounts(map?: Record<string, number>) {
  if (!map) return
  for (const c of counters.value) {
    if (map[c.id] !== undefined) c.waiting_count = map[c.id]
  }
}

let echoChannel: any = null
let echoUpdatesChannel: any = null

onMounted(() => {
  fetchDisplay()
  const clockInterval = setInterval(() => { currentTime.value = new Date() }, 1000)

  if ('speechSynthesis' in window) window.speechSynthesis.getVoices()

  // Jika halaman belum pernah berinteraksi dengan user, suara akan diblokir browser
  const activation = (navigator as any).userActivation
  if (!activation || !activation.hasBeenActive) needsSoundUnlock.value = true
  window.addEventListener('pointerdown', unlockSound)
  window.addEventListener('keydown', unlockSound)
  window.addEventListener('touchstart', unlockSound)

  echoChannel = echo.channel('queue.display')
  echoChannel.listen('.App\\Events\\QueueCalled', handleQueueCalled)

  echoUpdatesChannel = echo.channel('queue.updates')
  echoUpdatesChannel.listen('.App\\Events\\QueueUpdated', handleQueueUpdated)

  // Tanpa polling: sinkronkan ulang data hanya ketika WebSocket tersambung kembali
  // (event yang terlewat saat koneksi putus akan terambil lewat satu kali fetch)
  const pusherConn = (echo as any).connector?.pusher?.connection
  let wasDisconnected = false
  const onConnState = (states: { previous: string; current: string }) => {
    if (states.current === 'connected') {
      if (wasDisconnected) fetchDisplay()
      wasDisconnected = false
    } else if (states.current === 'unavailable' || states.current === 'disconnected' || states.current === 'failed') {
      wasDisconnected = true
    }
  }
  pusherConn?.bind('state_change', onConnState)

  onUnmounted(() => {
    pusherConn?.unbind('state_change', onConnState)
    clearInterval(clockInterval)
    window.removeEventListener('pointerdown', unlockSound)
    window.removeEventListener('keydown', unlockSound)
    window.removeEventListener('touchstart', unlockSound)
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



    <!-- Banner aktivasi suara (kebijakan autoplay browser) -->
    <button
      v-if="needsSoundUnlock && soundEnabled"
      class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 px-6 py-3 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold shadow-xl shadow-amber-500/30 animate-pulse backdrop-blur"
      @click="unlockSound"
    >
      <Volume2 class="h-5 w-5" />
      Klik di mana saja untuk mengaktifkan suara panggilan
    </button>

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

      <!-- Sisa semua antrean (center) -->
      <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center gap-4 px-8 py-2.5 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-lg shadow-sky-500/30 border border-white/30 backdrop-blur">
        <Users class="h-8 w-8 opacity-90" />
        <div class="text-center leading-none">
          <p class="text-xs font-semibold uppercase tracking-widest text-sky-100">Sisa Antrean</p>
          <p class="text-xl font-black tabular-nums mt-1">{{ totalWaiting }}</p>
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
    <main class="flex-1 flex flex-col relative z-10 p-6 gap-6 overflow-y-auto scrollbar-thin">

      <!-- ═══ TOP CENTER: Active Calling Card ═════════════════════════ -->
      <div class="w-full max-w-3xl mx-auto flex flex-col shrink-0">
        <div class="min-h-[420px] flex-1 rounded-3xl overflow-hidden relative">
          <!-- Active Call Card -->
          <div
            v-if="activeCallCounter"
            class="h-full rounded-3xl border-2 flex flex-col items-center justify-center relative overflow-hidden transition-all duration-500"
            :class="[
              activeColor.border,
              flashingCounterId === activeCallCounter.counter_id
                ? `shadow-2xl ${activeColor.glow} animate-pulse-glow`
                : 'shadow-xl',
              'bg-white'
            ]"
          >
            <!-- Decorative background glow -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
              <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] rounded-full blur-[100px]"
                   :class="[activeColor.iconBg, flashingCounterId === activeCallCounter.counter_id ? 'call-aurora' : 'opacity-10']"></div>

              <!-- Ripple rings + shimmer (hanya saat panggilan berlangsung) -->
              <template v-if="flashingCounterId === activeCallCounter.counter_id">
                <span class="call-ring" :class="activeColor.text"></span>
                <span class="call-ring" :class="activeColor.text" style="animation-delay: 0.8s"></span>
                <span class="call-ring" :class="activeColor.text" style="animation-delay: 1.6s"></span>
                <span class="call-shimmer"></span>
              </template>
            </div>

            <!-- Top gradient bar -->
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r" :class="[activeColor.gradient, flashingCounterId === activeCallCounter.counter_id ? 'call-bar' : '']"></div>

            <div class="text-center p-8 w-full relative z-10">
              <div class="flex justify-center mb-3">
                <span class="inline-flex items-center gap-3 px-5 py-2 rounded-full text-sm font-bold border backdrop-blur"
                      :class="[activeColor.badgeBg, activeColor.text, activeColor.border]">
                  <PhoneCall class="h-4 w-4" :class="flashingCounterId === activeCallCounter.counter_id ? 'call-ring-phone' : ''" />
                  {{ flashingCounterId === activeCallCounter.counter_id ? 'Sedang Memanggil' : 'Dipanggil' }}
                  <!-- Equalizer suara -->
                  <span v-if="flashingCounterId === activeCallCounter.counter_id" class="flex items-end gap-[3px] h-4">
                    <i class="eq-bar" :class="activeColor.iconBg"></i>
                    <i class="eq-bar" :class="activeColor.iconBg" style="animation-delay: .15s"></i>
                    <i class="eq-bar" :class="activeColor.iconBg" style="animation-delay: .3s"></i>
                    <i class="eq-bar" :class="activeColor.iconBg" style="animation-delay: .45s"></i>
                  </span>
                </span>
              </div>
              <p class="text-sm font-black uppercase tracking-widest mb-3" :class="activeColor.text">Nomor Antrean</p>
              <h2
                class="font-black tracking-tighter leading-none mb-6"
                :class="[
                  flashingCounterId === activeCallCounter.counter_id ? 'animate-number-pop' : '',
                  'text-[10rem] lg:text-[15rem] text-transparent bg-clip-text bg-gradient-to-br',
                  activeColor.gradient
                ]"
              >
                {{ activeCallCounter.queue_number }}
              </h2>
              <div class="space-y-3">
                <div class="inline-flex items-center gap-3 px-6 py-3 rounded-2xl bg-gradient-to-r shadow-lg" :class="[activeColor.gradient, activeColor.glow]">
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

      <!-- ═══ BOTTOM: Counter List with Status ════════════════════════ -->
      <div class="w-full flex flex-col gap-4">
        <h3 class="text-sm font-black text-slate-500 uppercase tracking-widest px-1">Daftar Loket</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="(counter, idx) in counters"
          :key="counter.id"
          class="relative rounded-2xl border-2 overflow-hidden transition-all duration-500"
          :class="[
            flashingCounterId === counter.id
              ? `${getColor(idx).border} shadow-xl ${getColor(idx).glow} scale-[1.02] animate-pulse-glow`
              : counter.current_queue
                ? `${getColor(idx).border} shadow-none`
                : 'border-slate-200 shadow-none'
          ]"
        >
          <!-- Shimmer sweep saat dipanggil -->
          <span v-if="flashingCounterId === counter.id" class="list-shimmer"></span>

          <!-- Top color bar -->
          <div class="h-1 w-full bg-gradient-to-r" :class="[getColor(idx).gradient, flashingCounterId === counter.id ? 'call-bar' : '']"></div>

          <div class="p-5 flex items-center gap-5 bg-white">
            <!-- Counter icon with gradient -->
            <div class="relative shrink-0">
              <span v-if="flashingCounterId === counter.id" class="list-ping" :class="getColor(idx).iconBg"></span>
              <span v-if="flashingCounterId === counter.id" class="list-ping" :class="getColor(idx).iconBg" style="animation-delay: 0.7s"></span>
              <div
                class="relative h-14 w-14 rounded-2xl bg-gradient-to-br flex items-center justify-center transition-transform duration-300"
                :class="[
                  getColor(idx).gradient,
                  flashingCounterId === counter.id ? `scale-110 shadow-md ${getColor(idx).glow} list-bounce` : ''
                ]"
              >
                <Monitor class="h-6 w-6 text-white" />
              </div>
            </div>

            <!-- Counter info -->
            <div class="flex-1 min-w-0">
              <h4 class="text-lg font-bold text-slate-800">{{ counter.name }}</h4>
              <p class="text-lg font-medium" :class="getColor(idx).text">{{ counter.service_name }}</p>
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

          <!-- Sisa antrean menunggu -->
          <div class="flex items-center justify-between px-5 py-2.5 border-t" :class="[getColor(idx).lightBg, getColor(idx).border]">
            <span class="text-sm font-semibold text-slate-600">Sisa antrean menunggu</span>
            <span
              class="inline-flex items-center justify-center min-w-[2.5rem] px-3 py-0.5 rounded-full text-lg font-black text-white bg-gradient-to-r shadow-sm"
              :class="getColor(idx).gradient"
            >{{ counter.waiting_count ?? 0 }}</span>
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

/* ── Animasi panggilan berlangsung ── */
.call-ring {
  position: absolute;
  top: 50%; left: 50%;
  width: 260px; height: 260px;
  margin: -130px 0 0 -130px;
  border-radius: 9999px;
  border: 3px solid currentColor;
  opacity: 0;
  animation: call-ripple 2.4s cubic-bezier(0.2, 0.6, 0.3, 1) infinite;
}
@keyframes call-ripple {
  0%   { transform: scale(0.6); opacity: 0.55; }
  100% { transform: scale(3.2); opacity: 0; }
}
.call-aurora { animation: call-aurora 2.4s ease-in-out infinite; }
@keyframes call-aurora {
  0%, 100% { opacity: 0.14; transform: translate(-50%, -50%) scale(0.9); }
  50%      { opacity: 0.32; transform: translate(-50%, -50%) scale(1.15); }
}
.call-shimmer {
  position: absolute; inset: 0;
  background: linear-gradient(110deg, transparent 35%, rgba(255,255,255,0.75) 50%, transparent 65%);
  transform: translateX(-100%);
  animation: call-shimmer 2.8s ease-in-out infinite;
}
@keyframes call-shimmer {
  0%   { transform: translateX(-100%); }
  60%, 100% { transform: translateX(100%); }
}
.call-bar {
  background-size: 200% 100%;
  animation: call-bar 1.6s linear infinite;
}
@keyframes call-bar {
  0%   { background-position: 0% 0; filter: brightness(1); }
  50%  { filter: brightness(1.25); }
  100% { background-position: 200% 0; filter: brightness(1); }
}
.call-ring-phone { animation: call-phone 0.9s ease-in-out infinite; transform-origin: center; }
@keyframes call-phone {
  0%, 100% { transform: rotate(0); }
  15% { transform: rotate(-18deg); }
  30% { transform: rotate(16deg); }
  45% { transform: rotate(-12deg); }
  60% { transform: rotate(8deg); }
  75% { transform: rotate(0); }
}
.eq-bar {
  display: block; width: 3px; height: 100%;
  border-radius: 2px;
  transform-origin: bottom;
  animation: eq 0.9s ease-in-out infinite;
}
@keyframes eq {
  0%, 100% { transform: scaleY(0.25); }
  50%      { transform: scaleY(1); }
}

/* ── Animasi kartu daftar loket ── */
.list-shimmer {
  position: absolute; inset: 0; z-index: 10; pointer-events: none;
  background: linear-gradient(110deg, transparent 35%, rgba(255,255,255,0.7) 50%, transparent 65%);
  transform: translateX(-100%);
  animation: call-shimmer 2.2s ease-in-out infinite;
}
.list-ping {
  position: absolute; inset: 0;
  border-radius: 1rem;
  opacity: 0;
  animation: list-ping 1.4s cubic-bezier(0, 0, 0.2, 1) infinite;
}
@keyframes list-ping {
  0%   { transform: scale(1); opacity: 0.5; }
  100% { transform: scale(1.9); opacity: 0; }
}
.list-bounce { animation: list-bounce 0.8s ease-in-out infinite; }
@keyframes list-bounce {
  0%, 100% { transform: scale(1.1) translateY(0); }
  50%      { transform: scale(1.15) translateY(-3px); }
}
</style>
