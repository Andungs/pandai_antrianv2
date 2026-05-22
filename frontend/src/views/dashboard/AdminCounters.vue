<script setup lang="ts">
import { ref, onMounted } from 'vue'
import DataTable from '@/components/DataTable.vue'
import AppModal from '@/components/AppModal.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useDataTable } from '@/composables/useDataTable'
import { api } from '@/stores/auth'
import { Plus, Pencil, Trash2, Monitor } from 'lucide-vue-next'
import Swal from 'sweetalert2'
import Multiselect from '@vueform/multiselect'

const { data: counters, loading, search, currentPage, perPage, total, lastPage, fetchData, onSearch, goToPage, refresh } = useDataTable({ url: '/admin/counters', perPageDefault: 10 })

const showModal = ref(false)
const editing = ref(false)
const formLoading = ref(false)
const form = ref({ id: 0, name: '', service_ids: [] as string[], user_ids: [] as string[], status: true })

const columns = [
  { key: 'name', label: 'Nama Loket' },
  { key: 'services', label: 'Layanan' },
  { key: 'users', label: 'Petugas' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', align: 'right' as const },
]

// Dropdown data
const servicesList = ref<any[]>([])
const usersList = ref<any[]>([])

async function fetchFormData() {
  try {
    const res = await api.get('/admin/counters-form-data')
    servicesList.value = res.data.services
    usersList.value = res.data.users
  } catch (e) { console.error(e) }
}

function openCreate() {
  form.value = { id: 0, name: '', service_ids: [], user_ids: [], status: true }
  editing.value = false
  showModal.value = true
  fetchFormData()
}

function openEdit(counter: any) {
  form.value = {
    id: counter.id,
    name: counter.name,
    service_ids: counter.services.map((s: any) => String(s.id)),
    user_ids: counter.users.map((u: any) => String(u.id)),
    status: counter.status,
  }
  editing.value = true
  showModal.value = true
  fetchFormData()
}

async function save() {
  formLoading.value = true
  const payload = {
    ...form.value,
    service_ids: form.value.service_ids.map(Number),
    user_ids: form.value.user_ids.map(Number),
  }
  try {
    if (editing.value) {
      await api.put(`/admin/counters/${form.value.id}`, payload)
    } else {
      await api.post('/admin/counters', payload)
    }
    showModal.value = false
    refresh()
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal menyimpan', 'error')
  } finally {
    formLoading.value = false
  }
}

async function deleteCounter(id: number) {
  const result = await Swal.fire({
    title: 'Hapus Loket?',
    text: 'Data loket akan dihapus permanen.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal'
  })
  if (!result.isConfirmed) return
  try {
    await api.delete(`/admin/counters/${id}`)
    refresh()
    Swal.fire('Berhasil', 'Loket berhasil dihapus', 'success')
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal menghapus', 'error')
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white">Kelola Loket</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola loket pelayanan, layanan, dan petugas</p>
      </div>
      <Button @click="openCreate" class="gap-2">
        <Plus class="h-4 w-4" />
        Tambah Loket
      </Button>
    </div>

    <DataTable
      :data="counters"
      :loading="loading"
      :columns="columns"
      v-model="search"
      :current-page="currentPage"
      :per-page="perPage"
      :total="total"
      :last-page="lastPage"
      @search="onSearch"
      @page-change="goToPage"
      @refresh="refresh"
      search-placeholder="Cari loket..."
    >
      <template #row="{ row }">
        <td>
          <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
              <Monitor class="h-4 w-4 text-violet-600 dark:text-violet-400" />
            </div>
            <span class="font-semibold text-slate-800 dark:text-white">{{ row.name }}</span>
          </div>
        </td>
        <td>
          <div class="flex flex-wrap gap-1">
            <span v-for="s in row.services" :key="s.id" class="px-2 py-0.5 rounded bg-sky-50 text-sky-600 text-xs font-medium border border-sky-100">
              {{ s.name }}
            </span>
            <span v-if="!row.services?.length" class="text-slate-400">-</span>
          </div>
        </td>
        <td>
          <div class="flex flex-wrap gap-1">
            <span v-for="u in row.users" :key="u.id" class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-xs font-medium border border-slate-200">
              {{ u.name }}
            </span>
            <span v-if="!row.users?.length" class="text-slate-400">-</span>
          </div>
        </td>
        <td>
          <span
            class="px-2.5 py-1 rounded-full text-xs font-bold"
            :class="row.status ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' : 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'"
          >
            {{ row.status ? 'Aktif' : 'Nonaktif' }}
          </span>
        </td>
        <td class="text-right">
          <div class="flex items-center justify-end gap-1">
            <Button variant="ghost" size="icon" @click="openEdit(row)" class="h-8 w-8">
              <Pencil class="h-3.5 w-3.5 text-slate-500" />
            </Button>
            <Button variant="ghost" size="icon" @click="deleteCounter(row.id)" class="h-8 w-8 text-red-500 hover:text-red-600">
              <Trash2 class="h-3.5 w-3.5" />
            </Button>
          </div>
        </td>
      </template>
    </DataTable>

    <!-- Modal Form -->
    <AppModal v-model="showModal" :title="editing ? 'Edit Loket' : 'Tambah Loket'" max-width="md">
      <form @submit.prevent="save" class="space-y-4">
        <div class="space-y-2">
          <Label>Nama Loket</Label>
          <Input v-model="form.name" placeholder="Loket 1" required />
        </div>
        <div class="space-y-2">
          <Label>Layanan</Label>
          <Multiselect
            v-model="form.service_ids"
            mode="tags"
            :close-on-select="false"
            :searchable="true"
            :options="servicesList.map(s => ({ value: String(s.id), label: s.name }))"
            placeholder="Pilih Layanan..."
            class="min-h-[42px]"
          />
        </div>
        <div class="space-y-2">
          <Label>Petugas (Opsional)</Label>
          <Multiselect
            v-model="form.user_ids"
            mode="tags"
            :close-on-select="false"
            :searchable="true"
            :options="usersList.map(u => ({ value: String(u.id), label: `${u.name} (@${u.username})` }))"
            placeholder="Pilih Petugas..."
            class="min-h-[42px]"
          />
        </div>
        <div class="flex items-center gap-3">
          <input type="checkbox" v-model="form.status" id="counter-status" class="rounded" />
          <Label for="counter-status">Status Aktif</Label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" @click="showModal = false">Batal</Button>
          <Button type="submit" :disabled="formLoading">{{ formLoading ? 'Menyimpan...' : 'Simpan' }}</Button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<style src="@vueform/multiselect/themes/default.css"></style>
<style>
/* Override default vueform multiselect theme to match the app */
.multiselect {
  --ms-border-color: #e2e8f0;
  --ms-border-color-active: #bae6fd;
  --ms-ring-color: rgba(56, 189, 248, 0.2);
  --ms-dropdown-border-color: #e2e8f0;
  --ms-tag-bg: #e0f2fe;
  --ms-tag-color: #0284c7;
  --ms-tag-font-weight: 600;
  --ms-option-bg-selected: #bae6fd;
  --ms-option-color-selected: #0369a1;
  --ms-option-bg-selected-pointed: #e0f2fe;
  border-radius: 0.75rem;
}
.dark .multiselect {
  --ms-bg: #1e293b;
  --ms-border-color: #334155;
  --ms-dropdown-bg: #1e293b;
  --ms-dropdown-border-color: #334155;
  --ms-option-bg-pointed: #334155;
  --ms-option-color-pointed: #f8fafc;
  --ms-tag-bg: rgba(14, 165, 233, 0.2);
  --ms-tag-color: #38bdf8;
  --ms-option-bg-selected: rgba(14, 165, 233, 0.2);
  --ms-option-color-selected: #38bdf8;
}
</style>
