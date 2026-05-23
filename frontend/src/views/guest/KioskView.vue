<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { TicketCheck, Clock, Users, ChevronRight, Camera, Printer, X, RefreshCw } from 'lucide-vue-next'
import axios from 'axios'
import { useQZTray } from '@/composables/useQZTray'

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

// Service card colors
const serviceColors = [
  { bg: 'from-sky-500 to-blue-600', light: 'bg-sky-50 dark:bg-sky-950/30', text: 'text-sky-600 dark:text-sky-400', border: 'border-sky-200 dark:border-sky-800/40', ring: 'ring-sky-500/20' },
  { bg: 'from-violet-500 to-purple-600', light: 'bg-violet-50 dark:bg-violet-950/30', text: 'text-violet-600 dark:text-violet-400', border: 'border-violet-200 dark:border-violet-800/40', ring: 'ring-violet-500/20' },
  { bg: 'from-emerald-500 to-teal-600', light: 'bg-emerald-50 dark:bg-emerald-950/30', text: 'text-emerald-600 dark:text-emerald-400', border: 'border-emerald-200 dark:border-emerald-800/40', ring: 'ring-emerald-500/20' },
  { bg: 'from-amber-500 to-orange-600', light: 'bg-amber-50 dark:bg-amber-950/30', text: 'text-amber-600 dark:text-amber-400', border: 'border-amber-200 dark:border-amber-800/40', ring: 'ring-amber-500/20' },
  { bg: 'from-rose-500 to-pink-600', light: 'bg-rose-50 dark:bg-rose-950/30', text: 'text-rose-600 dark:text-rose-400', border: 'border-rose-200 dark:border-rose-800/40', ring: 'ring-rose-500/20' },
  { bg: 'from-cyan-500 to-blue-500', light: 'bg-cyan-50 dark:bg-cyan-950/30', text: 'text-cyan-600 dark:text-cyan-400', border: 'border-cyan-200 dark:border-cyan-800/40', ring: 'ring-cyan-500/20' },
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

onMounted(() => {
  fetchServices()
  connect().catch(e => console.error('Initial QZTray connect error:', e))
  // Update clock
  setInterval(() => { currentTime.value = new Date() }, 1000)
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-sky-50/30 to-blue-50/20 dark:from-[#080c14] dark:via-[#0a1628] dark:to-[#080c14] flex flex-col font-sans overflow-hidden relative">
    
    <!-- Decorative background elements -->
    <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-gradient-to-bl from-sky-400/8 to-transparent rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-gradient-to-tr from-blue-400/8 to-transparent rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-gradient-to-r from-sky-500/5 via-purple-500/5 to-sky-500/5 rounded-full blur-[100px] pointer-events-none"></div>

    <!-- ══ HEADER ══════════════════════════════════════════════════════════ -->
    <header class="relative z-10 flex items-center justify-between px-6 sm:px-10 py-5">
      <div class="flex items-center gap-3">
        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shadow-lg shadow-sky-500/30">
          <TicketCheck class="h-6 w-6 text-white" />
        </div>
        <div>
          <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">{{ settings.app_name }}</h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Sistem Antrean Digital</p>
        </div>
      </div>
      <div class="text-right">
        <p class="text-3xl font-black tracking-tight text-slate-800 dark:text-white tabular-nums">{{ formattedTime }}</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">{{ formattedDate }}</p>
      </div>
    </header>

    <!-- ══ MAIN CONTENT ════════════════════════════════════════════════════ -->
    <main class="flex-1 flex items-center justify-center px-6 sm:px-10 py-6 relative z-10">

      <!-- ── STEP 1: Service Selection ────────────────────────────────── -->
      <Transition name="fade" mode="out-in">
        <div v-if="step === 'select'" key="select" class="w-full max-w-5xl">
          
          <!-- Title -->
          <div class="text-center mb-10 animate-fadeup">
            <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-800 dark:text-white mb-3">
              Pilih Layanan
            </h2>
            <p class="text-slate-500 dark:text-slate-400 text-base sm:text-lg font-medium max-w-lg mx-auto">
              Sentuh layanan yang Anda inginkan untuk mendapatkan nomor antrean
            </p>
          </div>

          <!-- Error Alert -->
          <div v-if="processingError" class="max-w-lg mx-auto mb-8 flex items-center gap-3 p-4 rounded-2xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/40 text-red-600 dark:text-red-400 text-sm font-medium animate-fadeup">
            <X class="h-5 w-5 shrink-0" />
            {{ processingError }}
          </div>

          <!-- Service Grid -->
          <div v-if="!loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
            <button
              v-for="(service, index) in services"
              :key="service.id"
              @click="selectService(service)"
              class="group relative rounded-3xl border-2 bg-white dark:bg-slate-900/80 backdrop-blur-sm p-6 sm:p-8 text-left transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 active:scale-[0.98] animate-fadeup overflow-hidden"
              :class="[getColor(index).border, 'hover:ring-4', getColor(index).ring]"
              :style="{ animationDelay: `${index * 80}ms` }"
            >
              <!-- Gradient accent bar -->
              <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r opacity-80 group-hover:opacity-100 transition-opacity" :class="getColor(index).bg"></div>
              
              <!-- Glow circle -->
              <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full opacity-30 blur-2xl pointer-events-none transition-opacity group-hover:opacity-50" :class="getColor(index).light"></div>

              <!-- Icon -->
              <div class="relative z-10 mb-5">
                <div 
                  class="h-16 w-16 rounded-2xl bg-gradient-to-br flex items-center justify-center shadow-lg transition-transform duration-300 group-hover:scale-110"
                  :class="getColor(index).bg"
                >
                  <span class="text-3xl font-black text-white">{{ service.prefix_code }}</span>
                </div>
              </div>

              <!-- Content -->
              <div class="relative z-10">
                <h3 class="text-lg sm:text-xl font-bold text-slate-800 dark:text-white mb-2 group-hover:text-slate-900 dark:group-hover:text-white transition-colors">
                  {{ service.name }}
                </h3>
                <div class="flex items-center gap-2 text-sm font-medium" :class="getColor(index).text">
                  <Users class="h-4 w-4" />
                  <span>{{ service.waiting_count }} orang menunggu</span>
                </div>
              </div>

              <!-- Arrow indicator -->
              <div class="absolute bottom-6 right-6 opacity-0 group-hover:opacity-100 transition-all duration-300 translate-x-2 group-hover:translate-x-0">
                <ChevronRight class="h-6 w-6 text-slate-400 dark:text-slate-500" />
              </div>
            </button>
          </div>

          <!-- Loading Skeleton -->
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
            <div v-for="i in 3" :key="i" class="rounded-3xl border-2 border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/80 p-6 sm:p-8 animate-pulse">
              <div class="h-16 w-16 rounded-2xl bg-slate-200 dark:bg-slate-800 mb-5"></div>
              <div class="h-5 w-32 bg-slate-200 dark:bg-slate-800 rounded-lg mb-3"></div>
              <div class="h-4 w-40 bg-slate-100 dark:bg-slate-800/60 rounded-lg"></div>
            </div>
          </div>
        </div>
      </Transition>

      <!-- ── STEP 2: Camera Capture ───────────────────────────────────── -->
      <Transition name="fade" mode="out-in">
        <div v-if="step === 'camera'" key="camera" class="w-full max-w-lg text-center animate-slide-up-bounce">
          <div class="bg-white dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border-2 border-slate-200 dark:border-slate-800 p-8 shadow-2xl">
            <div class="flex items-center justify-center gap-2 mb-6">
              <Camera class="h-6 w-6 text-sky-500" />
              <h3 class="text-xl font-bold text-slate-800 dark:text-white">Foto Pengunjung</h3>
            </div>
            
            <div class="relative rounded-2xl overflow-hidden bg-black mb-6 aspect-[4/3]">
              <video ref="videoRef" autoplay playsinline muted class="w-full h-full object-cover"></video>
              <canvas ref="canvasRef" class="hidden"></canvas>
              <!-- Camera frame overlay -->
              <div class="absolute inset-0 border-4 border-white/20 rounded-2xl pointer-events-none"></div>
              <div class="absolute inset-8 border-2 border-dashed border-white/30 rounded-xl pointer-events-none"></div>
            </div>

            <div class="flex gap-3">
              <button
                @click="cancelCamera"
                class="flex-1 h-14 rounded-2xl border-2 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-bold text-base hover:bg-slate-50 dark:hover:bg-slate-800 transition-all"
              >
                Batal
              </button>
              <button
                @click="capturePhoto"
                class="flex-1 h-14 rounded-2xl bg-gradient-to-r from-sky-500 to-blue-600 text-white font-bold text-base shadow-lg shadow-sky-500/30 hover:shadow-xl hover:shadow-sky-500/40 transition-all active:scale-[0.98] flex items-center justify-center gap-2"
              >
                <Camera class="h-5 w-5" />
                Ambil Foto
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- ── STEP 3: Processing / Printing ────────────────────────────── -->
      <Transition name="fade" mode="out-in">
        <div v-if="step === 'printing'" key="printing" class="text-center animate-slide-up-bounce">
          <div class="mb-8">
            <div class="h-24 w-24 mx-auto rounded-3xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shadow-2xl shadow-sky-500/30 animate-pulse">
              <Printer class="h-12 w-12 text-white" />
            </div>
          </div>
          <h3 class="text-2xl font-black text-slate-800 dark:text-white mb-2">Memproses Cetak...</h3>
          <p class="text-slate-500 dark:text-slate-400 font-medium">Mohon tunggu sebentar</p>
          
          <!-- Loading bar -->
          <div class="mt-8 max-w-xs mx-auto h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-sky-500 to-blue-600 rounded-full animate-[shimmer_1.5s_ease-in-out_infinite] w-2/3"></div>
          </div>
        </div>
      </Transition>

      <!-- ── STEP 4: Result / Ticket ──────────────────────────────────── -->
      <Transition name="fade" mode="out-in">
        <div v-if="step === 'result' && resultData" key="result" class="w-full max-w-md text-center">
          <div class="bg-white dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border-2 border-sky-200 dark:border-sky-800/40 p-8 sm:p-10 shadow-2xl shadow-sky-500/10 animate-slide-up-bounce relative overflow-hidden">
            
            <!-- Success glow -->
            <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-60 h-60 bg-gradient-to-b from-sky-400/20 to-transparent rounded-full blur-3xl pointer-events-none"></div>

            <!-- Checkmark -->
            <div class="relative z-10 mb-6">
              <div class="h-16 w-16 mx-auto rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/30">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </div>
            </div>

            <!-- Queue Number (HERO) -->
            <div class="relative z-10 mb-6">
              <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Nomor Antrean Anda</p>
              <div class="animate-number-pop">
                <h2 class="text-7xl sm:text-8xl font-black tracking-tighter text-transparent bg-clip-text bg-gradient-to-br from-sky-500 to-blue-600 leading-none">
                  {{ resultData.queue_number }}
                </h2>
              </div>
            </div>

            <!-- Details -->
            <div class="relative z-10 space-y-3 mb-8">
              <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">Layanan</span>
                <span class="text-sm font-bold text-slate-800 dark:text-white">{{ resultData.service_name }}</span>
              </div>
              <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">Posisi Antrean</span>
                <span class="text-sm font-bold text-sky-600 dark:text-sky-400">Ke-{{ resultData.position }}</span>
              </div>
              <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">Tanggal</span>
                <span class="text-sm font-bold text-slate-800 dark:text-white">{{ resultData.date }}</span>
              </div>
            </div>

            <!-- QR Code placeholder text -->
            <div class="relative z-10 pt-6 border-t-2 border-dashed border-slate-200 dark:border-slate-700">
              <p class="text-xs text-slate-400 dark:text-slate-500 font-medium">
                Scan QR Code di tiket untuk memantau antrean dari HP Anda
              </p>
            </div>

            <!-- Auto-reset countdown -->
            <div class="relative z-10 mt-6">
              <button
                @click="resetToSelect"
                class="h-12 px-6 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold text-sm hover:bg-slate-200 dark:hover:bg-slate-700 transition-all flex items-center gap-2 mx-auto"
              >
                <RefreshCw class="h-4 w-4" />
                Ambil Tiket Lagi
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </main>

    <!-- ══ FOOTER ══════════════════════════════════════════════════════════ -->
    <footer class="relative z-10 text-center py-4 px-6">
      <p class="text-xs text-slate-400 dark:text-slate-600 font-medium">
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
</style>
