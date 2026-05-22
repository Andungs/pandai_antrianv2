<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api } from '@/stores/auth'
import { Settings, Upload, ShieldCheck, Save, Loader2 } from 'lucide-vue-next'
import Swal from 'sweetalert2'
const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8092/api'
const loading = ref(true)
const saving = ref(false)
const settingsData = ref<Record<string, string>>({})
const generatingCert = ref(false)

async function fetchSettings() {
  loading.value = true
  try {
    const res = await api.get('/admin/settings')
    settingsData.value = res.data.data
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function saveSettings() {
  saving.value = true
  try {
    await api.put('/admin/settings', {
      settings: {
        app_name: settingsData.value.app_name ?? 'Pandai Antrian',
        enable_camera: settingsData.value.enable_camera ?? 'false',
        printer_name: settingsData.value.printer_name ?? '',
      }
    })
    Swal.fire('Berhasil', 'Pengaturan berhasil disimpan', 'success')
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal menyimpan', 'error')
  } finally { saving.value = false }
}

async function uploadLogo(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  const formData = new FormData()
  formData.append('logo', file)
  try {
    const res = await api.post('/admin/settings/upload-logo', formData)
    settingsData.value.app_logo = res.data.data.app_logo
    Swal.fire('Berhasil', 'Logo berhasil diupload', 'success')
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal upload', 'error')
  }
}

async function generateCert() {
  generatingCert.value = true
  try {
    const res = await api.post('/admin/settings/qztray-certificate')
    Swal.fire('Berhasil', res.data.message, 'success')
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal generate sertifikat', 'error')
  } finally { generatingCert.value = false }
}

onMounted(fetchSettings)
</script>

<template>
  <div class="space-y-6 max-w-2xl">
    <div>
      <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white">Pengaturan</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Konfigurasi aplikasi Pandai Antrian</p>
    </div>

    <div v-if="!loading" class="space-y-6">
      <!-- General Settings -->
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/80 p-6 space-y-5">
        <div class="flex items-center gap-2 mb-2">
          <Settings class="h-5 w-5 text-sky-500" />
          <h3 class="text-sm font-bold text-slate-800 dark:text-white">Umum</h3>
        </div>

        <div class="space-y-2">
          <Label>Nama Aplikasi</Label>
          <Input v-model="settingsData.app_name" placeholder="Pandai Antrian" />
        </div>

        <div class="space-y-2">
          <Label>Logo Aplikasi</Label>
          <div class="flex items-center gap-4">
            <img v-if="settingsData.app_logo" :src="settingsData.app_logo" alt="Logo" class="h-12 w-12 rounded-xl object-cover border" />
            <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-all">
              <Upload class="h-4 w-4" />
              Upload Logo
              <input type="file" accept="image/*" class="hidden" @change="uploadLogo" />
            </label>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <input type="checkbox" id="enable-camera" v-model="settingsData.enable_camera" true-value="true" false-value="false" class="rounded" />
          <Label for="enable-camera">Aktifkan Kamera KIOSK (foto pengunjung)</Label>
        </div>

        <div class="space-y-2">
          <Label>Nama Printer KIOSK (QZTray)</Label>
          <Input v-model="settingsData.printer_name" placeholder="Contoh: POS-80C" />
          <p class="text-xs text-slate-500">Pastikan nama sama persis dengan yang ada di sistem operasi tempat KIOSK berjalan.</p>
        </div>

        <Button @click="saveSettings" :disabled="saving" class="gap-2">
          <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
          <Save v-else class="h-4 w-4" />
          {{ saving ? 'Menyimpan...' : 'Simpan Pengaturan' }}
        </Button>
      </div>

      <!-- QZTray Certificate -->
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/80 p-6 space-y-4">
        <div class="flex items-center gap-2 mb-2">
          <ShieldCheck class="h-5 w-5 text-emerald-500" />
          <h3 class="text-sm font-bold text-slate-800 dark:text-white">Sertifikat QZTray</h3>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          Generate sertifikat digital untuk menghubungkan browser dengan printer thermal KIOSK melalui QZTray.
          Setelah di-generate, import sertifikat ke QZTray untuk mengaktifkan silent printing.
        </p>
        <div class="flex gap-3">
          <Button @click="generateCert" :disabled="generatingCert" variant="outline" class="gap-2">
            <Loader2 v-if="generatingCert" class="h-4 w-4 animate-spin" />
            {{ generatingCert ? 'Generating...' : 'Generate Sertifikat' }}
          </Button>
          <a :href="`${API_BASE}/guest/settings/qztray-certificate`" target="_blank">
            <Button variant="outline" class="gap-2">
              <Upload class="h-4 w-4" />
              Download Sertifikat
            </Button>
          </a>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-else class="space-y-6">
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/80 p-6 space-y-4">
        <div class="skeleton h-5 w-32"></div>
        <div class="skeleton h-10 w-full"></div>
        <div class="skeleton h-10 w-full"></div>
      </div>
    </div>
  </div>
</template>
