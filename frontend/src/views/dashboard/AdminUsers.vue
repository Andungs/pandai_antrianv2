<script setup lang="ts">
import { ref, onMounted } from 'vue'
import DataTable from '@/components/DataTable.vue'
import AppModal from '@/components/AppModal.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useDataTable } from '@/composables/useDataTable'
import { api } from '@/stores/auth'
import { Plus, Pencil, Trash2, Users } from 'lucide-vue-next'
import Swal from 'sweetalert2'

const { data: users, loading, search, currentPage, perPage, total, lastPage, fetchData, onSearch, goToPage, refresh } = useDataTable({ url: '/admin/users', perPageDefault: 10 })

const showModal = ref(false)
const editing = ref(false)
const formLoading = ref(false)
const form = ref({ id: 0, name: '', username: '', password: '', role_type: 'loket' })
const photoFile = ref<File | null>(null)
const photoPreview = ref<string | null>(null)

const columns = [
  { key: 'user', label: 'User' },
  { key: 'role', label: 'Role' },
  { key: 'actions', label: 'Aksi', align: 'right' as const },
]

const API_URL = import.meta.env.VITE_API_BASE_URL ?? import.meta.env.VITE_API_URL ?? 'http://localhost:8092/api'
const STORAGE_URL = API_URL.replace('/api', '') + '/storage'

function handlePhotoUpload(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) {
    photoFile.value = file
    photoPreview.value = URL.createObjectURL(file)
  }
}

function openCreate() {
  form.value = { id: 0, name: '', username: '', password: '', role_type: 'loket' }
  photoFile.value = null
  photoPreview.value = null
  editing.value = false
  showModal.value = true
}

function openEdit(user: any) {
  form.value = {
    id: user.id,
    name: user.name,
    username: user.username,
    password: '',
    role_type: user.role_type,
  }
  photoFile.value = null
  photoPreview.value = user.photo ? `${STORAGE_URL}/${user.photo}` : null
  editing.value = true
  showModal.value = true
}

async function save() {
  formLoading.value = true
  const formData = new FormData()
  formData.append('name', form.value.name)
  formData.append('username', form.value.username)
  formData.append('role_type', form.value.role_type)
  
  if (form.value.password) {
    formData.append('password', form.value.password)
  }
  
  if (photoFile.value) {
    formData.append('photo', photoFile.value)
  }
  
  // Method spoofing for PUT request with FormData
  if (editing.value) {
    formData.append('_method', 'PUT')
  }

  try {
    if (editing.value) {
      await api.post(`/admin/users/${form.value.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    } else {
      await api.post('/admin/users', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    }
    showModal.value = false
    refresh()
  } catch (e: any) {
    Swal.fire('Error', e.response?.data?.message ?? 'Gagal menyimpan', 'error')
  } finally {
    formLoading.value = false
  }
}

async function deleteUser(id: number) {
  const result = await Swal.fire({
    title: 'Hapus User?',
    text: 'Data user akan dihapus permanen.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal'
  })
  if (!result.isConfirmed) return
  try {
    await api.delete(`/admin/users/${id}`)
    refresh()
    Swal.fire('Berhasil', 'User berhasil dihapus', 'success')
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
        <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-white">Manajemen User</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola data administrator dan petugas loket</p>
      </div>
      <Button @click="openCreate" class="gap-2">
        <Plus class="h-4 w-4" />
        Tambah User
      </Button>
    </div>

    <DataTable
      :data="users"
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
      search-placeholder="Cari user..."
    >
      <template #row="{ row }">
        <td>
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-slate-100 overflow-hidden border border-slate-200 shrink-0">
              <img v-if="row.photo" :src="`${STORAGE_URL}/${row.photo}`" class="h-full w-full object-cover" />
              <div v-else class="h-full w-full flex items-center justify-center bg-sky-50 text-sky-600 font-bold">
                {{ row.name.charAt(0).toUpperCase() }}
              </div>
            </div>
            <div>
              <p class="font-semibold text-slate-800 dark:text-white">{{ row.name }}</p>
              <p class="text-xs text-slate-500">@{{ row.username }}</p>
            </div>
          </div>
        </td>
        <td>
          <span
            class="px-2.5 py-1 rounded-full text-xs font-bold"
            :class="row.role_type === 'superadmin' ? 'bg-purple-100 text-purple-600 border border-purple-200' : 'bg-slate-100 text-slate-600 border border-slate-200'"
          >
            {{ row.role_type === 'superadmin' ? 'Superadmin' : 'Petugas Loket' }}
          </span>
        </td>
        <td class="text-right">
          <div class="flex items-center justify-end gap-1">
            <Button variant="ghost" size="icon" @click="openEdit(row)" class="h-8 w-8">
              <Pencil class="h-3.5 w-3.5 text-slate-500" />
            </Button>
            <Button variant="ghost" size="icon" @click="deleteUser(row.id)" class="h-8 w-8 text-red-500 hover:text-red-600">
              <Trash2 class="h-3.5 w-3.5" />
            </Button>
          </div>
        </td>
      </template>
    </DataTable>

    <!-- Modal Form -->
    <AppModal v-model="showModal" :title="editing ? 'Edit User' : 'Tambah User'" max-width="md">
      <form @submit.prevent="save" class="space-y-4">
        
        <div class="flex flex-col items-center gap-3 mb-4">
          <div class="h-20 w-20 rounded-full bg-slate-100 border border-slate-200 overflow-hidden relative group cursor-pointer">
            <img v-if="photoPreview" :src="photoPreview" class="h-full w-full object-cover" />
            <div v-else class="h-full w-full flex items-center justify-center">
              <Users class="h-8 w-8 text-slate-300" />
            </div>
            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <span class="text-white text-xs font-bold">Ganti Foto</span>
            </div>
            <input type="file" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*" @change="handlePhotoUpload" />
          </div>
          <p class="text-xs text-slate-500">Klik untuk upload foto</p>
        </div>

        <div class="space-y-2">
          <Label>Nama Lengkap</Label>
          <Input v-model="form.name" placeholder="John Doe" required />
        </div>
        
        <div class="space-y-2">
          <Label>Username</Label>
          <Input v-model="form.username" placeholder="johndoe" required />
        </div>

        <div class="space-y-2">
          <Label>Password {{ editing ? '(Kosongkan jika tidak ingin mengubah)' : '' }}</Label>
          <Input v-model="form.password" type="password" placeholder="••••••••" :required="!editing" />
        </div>

        <div class="space-y-2">
          <Label>Role</Label>
          <select v-model="form.role_type" class="form-select-std" required>
            <option value="loket">Petugas Loket</option>
            <option value="superadmin">Superadmin</option>
          </select>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" @click="showModal = false">Batal</Button>
          <Button type="submit" :disabled="formLoading">{{ formLoading ? 'Menyimpan...' : 'Simpan' }}</Button>
        </div>
      </form>
    </AppModal>
  </div>
</template>
