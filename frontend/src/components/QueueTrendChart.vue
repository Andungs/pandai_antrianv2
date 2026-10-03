<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { CurveType } from '@unovis/ts'
import {
  VisXYContainer, VisLine, VisArea, VisScatter, VisAxis, VisCrosshair, VisTooltip,
} from '@unovis/vue'
import dayjs from 'dayjs'
import 'dayjs/locale/id'
import { CalendarRange, TrendingUp, BarChart3, Users, CheckCircle2 } from 'lucide-vue-next'
import { api } from '@/stores/auth'

dayjs.locale('id')

interface Point { date: string; total: number; waiting: number; served: number }

const FMT = 'YYYY-MM-DD'
const to = ref(dayjs().format(FMT))
const from = ref(dayjs().subtract(6, 'day').format(FMT))
const points = ref<Point[]>([])
const loading = ref(true)
const error = ref('')

const today = dayjs().format(FMT)

// Series definition (3 indikator utama)
const series = [
  { key: 'total', label: 'Total Antrean', color: '#0ea5e9', icon: BarChart3 },
  { key: 'waiting', label: 'Menunggu', color: '#f59e0b', icon: Users },
  { key: 'served', label: 'Sudah Dilayani', color: '#10b981', icon: CheckCircle2 },
] as const

const sum = (k: 'total' | 'waiting' | 'served') => points.value.reduce((a, p) => a + p[k], 0)

// Rentang selalu maksimal 7 hari: sesuaikan tanggal lawan saat salah satu berubah
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
    const res = await api.get('/admin/dashboard/history', { params: { from: from.value, to: to.value } })
    points.value = res.data.data
  } catch (e: any) {
    points.value = []
    error.value = e?.response?.data?.message ?? 'Gagal memuat data grafik'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)

// Chart accessors (x = index karena hanya hari yang memiliki data yang ditampilkan)
const x = (_: Point, i: number) => i
const yTotal = (d: Point) => d.total
const yWaiting = (d: Point) => d.waiting
const yServed = (d: Point) => d.served
const accessors = { total: yTotal, waiting: yWaiting, served: yServed }

const tickFormat = (i: number) => {
  const p = points.value[Math.round(i)]
  return p && Number.isInteger(i) ? dayjs(p.date).format('ddd, D MMM') : ''
}
const tickValues = computed(() => points.value.map((_, i) => i))

// Domain x diberi ruang agar titik tidak menempel di tepi
const xDomain = computed<[number, number]>(() => [-0.3, Math.max(points.value.length - 1, 0) + 0.3])

const tooltipTemplate = (d: Point) => `
  <div style="min-width:180px;font-family:inherit">
    <div style="font-weight:800;font-size:12px;color:#0f172a;margin-bottom:8px">${dayjs(d.date).format('dddd, D MMMM YYYY')}</div>
    ${series.map(s => `
      <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;font-size:12px;margin-top:4px">
        <span style="display:flex;align-items:center;gap:6px;color:#475569">
          <span style="width:8px;height:8px;border-radius:9999px;background:${s.color};box-shadow:0 0 8px ${s.color}"></span>${s.label}
        </span>
        <b style="color:#0f172a">${d[s.key]}</b>
      </div>`).join('')}
  </div>`

const crosshairY = [yTotal, yWaiting, yServed]
const crosshairColor = (_: Point, i: number) => series[i % 3]!.color
</script>

<template>
  <div class="relative overflow-hidden rounded-3xl border border-slate-200/80 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl shadow-xl shadow-slate-200/50 dark:shadow-black/20">
    <!-- soft glow background -->
    <div class="pointer-events-none absolute -top-24 -right-24 h-64 w-64 rounded-full bg-gradient-to-br from-sky-400/20 to-emerald-400/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-gradient-to-tr from-amber-300/15 to-sky-400/10 blur-3xl"></div>

    <div class="relative p-6">
      <!-- Header + filter -->
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center shadow-lg shadow-sky-500/30">
            <TrendingUp class="h-5 w-5 text-white" />
          </div>
          <div>
            <h3 class="text-base font-black tracking-tight text-slate-800 dark:text-white">Statistik Antrean</h3>
            <!-- <p class="text-xs text-slate-500 dark:text-slate-400">Tren total, menunggu &amp; sudah dilayani (maks. 7 hari)</p> -->
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div class="flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-800/60 px-3 py-1.5 shadow-sm">
            <CalendarRange class="h-4 w-4 text-sky-500" />
            <input
              type="date" v-model="from" :max="to" @change="onFromChange"
              class="bg-transparent text-xs font-semibold text-slate-700 dark:text-slate-200 outline-none"
            />
            <span class="text-slate-400 text-xs">→</span>
            <input
              type="date" v-model="to" :max="today" :min="from" @change="onToChange"
              class="bg-transparent text-xs font-semibold text-slate-700 dark:text-slate-200 outline-none"
            />
          </div>
          <button
            @click="resetRange"
            class="rounded-2xl px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-sky-500 to-blue-600 shadow-md shadow-sky-500/30 hover:shadow-lg hover:-translate-y-0.5 transition-all"
          >7 Hari Terakhir</button>
        </div>
      </div>

      <!-- Summary pills / legend -->
      <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div
          v-for="s in series" :key="s.key"
          class="flex items-center gap-3 rounded-2xl border border-slate-100 dark:border-slate-800 bg-white/60 dark:bg-slate-800/40 px-4 py-3"
        >
          <div class="h-9 w-9 rounded-xl flex items-center justify-center" :style="{ background: s.color + '1f' }">
            <component :is="s.icon" class="h-4 w-4" :style="{ color: s.color }" />
          </div>
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ s.label }}</p>
            <p class="text-xl font-black tabular-nums text-slate-800 dark:text-white leading-tight">{{ sum(s.key) }}</p>
          </div>
        </div>
      </div>

      <!-- Chart -->
      <div class="mt-6 h-80">
        <div v-if="loading" class="h-full w-full skeleton rounded-2xl"></div>
        <div v-else-if="error" class="h-full flex items-center justify-center text-sm text-red-500">{{ error }}</div>
        <div v-else-if="points.length === 0" class="h-full flex flex-col items-center justify-center text-slate-400 gap-2">
          <BarChart3 class="h-10 w-10 opacity-40" />
          <p class="text-sm">Tidak ada data antrean pada rentang tanggal ini</p>
        </div>
        <VisXYContainer v-else :data="points" :x-domain="xDomain" :padding="{ top: 16, right: 24, bottom: 4, left: 8 }" class="queue-chart">
          <template v-for="s in series" :key="s.key">
            <VisArea :x="x" :y="accessors[s.key]" :color="s.color" :opacity="0.10" :curve-type="CurveType.MonotoneX" />
            <VisLine :x="x" :y="accessors[s.key]" :color="s.color" :line-width="3" :curve-type="CurveType.MonotoneX" />
            <VisScatter :x="x" :y="accessors[s.key]" :color="s.color" :size="9" :stroke-color="'#ffffff'" :stroke-width="2" />
          </template>
          <VisAxis type="x" :tick-values="tickValues" :tick-format="tickFormat" :grid-line="false" :domain-line="false" :tick-line="false" />
          <VisAxis type="y" :num-ticks="5" :tick-format="(v: number) => Number.isInteger(v) ? v : ''" :domain-line="false" :tick-line="false" />
          <VisCrosshair :x="x" :y="crosshairY" :template="tooltipTemplate" :color="crosshairColor" />
          <VisTooltip />
        </VisXYContainer>
      </div>
    </div>
  </div>
</template>

<style>
.queue-chart {
  --vis-axis-tick-label-color: #64748b;
  --vis-axis-tick-label-font-size: 11px;
  --vis-axis-grid-color: rgba(148, 163, 184, 0.25);
  --vis-crosshair-line-stroke-color: rgba(100, 116, 139, 0.45);
  --vis-crosshair-circle-stroke-color: #ffffff;
  --vis-tooltip-background-color: rgba(255, 255, 255, 0.92);
  --vis-tooltip-border-color: rgba(226, 232, 240, 0.9);
  --vis-tooltip-text-color: #0f172a;
  --vis-tooltip-padding: 12px 14px;
  --vis-tooltip-backdrop-filter: blur(10px);
  --vis-tooltip-shadow-color: rgba(15, 23, 42, 0.15);
}
.queue-chart [class*='tooltip'] { border-radius: 16px; }
</style>
