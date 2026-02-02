<script setup>
import { useTheme } from './composables/useTheme'
// Initialize theme globally
useTheme()
</script>

<template>
  <div class="app-container min-h-screen bg-base-200">
    <router-view></router-view>
  </div>
</template>

<style>
/* Global styles can go here or in style.css */
</style>
