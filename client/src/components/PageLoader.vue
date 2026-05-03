<template>
  <div v-if="visible" style="position:fixed;inset:0;background:red;display:flex;align-items:center;justify-content:center;z-index:9999;">
    <p style="color:white;font-size:24px;">LOADING...</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const visible = ref(true)

console.log('PageLoader script runs')

onMounted(() => {
  console.log('onMounted fired')
  visible.value = false
})
</script>