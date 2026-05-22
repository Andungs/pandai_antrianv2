<script setup lang="ts">
defineProps<{
  rows?: number
  cols?: number
}>()
</script>

<template>
  <tbody>
    <tr v-for="r in (rows ?? 6)" :key="r" class="border-b border-slate-100 dark:border-slate-800/60">
      <td v-for="c in (cols ?? 5)" :key="c" class="px-4 py-3.5">
        <div 
          class="skeleton"
          :style="{ 
            height: '16px',
            width: c === 1 ? '60%' : c === (cols ?? 5) ? '80px' : `${55 + (r + c) % 3 * 15}%`,
            marginLeft: c === (cols ?? 5) ? 'auto' : '0'
          }"
        ></div>
      </td>
    </tr>
  </tbody>
</template>
