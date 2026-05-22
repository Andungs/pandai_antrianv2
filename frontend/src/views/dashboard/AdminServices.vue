<script setup lang="ts">
import { ref, onMounted } from 'vue'
import DataTable from '@/components/DataTable.vue'
import AppModal from '@/components/AppModal.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useDataTable } from '@/composables/useDataTable'
import { api } from '@/stores/auth'
import { Plus, Pencil, Trash2, Layers } from 'lucide-vue-next'
import Swal from 'sweetalert2'

const { data: services, loading, search, currentPage, perPage, total, lastPage, fetchData, onSearch, goToPage, refresh } = useDataTable({ url: '/admin/services', perPageDefault: 10 })

const showModal = ref(false)
const editing = ref(false)
const formLoading = ref(false)
const form = ref({ id: 0, name: '', prefix_code: '', digit_length: 3 })

const columns = [
  { key: 'name', label: 'Nama Layanan' },
  { key: 'prefix_code', label: 'Prefix' },
  { key: 'digit_length', label: 'Digit' },
  { key: 'example', label: 'Contoh' },
  { key: 'actions', label: 'Aksi', align: 'right' as const },
]

function openCreate() {
  form.value = { id: 0, name: '', prefix_code: '', digit_length: 3 }
  editing.value = false
  showModal.value = true
}

function openEdit(svc: any) {
  form.value = { id: svc.id, name: svc.name, prefix_code: svc.prefix_code, digit_length: svc.digit_length }
  editing.value = true
  showModal.value = true
}

async function save() {
  formLoading.value = true
  try {
    if (editing.value) {
      await api.put(`/admin/services/${form.value.id}`, form.value)
    } else {
      await api.post('/admin/services', form.value)
    }
    showModal.value = false
    refresh()
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal menyimpan', 'error')
  } finally {
    formLoading.value = false
  }
}

async function deleteService(id: number) {
  const result = await Swal.fire({
    title: 'Hapus Layanan?',
    text: 'Data layanan akan dihapus permanen.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal'
  })
  if (!result.isConfirmed) return
  try {
    await api.delete(`/admin/services/${id}`)
    refresh()
    Swal.fire('Berhasil', 'Layanan berhasil dihapus', 'success')
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
        <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white">Kelola Layanan</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola jenis layanan antrean</p>
      </div>
      <Button @click="openCreate" class="gap-2">
        <Plus class="h-4 w-4" />
        Tambah Layanan
      </Button>
    </div>

    <DataTable
      :data="services"
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
      search-placeholder="Cari layanan..."
    >
      <template #row="{ row }">
        <td>
          <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
              <Layers class="h-4 w-4 text-sky-600 dark:text-sky-400" />
            </div>
            <span class="font-semibold text-slate-800 dark:text-white">{{ row.name }}</span>
          </div>
        </td>
        <td>
          <span class="px-2.5 py-1 rounded-lg bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 text-xs font-bold">{{ row.prefix_code }}</span>
        </td>
        <td class="text-slate-600 dark:text-slate-400">{{ row.digit_length }} digit</td>
        <td class="font-mono text-sm font-bold text-slate-700 dark:text-slate-300">{{ row.prefix_code }}{{ '0'.repeat(row.digit_length - 1) }}1</td>
        <td class="text-right">
          <div class="flex items-center justify-end gap-1">
            <Button variant="ghost" size="icon" @click="openEdit(row)" class="h-8 w-8">
              <Pencil class="h-3.5 w-3.5 text-slate-500" />
            </Button>
            <Button variant="ghost" size="icon" @click="deleteService(row.id)" class="h-8 w-8 text-red-500 hover:text-red-600">
              <Trash2 class="h-3.5 w-3.5" />
            </Button>
          </div>
        </td>
      </template>
    </DataTable>

    <!-- Modal Form -->
    <AppModal v-model="showModal" :title="editing ? 'Edit Layanan' : 'Tambah Layanan'" max-width="md">
      <form @submit.prevent="save" class="space-y-4">
        <div class="space-y-2">
          <Label>Nama Layanan</Label>
          <Input v-model="form.name" placeholder="Customer Service" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label>Kode Prefix</Label>
            <Input v-model="form.prefix_code" placeholder="A" required maxlength="5" />
          </div>
          <div class="space-y-2">
            <Label>Jumlah Digit</Label>
            <Input v-model.number="form.digit_length" type="number" min="1" max="5" required />
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" @click="showModal = false">Batal</Button>
          <Button type="submit" :disabled="formLoading">{{ formLoading ? 'Menyimpan...' : 'Simpan' }}</Button>
        </div>
      </form>
    </AppModal>
  </div>
</template>
