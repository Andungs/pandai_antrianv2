<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { LogIn, Loader2, AlertCircle } from 'lucide-vue-next'

const router = useRouter()
const auth   = useAuthStore()

const username = ref('')
const password = ref('')

const handleLogin = async () => {
  try {
    const res = await auth.login(username.value, password.value)
    const defaultRoute = res?.user?.default_route ?? '/dashboard'
    router.push(defaultRoute)
  } catch {
    // error sudah di-handle di store (auth.error)
  }
}
</script>

<template>
  <div class="w-full max-w-md px-4">
    <!-- Logo -->
    <div class="flex flex-col items-center mb-8">
      <div class="p-3 bg-gradient-to-br from-sky-500 to-blue-600 rounded-2xl shadow-lg shadow-sky-500/30 mb-4">
        <LogIn class="h-10 w-10 text-white" />
      </div>
      <h1 class="text-3xl font-bold tracking-tight text-slate-800 dark:text-white">Selamat Datang</h1>
      <p class="text-muted-foreground mt-1 text-sm">Masuk ke sistem Pandai Antrian</p>
    </div>

    <!-- Error Alert -->
    <div v-if="auth.error" class="flex items-center gap-2 p-3 rounded-xl bg-destructive/10 text-destructive text-sm mb-4 border border-destructive/20">
      <AlertCircle class="h-4 w-4 shrink-0" />
      {{ auth.error }}
    </div>

    <!-- Form -->
    <form @submit.prevent="handleLogin" class="space-y-4">
      <div class="space-y-2">
        <Label for="username">Username</Label>
        <Input
          id="username"
          v-model="username"
          type="text"
          placeholder="admin"
          required
          class="h-11"
          :disabled="auth.loading"
        />
      </div>
      <div class="space-y-2">
        <Label for="password">Password</Label>
        <Input
          id="password"
          v-model="password"
          type="password"
          placeholder="••••••••"
          required
          class="h-11"
          :disabled="auth.loading"
        />
      </div>

      <Button type="submit" class="w-full h-11 mt-2 text-base gap-2" :disabled="auth.loading">
        <Loader2 v-if="auth.loading" class="h-4 w-4 animate-spin" />
        {{ auth.loading ? 'Memproses...' : 'Masuk' }}
      </Button>
    </form>

    <p class="text-center text-xs text-muted-foreground mt-8">
      &copy; {{ new Date().getFullYear() }} Pandai Antrian
    </p>
  </div>
</template>
