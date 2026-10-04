<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { TicketCheck, Ticket, Pointer, Clock, Users, ChevronRight, Camera, Printer, X, RefreshCw } from 'lucide-vue-next'
import axios from 'axios'
import { useQZTray } from '@/composables/useQZTray'
import { echo } from '@/lib/echo'

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8092/api'

interface ServiceItem {
  id: number
  name: string
  prefix_code: string
  waiting_count: number
}

interface Settings {
  app_name: string
  app_logo: string | null
  enable_camera: boolean
  printer_name: string
}

// State
const { connect, printTicket } = useQZTray()
const services = ref<ServiceItem[]>([])
const settings = ref<Settings>({ app_name: 'Pandai Antrian', app_logo: null, enable_camera: false, printer_name: '' })
const loading = ref(true)
const step = ref<'select' | 'camera' | 'printing' | 'result'>('select')
const selectedService = ref<ServiceItem | null>(null)
const resultData = ref<any>(null)
const processingError = ref<string | null>(null)
const currentTime = ref(new Date())

// Camera
const videoRef = ref<HTMLVideoElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const cameraStream = ref<MediaStream | null>(null)
const capturedPhoto = ref<Blob | null>(null)

// Service card colors (Light Theme only)
const serviceColors = [
  { bg: 'from-sky-500 to-blue-600', soft: 'from-sky-50 to-blue-50', text: 'text-sky-700', chip: 'bg-sky-100/80 text-sky-700', glow: 'shadow-sky-500/30', hover: 'hover:shadow-sky-500/25 hover:border-sky-300' },
  { bg: 'from-violet-500 to-purple-600', soft: 'from-violet-50 to-purple-50', text: 'text-violet-700', chip: 'bg-violet-100/80 text-violet-700', glow: 'shadow-violet-500/30', hover: 'hover:shadow-violet-500/25 hover:border-violet-300' },
  { bg: 'from-emerald-500 to-teal-600', soft: 'from-emerald-50 to-teal-50', text: 'text-emerald-700', chip: 'bg-emerald-100/80 text-emerald-700', glow: 'shadow-emerald-500/30', hover: 'hover:shadow-emerald-500/25 hover:border-emerald-300' },
  { bg: 'from-amber-500 to-orange-600', soft: 'from-amber-50 to-orange-50', text: 'text-amber-700', chip: 'bg-amber-100/80 text-amber-700', glow: 'shadow-amber-500/30', hover: 'hover:shadow-amber-500/25 hover:border-amber-300' },
  { bg: 'from-rose-500 to-pink-600', soft: 'from-rose-50 to-pink-50', text: 'text-rose-700', chip: 'bg-rose-100/80 text-rose-700', glow: 'shadow-rose-500/30', hover: 'hover:shadow-rose-500/25 hover:border-rose-300' },
  { bg: 'from-cyan-500 to-blue-500', soft: 'from-cyan-50 to-blue-50', text: 'text-cyan-700', chip: 'bg-cyan-100/80 text-cyan-700', glow: 'shadow-cyan-500/30', hover: 'hover:shadow-cyan-500/25 hover:border-cyan-300' },
]

const getColor = (index: number) => serviceColors[index % serviceColors.length]!

// Time display
const formattedTime = computed(() => {
  return currentTime.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
})
const formattedDate = computed(() => {
  return currentTime.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
})

// Fetch services
async function fetchServices() {
  loading.value = true
  try {
    const res = await axios.get(`${API_BASE}/guest/services`)
    services.value = res.data.data.services
    settings.value = res.data.data.settings
  } catch (e) {
    console.error('Failed to fetch services:', e)
  } finally {
    loading.value = false
  }
}

// Select service
function selectService(service: ServiceItem) {
  selectedService.value = service
  processingError.value = null

  if (settings.value.enable_camera) {
    step.value = 'camera'
    startCamera()
  } else {
    submitTicket()
  }
}

// Camera functions
async function startCamera() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: 640, height: 480 }
    })
    cameraStream.value = stream
    if (videoRef.value) {
      videoRef.value.srcObject = stream
    }
  } catch (e) {
    console.error('Camera error:', e)
    // Skip camera if not available
    submitTicket()
  }
}

function capturePhoto() {
  if (!videoRef.value || !canvasRef.value) return
  const ctx = canvasRef.value.getContext('2d')
  if (!ctx) return

  canvasRef.value.width = videoRef.value.videoWidth
  canvasRef.value.height = videoRef.value.videoHeight
  ctx.drawImage(videoRef.value, 0, 0)

  canvasRef.value.toBlob((blob) => {
    capturedPhoto.value = blob
    stopCamera()
    submitTicket()
  }, 'image/jpeg', 0.8)
}

function stopCamera() {
  if (cameraStream.value) {
    cameraStream.value.getTracks().forEach(t => t.stop())
    cameraStream.value = null
  }
}

// Submit ticket
async function submitTicket() {
  step.value = 'printing'
  processingError.value = null

  try {
    const formData = new FormData()
    formData.append('service_id', String(selectedService.value!.id))
    if (capturedPhoto.value) {
      formData.append('photo', capturedPhoto.value, 'visitor.jpg')
    }

    const res = await axios.post(`${API_BASE}/guest/queues`, formData)
    resultData.value = res.data.data

    if (settings.value.printer_name) {
      try {
        await printTicket(settings.value.printer_name, resultData.value, settings.value.app_name)
      } catch (printErr) {
        console.error('Print failed:', printErr)
        // Kita abaikan error agar KIOSK tetap lanjut ke halaman antrean
      }
    }

    step.value = 'result'

    // Auto-reset after 1 second
    setTimeout(() => resetToSelect(), 2000)
  } catch (e: any) {
    processingError.value = e.response?.data?.message ?? 'Gagal mengambil tiket. Silakan coba lagi.'
    step.value = 'select'
  }
}

// Reset to service selection
function resetToSelect() {
  step.value = 'select'
  selectedService.value = null
  resultData.value = null
  capturedPhoto.value = null
  processingError.value = null
  fetchServices() // Refresh waiting counts
}

function cancelCamera() {
  stopCamera()
  step.value = 'select'
  selectedService.value = null
}

let echoChannel: any = null
let clockTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  fetchServices()
  connect().catch(e => console.error('Initial QZTray connect error:', e))
  // Update clock
  clockTimer = setInterval(() => { currentTime.value = new Date() }, 1000)

  // Listen for real-time queue updates to refresh waiting counts
  echoChannel = echo.channel('queue.updates')
  echoChannel.listen('.App\\Events\\QueueUpdated', () => {
    // Only refresh waiting counts if we're on the service selection screen
    if (step.value === 'select') {
      fetchServices()
    }
  })
  echoChannel.listen('.App\\Events\\QueueCalled', () => {
    if (step.value === 'select') {
      fetchServices()
    }
  })
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
  stopCamera()
  if (echoChannel) {
    echoChannel.stopListening('.App\\Events\\QueueUpdated')
    echoChannel.stopListening('.App\\Events\\QueueCalled')
    echo.leaveChannel('queue.updates')
  }
})
</script>

<template>
  <div class="min-h-screen flex flex-col font-sans overflow-hidden relative bg-gradient-to-br from-slate-50 via-white to-sky-50">

    <!-- Ambient mesh background -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
      <div class="kiosk-blob absolute -top-40 -right-32 w-[640px] h-[640px] rounded-full bg-gradient-to-bl from-sky-300/30 via-blue-200/20 to-transparent blur-3xl"></div>
      <div class="kiosk-blob absolute -bottom-48 -left-40 w-[620px] h-[620px] rounded-full bg-gradient-to-tr from-violet-300/25 via-sky-200/20 to-transparent blur-3xl" style="animation-delay: -6s"></div>
      <div class="absolute top-1/3 left-1/2 -translate-x-1/2 w-[900px] h-[400px] rounded-full bg-gradient-to-r from-emerald-200/15 via-sky-200/15 to-violet-200/15 blur-[110px]"></div>
      <div class="absolute inset-0 kiosk-grid opacity-[0.35]"></div>
    </div>

    <!-- ══ HEADER ══════════════════════════════════════════════════════════ -->
    <header class="relative z-10 px-5 sm:px-10 pt-5">
      <div class="flex items-center justify-between gap-4 rounded-3xl bg-white/70 backdrop-blur-xl border border-white/80 shadow-[0_8px_30px_-12px_rgba(14,116,204,0.25)] px-5 sm:px-7 py-4">
        <div class="flex items-center gap-4 min-w-0">
          <div
            class="h-14 w-14 shrink-0 rounded-2xl overflow-hidden flex items-center justify-center"
            :class="settings.app_logo ? 'bg-white shadow-md ring-1 ring-slate-100' : 'bg-gradient-to-br from-sky-500 to-blue-600 shadow-lg shadow-sky-500/30'"
          >
            <img v-if="settings.app_logo" :src="settings.app_logo" alt="Logo" class="h-full w-full object-contain p-1" />
            <TicketCheck v-else class="h-7 w-7 text-white" />
          </div>
          <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-800 truncate">{{ settings.app_name }}</h1>
          </div>
        </div>

        <div class="flex items-center gap-4 shrink-0">
          <div class="hidden sm:block h-10 w-px bg-gradient-to-b from-transparent via-slate-200 to-transparent"></div>
          <div class="text-right">
            <p class="text-3xl sm:text-4xl font-black tracking-tight tabular-nums bg-gradient-to-br from-slate-800 to-slate-600 bg-clip-text text-transparent leading-none">{{ formattedTime }}</p>
            <p class="text-xs text-slate-500 font-medium mt-1.5 flex items-center justify-end gap-1.5">
              <Clock class="h-3.5 w-3.5" />
              {{ formattedDate }}
            </p>
          </div>
        </div>
      </div>
    </header>

    <!-- ══ MAIN CONTENT ════════════════════════════════════════════════════ -->
    <main class="flex-1 flex items-center justify-center px-5 sm:px-10 py-8 relative z-10">

      <!-- ── STEP 1: Service Selection ────────────────────────────────── -->
      <Transition name="fade" mode="out-in">
        <div v-if="step === 'select'" key="select" class="w-full max-w-6xl">

          <!-- Title -->
          <div class="text-center mb-10 animate-fadeup">
            <h2 class="text-3xl sm:text-3xl font-black tracking-tight mb-3 bg-gradient-to-br from-slate-900 via-slate-800 to-sky-700 bg-clip-text text-transparent">
              Pilih Layanan
            </h2>
            <p class="text-slate-500 text-base sm:text-lg font-medium max-w-xl mx-auto">
              Sentuh layanan yang Anda inginkan untuk mendapatkan nomor antrean
            </p>
          </div>

          <!-- Error Alert -->
          <div v-if="processingError" class="max-w-lg mx-auto mb-8 flex items-center gap-3 p-4 rounded-2xl bg-gradient-to-r from-rose-50 to-red-50 border border-rose-200 shadow-lg shadow-rose-500/10 text-rose-600 text-sm font-semibold animate-fadeup">
            <div class="h-9 w-9 shrink-0 rounded-xl bg-gradient-to-br from-rose-500 to-red-600 flex items-center justify-center shadow-md shadow-rose-500/30">
              <X class="h-5 w-5 text-white" />
            </div>
            {{ processingError }}
          </div>

          <!-- Service Grid -->
          <div v-if="!loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-7">
            <button
              v-for="(service, index) in services"
              :key="service.id"
              @click="selectService(service)"
              class="group relative rounded-[28px] border border-white/90 bg-white/75 backdrop-blur-xl p-6 sm:p-7 text-left transition-all duration-300 shadow-[0_10px_40px_-14px_rgba(15,23,42,0.18)] hover:-translate-y-1.5 hover:shadow-2xl active:scale-[0.97] animate-fadeup overflow-hidden"
              :class="getColor(index).hover"
              :style="{ animationDelay: `${index * 80}ms` }"
            >
              <!-- Soft tinted wash -->
              <div class="absolute inset-0 bg-gradient-to-br opacity-60 group-hover:opacity-100 transition-opacity duration-300" :class="getColor(index).soft"></div>
              <!-- Glow orb -->
              <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-gradient-to-br opacity-20 group-hover:opacity-40 blur-2xl transition-opacity duration-300" :class="getColor(index).bg"></div>
              <!-- Shine sweep -->
              <div class="kiosk-shine absolute inset-0 pointer-events-none"></div>

              <div class="relative z-10 flex items-start justify-between mb-6">
                <div
                  class="h-[72px] w-[72px] rounded-[22px] bg-gradient-to-br flex items-center justify-center shadow-xl transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3 ring-4 ring-white/70"
                  :class="[getColor(index).bg, getColor(index).glow]"
                >
                  <span class="text-3xl font-black text-white drop-shadow-sm">{{ service.prefix_code }}</span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/90 shadow-md text-[11px] font-bold uppercase tracking-wider text-slate-500">
                  <Pointer class="h-3.5 w-3.5 kiosk-tap" />
                  Sentuh
                </div>
              </div>

              <div class="relative z-10 mb-6">
                <h3 class="text-xl sm:text-2xl font-extrabold text-slate-800 mb-3 leading-snug">
                  {{ service.name }}
                </h3>
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-sm font-bold backdrop-blur" :class="getColor(index).chip">
                  <Users class="h-4 w-4" />
                  <span>{{ service.waiting_count }} orang menunggu</span>
                </div>
              </div>

              <!-- Call to action: menegaskan bahwa card ini = ambil nomor antrean -->
              <!-- <div
                class="relative z-10 flex items-center justify-between gap-3 rounded-2xl bg-gradient-to-r px-5 py-4 text-white shadow-lg transition-all duration-300 group-hover:shadow-xl group-hover:brightness-110"
                :class="[getColor(index).bg, getColor(index).glow]"
              >
                <div class="flex items-center gap-3">
                  <Ticket class="h-7 w-7 -rotate-12 transition-transform duration-300 group-hover:rotate-0 group-hover:scale-110" />
                  <span class="text-lg font-black tracking-tight">Ambil Antrean</span>
                </div>
                <div class="h-9 w-9 rounded-full bg-white/25 flex items-center justify-center">
                  <ChevronRight class="h-5 w-5 kiosk-nudge" />
                </div>
              </div> -->
            </button>
          </div>

          <!-- Loading Skeleton -->
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-7">
            <div v-for="i in 3" :key="i" class="rounded-[28px] border border-white/90 bg-white/70 backdrop-blur-xl p-6 sm:p-7 animate-pulse shadow-lg shadow-slate-900/5">
              <div class="h-[72px] w-[72px] rounded-[22px] bg-slate-200 mb-6"></div>
              <div class="h-6 w-40 bg-slate-200 rounded-lg mb-3"></div>
              <div class="h-8 w-44 bg-slate-100 rounded-full"></div>
            </div>
          </div>
        </div>
      </Transition>

      <!-- ── STEP 2: Camera Capture ───────────────────────────────────── -->
      <Transition name="fade" mode="out-in">
        <div v-if="step === 'camera'" key="camera" class="w-full max-w-xl text-center animate-slide-up-bounce">
          <div class="bg-white/80 backdrop-blur-xl rounded-[32px] border border-white shadow-[0_30px_80px_-20px_rgba(14,116,204,0.35)] p-7 sm:p-9">
            <div class="flex items-center justify-center gap-3 mb-2">
              <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shadow-lg shadow-sky-500/30">
                <Camera class="h-6 w-6 text-white" />
              </div>
              <h3 class="text-2xl font-black text-slate-800">Foto Pengunjung</h3>
            </div>
            <p v-if="selectedService" class="text-sm text-slate-500 font-medium mb-6">
              Layanan: <span class="font-bold text-sky-600">{{ selectedService.name }}</span>
            </p>

            <div class="relative rounded-3xl overflow-hidden bg-slate-900 mb-7 aspect-[4/3] ring-4 ring-white shadow-2xl shadow-slate-900/20">
              <video ref="videoRef" autoplay playsinline muted class="w-full h-full object-cover"></video>
              <canvas ref="canvasRef" class="hidden"></canvas>
              <!-- Camera frame overlay -->
              <div class="absolute inset-6 pointer-events-none">
                <span class="absolute top-0 left-0 h-9 w-9 border-t-4 border-l-4 border-white/90 rounded-tl-2xl"></span>
                <span class="absolute top-0 right-0 h-9 w-9 border-t-4 border-r-4 border-white/90 rounded-tr-2xl"></span>
                <span class="absolute bottom-0 left-0 h-9 w-9 border-b-4 border-l-4 border-white/90 rounded-bl-2xl"></span>
                <span class="absolute bottom-0 right-0 h-9 w-9 border-b-4 border-r-4 border-white/90 rounded-br-2xl"></span>
              </div>
              <div class="absolute top-4 left-4 flex items-center gap-2 px-3 py-1 rounded-full bg-black/40 backdrop-blur text-white text-xs font-bold">
                <span class="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span> LIVE
              </div>
            </div>

            <div class="flex gap-4">
              <button
                @click="cancelCamera"
                class="flex-1 h-16 rounded-2xl bg-white border-2 border-slate-200 text-slate-600 font-bold text-lg hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-[0.97]"
              >
                Batal
              </button>
              <button
                @click="capturePhoto"
                class="flex-[1.4] h-16 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 text-white font-bold text-lg shadow-xl shadow-sky-500/35 hover:shadow-2xl hover:shadow-sky-500/45 hover:-translate-y-0.5 transition-all active:scale-[0.97] flex items-center justify-center gap-2.5"
              >
                <Camera class="h-6 w-6" />
                Ambil Foto
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- ── STEP 3: Processing / Printing ────────────────────────────── -->
      <Transition name="fade" mode="out-in">
        <div v-if="step === 'printing'" key="printing" class="text-center animate-slide-up-bounce">
          <div class="mb-10 relative h-32 w-32 mx-auto">
            <span class="absolute inset-0 rounded-[36px] bg-sky-400/30 animate-ping"></span>
            <span class="absolute -inset-4 rounded-[44px] border-2 border-sky-300/40 animate-pulse"></span>
            <div class="relative h-32 w-32 rounded-[36px] bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shadow-2xl shadow-sky-500/40">
              <Printer class="h-16 w-16 text-white" />
            </div>
          </div>
          <h3 class="text-3xl font-black text-slate-800 mb-2">Memproses Cetak...</h3>
          <p class="text-slate-500 font-medium text-lg">Mohon tunggu sebentar</p>

          <div class="mt-9 max-w-xs mx-auto h-2 bg-white/80 rounded-full overflow-hidden shadow-inner ring-1 ring-slate-200/70">
            <div class="h-full bg-gradient-to-r from-sky-400 via-blue-500 to-sky-400 rounded-full kiosk-progress w-1/3"></div>
          </div>
        </div>
      </Transition>

      <!-- ── STEP 4: Result / Ticket ──────────────────────────────────── -->
      <Transition name="fade" mode="out-in">
        <div v-if="step === 'result' && resultData" key="result" class="w-full max-w-md text-center animate-slide-up-bounce">
          <div class="relative">
            <div class="relative bg-white/90 backdrop-blur-xl rounded-[32px] border border-white shadow-[0_30px_80px_-20px_rgba(14,116,204,0.4)] overflow-hidden">

              <!-- Top gradient band -->
              <div class="h-2 bg-gradient-to-r from-emerald-400 via-sky-500 to-blue-600"></div>
              <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-72 h-72 bg-gradient-to-b from-sky-300/30 to-transparent rounded-full blur-3xl pointer-events-none"></div>

              <div class="relative z-10 px-8 sm:px-10 pt-9 pb-7">
                <!-- Checkmark -->
                <div class="mb-6">
                  <div class="h-[72px] w-[72px] mx-auto rounded-3xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-xl shadow-emerald-500/35 ring-8 ring-emerald-50">
                    <svg class="h-9 w-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                </div>

                <!-- Queue Number (HERO) -->
                <div class="mb-7">
                  <p class="text-xs font-bold text-slate-500 uppercase tracking-[0.22em] mb-3">Nomor Antrean Anda</p>
                  <div class="animate-number-pop">
                    <h2 class="text-8xl sm:text-9xl font-black tracking-tighter text-transparent bg-clip-text bg-gradient-to-br from-sky-500 via-blue-600 to-violet-600 leading-none drop-shadow-sm">
                      {{ resultData.queue_number }}
                    </h2>
                  </div>
                </div>

                <!-- Details -->
                <div class="space-y-2.5 text-left">
                  <div class="flex items-center justify-between px-5 py-3.5 rounded-2xl bg-gradient-to-r from-slate-50 to-white border border-slate-100">
                    <span class="text-sm text-slate-500 font-medium">Layanan</span>
                    <span class="text-sm font-bold text-slate-800">{{ resultData.service_name }}</span>
                  </div>
                  <div class="flex items-center justify-between px-5 py-3.5 rounded-2xl bg-gradient-to-r from-sky-50 to-white border border-sky-100">
                    <span class="text-sm text-slate-500 font-medium">Posisi Antrean</span>
                    <span class="text-sm font-black text-sky-600">Ke-{{ resultData.position }}</span>
                  </div>
                  <div class="flex items-center justify-between px-5 py-3.5 rounded-2xl bg-gradient-to-r from-slate-50 to-white border border-slate-100">
                    <span class="text-sm text-slate-500 font-medium">Tanggal</span>
                    <span class="text-sm font-bold text-slate-800">{{ resultData.date }}</span>
                  </div>
                </div>
              </div>

              <!-- Ticket perforation -->
              <div class="relative z-10 flex items-center">
                <span class="h-6 w-6 -ml-3 rounded-full bg-gradient-to-br from-slate-50 to-sky-50 shadow-inner shrink-0"></span>
                <span class="flex-1 border-t-2 border-dashed border-slate-200"></span>
                <span class="h-6 w-6 -mr-3 rounded-full bg-gradient-to-br from-slate-50 to-sky-50 shadow-inner shrink-0"></span>
              </div>

              <div class="relative z-10 px-8 sm:px-10 pt-5 pb-8">
                <p class="text-xs text-slate-400 font-medium mb-5">
                  Scan QR Code di tiket untuk memantau antrean dari HP Anda
                </p>
                <button
                  @click="resetToSelect"
                  class="h-12 px-7 rounded-2xl bg-gradient-to-br from-slate-100 to-slate-50 border border-slate-200 text-slate-600 font-bold text-sm hover:from-sky-50 hover:to-white hover:text-sky-700 hover:border-sky-200 transition-all active:scale-[0.97] flex items-center gap-2 mx-auto"
                >
                  <RefreshCw class="h-4 w-4" />
                  Ambil Tiket Lagi
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </main>

    <!-- ══ FOOTER ══════════════════════════════════════════════════════════ -->
    <footer class="relative z-10 text-center py-5 px-6">
      <p class="text-xs text-slate-400 font-medium">
        &copy; {{ new Date().getFullYear() }} {{ settings.app_name }} &bull; Powered by PT. Pintu Data Indonesia
      </p>
    </footer>
  </div>
</template>

<style scoped>
.fade-enter-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-enter-from { opacity: 0; transform: translateY(10px); }
.fade-leave-to { opacity: 0; transform: translateY(-10px); }

.kiosk-grid {
  background-image: radial-gradient(circle, rgba(100, 116, 139, 0.18) 1px, transparent 1px);
  background-size: 28px 28px;
  mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
  -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
}

.kiosk-blob { animation: kiosk-float 18s ease-in-out infinite; }
@keyframes kiosk-float {
  0%, 100% { transform: translate(0, 0) scale(1); }
  50% { transform: translate(-30px, 24px) scale(1.08); }
}

.kiosk-shine {
  background: linear-gradient(115deg, transparent 35%, rgba(255, 255, 255, 0.65) 50%, transparent 65%);
  transform: translateX(-120%);
  transition: transform 0.9s ease;
}
.group:hover .kiosk-shine { transform: translateX(120%); }

.kiosk-progress { animation: kiosk-progress 1.4s ease-in-out infinite; }
@keyframes kiosk-progress {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(300%); }
}
</style>

<style scoped>
.kiosk-tap { animation: kiosk-tap 1.6s ease-in-out infinite; }
@keyframes kiosk-tap {
  0%, 100% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-3px) scale(0.9); }
}
.kiosk-nudge { animation: kiosk-nudge 1.2s ease-in-out infinite; }
@keyframes kiosk-nudge {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(4px); }
}
</style>
