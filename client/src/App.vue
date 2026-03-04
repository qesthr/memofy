<script setup>
import { useTheme } from './composables/useTheme'
import { useAuth } from './composables/useAuth'
import { onMounted, onUnmounted, watch } from 'vue'

// Initialize theme globally
useTheme()

// Initialize session timeout for authenticated users
const { initSessionTimeout, token } = useAuth()

// Watch for token changes (login/logout)
watch(token, (newToken) => {
  if (newToken) {
    initSessionTimeout()
  }
}, { immediate: true })

// Security: Prevent back-button session restoration (bfcache)
const handlePageShow = (event) => {
  // If event.persisted is true, the page was loaded from the bfcache (back-forward cache)
  if (event.persisted) {
    const currentPath = window.location.pathname
    const isPublicPage = ['/login', '/forgot-password', '/setup-password', '/unauthorized'].includes(currentPath)
    
    // If we're on a non-public page but have no token, force a reload to trigger router guards
    if (!token.value && !isPublicPage) {
      window.location.reload()
    }
  }
}

onMounted(() => {
  window.addEventListener('pageshow', handlePageShow)
})

onUnmounted(() => {
  window.removeEventListener('pageshow', handlePageShow)
})
</script>

<template>
  <div class="app-container min-h-screen bg-base-200">
    <router-view></router-view>
  </div>
</template>

<style>
/* Global styles can go here or in style.css */
</style>
