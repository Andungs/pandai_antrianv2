<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Orientation, GroupedBar } from '@unovis/ts'
import { VisXYContainer, VisGroupedBar, VisAxis, VisTooltip } from '@unovis/vue'
import dayjs from 'dayjs'
import { CalendarRange, Timer, Hourglass, BarChart3 } from 'lucide-vue-next'
import { api } from '@/stores/auth'

interface Row { service_name: string; prefix_code: string; served: number; avg_service_time: number; avg_wait_time: number }

const FMT = 'YYYY-MM-DD'
const today = dayjs().format(FMT)
const to = ref(today)
const from = ref(dayjs().subtract(6, 'day').format(FMT))
const rows = ref<Row[]>([])
const loading = ref(true)
const error = ref('')

const SERVICE_COLOR = '#8b5cf6'
const WAIT_COLOR = '#f59e0b'

function onFromChange() {
  if (!from.value) return
  const f = dayjs(from.value)
  if (dayjs(to.value).isBefore(f) || dayjs(to.value).diff(f, 'day') > 6) {
    const cand = f.add(6, 'day')
    to.value = (cand.isAfter(dayjs()) ? dayjs() : cand).format(FMT)
  }
  fetchData()
}
function onToChange() {
  if (!to.value) return
  const t = dayjs(to.value)
  if (dayjs(from.value).isAfter(t) || t.diff(dayjs(from.value), 'day') > 6) {
    from.value = t.subtract(6, 'day').format(FMT)
  }
  fetchData()
}
function resetRange() {
  to.value = today
  from.value = dayjs().subtract(6, 'day').format(FMT)
  fetchData()
}

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/dashboard/service-time', { params: { from: from.value, to: to.value } })
    rows.value = res.data.data
  } catch (e: any) {
    rows.value = []
    error.value = e?.response?.data?.message ?? 'Gagal memuat data grafik'
  } finally {
    loading.value = false
  }
}
onMounted(fetchData)

// Layanan paling lambat (kandidat utama penambahan loket)
const slowest = computed(() => rows.value[0] ?? null)
const chartHeight = computed(() => Math.max(220, rows.value.length * 72 + 60))

const x = (_: Row, i: number) => i
const yService = (d: Row) => d.avg_service_time
const yWait = (d: Row) => d.avg_wait_time
const yAccessors = [yService, yWait]
const barColor = (_: Row, i: number) => (i === 0 ? SERVICE_COLOR : WAIT_COLOR)

const labelFormat = (i: number) => {
  const r = rows.value[Math.round(i)]
  return r && Number.isInteger(i) ? `${r.prefix_code} · ${r.service_name}` : ''
}
const tickValues = computed(() => rows.value.map((_, i) => i))

const triggers = computed(() => ({
  [GroupedBar.selectors.bar]: (d: Row) => `
    <div style="min-width:190px;font-family:inherit">
      <div style="font-weight:800;font-size:12px;color:#0f172a;margin-bottom:8px">${d.prefix_code} · ${d.service_name}</div>
      <div style="display:flex;justify-content:space-between;gap:16px;font-size:12px;margin-top:4px">
        <span style="color:#475569"><span style="display:inline-block;width:8px;height:8px;border-radius:9999px;background:${SERVICE_COLOR};margin-right:6px"></span>Waktu melayani</span><b>${d.avg_service_time} mnt</b></div>
      <div style="display:flex;justify-content:space-between;gap:16px;font-size:12px;margin-top:4px">
        <span style="color:#475569"><span style="display:inline-block;width:8px;height:8px;border-radius:9999px;background:${WAIT_COLOR};margin-right:6px"></span>Waktu tunggu</span><b>${d.avg_wait_time} mnt</b></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:8px">${d.served} antrean dilayani</div>
    </div>`,
}))
</script>

<template>
  <div class="relative overflow-hidden rounded-3xl border border-slate-200/80 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl shadow-xl shadow-slate-200/50 dark:shadow-black/20">
    <div class="pointer-events-none absolute -top-24 -left-24 h-64 w-64 rounded-full bg-gradient-to-br from-violet-400/20 to-fuchsia-400/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 -right-24 h-64 w-64 rounded-full bg-gradient-to-tl from-amber-300/15 to-violet-400/10 blur-3xl"></div>

    <div class="relative p-6">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg shadow-violet-500/30">
            <Timer class="h-5 w-5 text-white" />
          </div>
          <div>
            <h3 class="text-base font-black tracking-tight text-slate-800 dark:text-white">Rata-rata Waktu Melayani per Layanan</h3>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div class="flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-800/60 px-3 py-1.5 shadow-sm">
            <CalendarRange class="h-4 w-4 text-violet-500" />
            <input type="date" v-model="from" :max="to" @change="onFromChange"
              class="bg-transparent text-xs font-semibold text-slate-700 dark:text-slate-200 outline-none" />
            <span class="text-slate-400 text-xs">→</span>
            <input type="date" v-model="to" :max="today" :min="from" @change="onToChange"
              class="bg-transparent text-xs font-semibold text-slate-700 dark:text-slate-200 outline-none" />
          </div>
          <button @click="resetRange"
            class="rounded-2xl px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-violet-500 to-purple-600 shadow-md shadow-violet-500/30 hover:shadow-lg hover:-translate-y-0.5 transition-all">
            7 Hari Terakhir
          </button>
        </div>
      </div>

      <!-- Legend + insight -->
      <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="flex items-center gap-3 rounded-2xl border border-slate-100 dark:border-slate-800 bg-white/60 dark:bg-slate-800/40 px-4 py-3">
          <div class="h-9 w-9 rounded-xl flex items-center justify-center" :style="{ background: SERVICE_COLOR + '1f' }">
            <Timer class="h-4 w-4" :style="{ color: SERVICE_COLOR }" />
          </div>
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Waktu Melayani</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">dipanggil → selesai</p>
          </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl border border-slate-100 dark:border-slate-800 bg-white/60 dark:bg-slate-800/40 px-4 py-3">
          <div class="h-9 w-9 rounded-xl flex items-center justify-center" :style="{ background: WAIT_COLOR + '1f' }">
            <Hourglass class="h-4 w-4" :style="{ color: WAIT_COLOR }" />
          </div>
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Waktu Tunggu</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">ambil tiket → dipanggil</p>
          </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl border border-violet-100 dark:border-violet-900/40 bg-gradient-to-br from-violet-50 to-white dark:from-violet-950/30 dark:to-slate-900/40 px-4 py-3">
          <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-md shadow-violet-500/30">
            <BarChart3 class="h-4 w-4 text-white" />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Layanan Terlama</p>
            <p v-if="slowest" class="text-sm font-black text-slate-800 dark:text-white truncate">
              {{ slowest.service_name }} · {{ slowest.avg_service_time }} mnt
            </p>
            <p v-else class="text-sm text-slate-400">-</p>
          </div>
        </div>
      </div>

      <div class="mt-6" :style="{ height: loading || error || rows.length === 0 ? '260px' : chartHeight + 'px' }">
        <div v-if="loading" class="h-full w-full skeleton rounded-2xl"></div>
        <div v-else-if="error" class="h-full flex items-center justify-center text-sm text-red-500">{{ error }}</div>
        <div v-else-if="rows.length === 0" class="h-full flex flex-col items-center justify-center text-slate-400 gap-2">
          <Timer class="h-10 w-10 opacity-40" />
          <p class="text-sm">Belum ada antrean yang selesai dilayani pada rentang ini</p>
        </div>
        <VisXYContainer v-else :data="rows" :padding="{ top: 8, right: 32, bottom: 8, left: 8 }" class="service-chart">
          <VisGroupedBar
            :x="x" :y="yAccessors" :color="barColor"
            :orientation="Orientation.Horizontal"
            :rounded-corners="8" :group-padding="0.35" :bar-padding="0.15"
          />
          <VisAxis type="y" :tick-values="tickValues" :tick-format="labelFormat" :grid-line="false" :domain-line="false" :tick-line="false" />
          <VisAxis type="x" :num-ticks="5" :tick-format="(v: number) => `${v} mnt`" :domain-line="false" :tick-line="false" />
          <VisTooltip :triggers="triggers" />
        </VisXYContainer>
      </div>
    </div>
  </div>
</template>

<style>
.service-chart {
  --vis-axis-tick-label-color: #64748b;
  --vis-axis-tick-label-font-size: 11px;
  --vis-axis-grid-color: rgba(148, 163, 184, 0.25);
  --vis-tooltip-background-color: rgba(255, 255, 255, 0.92);
  --vis-tooltip-border-color: rgba(226, 232, 240, 0.9);
  --vis-tooltip-text-color: #0f172a;
  --vis-tooltip-padding: 12px 14px;
  --vis-tooltip-backdrop-filter: blur(10px);
}
</style>
