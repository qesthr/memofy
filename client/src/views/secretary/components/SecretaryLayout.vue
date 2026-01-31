<script setup>
import SecretarySidebar from './SecretarySidebar.vue'
import SecretaryNavbar from './SecretaryNavbar.vue'
</script>

<template>
  <div class="secretary-layout">
    <SecretarySidebar />
    <SecretaryNavbar />
    <main class="main-content">
      <router-view />
    </main>
  </div>
</template>

<style scoped>
@reference "../../../style.css";

.secretary-layout {
  @apply min-h-screen bg-base-200;
}

.main-content {
  @apply ml-64 mt-16 p-8;
  height: calc(100vh - 4rem);
  overflow: hidden;
}
</style>
