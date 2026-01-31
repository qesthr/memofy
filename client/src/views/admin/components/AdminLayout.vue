<script setup>
import Sidebar from './Sidebar.vue'
import AdminNavbar from './AdminNavbar.vue'
</script>

<template>
  <div class="admin-layout">
    <Sidebar />
    <AdminNavbar />
    <main class="main-content">
      <router-view />
    </main>
  </div>
</template>

<style scoped>
@reference "../../../style.css";

.admin-layout {
  @apply min-h-screen bg-base-200;
}

.main-content {
  @apply ml-64 mt-16 p-6;
  height: calc(100vh - 4rem);
  overflow: hidden;
}
</style>
