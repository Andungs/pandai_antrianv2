<script setup lang="ts">
/**
 * AppModal — Reusable Modal with Spring Transition
 * Usage:
 *   <AppModal v-model="showDialog" title="Judul" icon="..." icon-color="text-primary">
 *     <template #default>...form content...</template>
 *     <template #footer>...buttons...</template>
 *   </AppModal>
 */
import { computed } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps<{
  modelValue: boolean
  title?: string
  description?: string
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | 'full'
  persistent?: boolean   // prevent close on backdrop click
  scrollable?: boolean   // enable inner scroll
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'close'): void
}>()

const isOpen = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const close = () => {
  if (!props.persistent) {
    isOpen.value = false
    emit('close')
  }
}

const maxWClass: Record<string, string> = {
  'sm':   'max-w-sm',
  'md':   'max-w-md',
  'lg':   'max-w-lg',
  'xl':   'max-w-xl',
  '2xl':  'max-w-2xl',
  '3xl':  'max-w-3xl',
  'full': 'max-w-full',
}
const sizeClass = computed(() => maxWClass[props.maxWidth ?? 'md'])
</script>

<template>
  <teleport to="body">
    <transition name="modal-backdrop">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="close"
      >
        <transition name="modal" appear>
          <div
            v-if="isOpen"
            :class="[
              sizeClass,
              'w-full bg-white dark:bg-slate-950 rounded-2xl shadow-2xl ring-1 ring-slate-200 dark:ring-slate-800 flex flex-col',
              scrollable ? 'max-h-[90vh]' : ''
            ]"
            @click.stop
          >
            <!-- Header -->
            <div class="flex items-start justify-between px-6 py-5 border-b border-slate-100 dark:border-slate-800 shrink-0">
              <div class="flex-1 pr-4">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2.5">
                  <slot name="icon" />
                  {{ title }}
                </h2>
                <p v-if="description" class="text-sm text-slate-500 mt-1">{{ description }}</p>
              </div>
              <button
                @click="close"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-600 dark:hover:text-slate-300 transition-colors shrink-0"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <!-- Body -->
            <div :class="['px-6 py-5 space-y-4', scrollable ? 'overflow-y-auto scrollbar-thin flex-1' : '']">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="$slots.footer" class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 rounded-b-2xl shrink-0">
              <slot name="footer" />
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
/* Backdrop */
.modal-backdrop-enter-active { transition: opacity 0.2s ease; }
.modal-backdrop-leave-active { transition: opacity 0.18s ease; }
.modal-backdrop-enter-from,
.modal-backdrop-leave-to { opacity: 0; }

/* Dialog card */
.modal-enter-active {
  transition: opacity 0.25s ease, transform 0.28s cubic-bezier(0.34, 1.35, 0.64, 1);
}
.modal-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease-in;
}
.modal-enter-from {
  opacity: 0;
  transform: scale(0.93) translateY(12px);
}
.modal-leave-to {
  opacity: 0;
  transform: scale(0.97) translateY(6px);
}
</style>
