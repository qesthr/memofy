<script setup>
import FacultySidebar from './FacultySidebar.vue'
import FacultyNavbar from './FacultyNavbar.vue'
</script>

<template>
  <div class="faculty-layout">
    <FacultySidebar />
    <FacultyNavbar />
    <main class="main-content">
      <router-view />
    </main>
  </div>
</template>

<style scoped>
@reference "../../../style.css";

.faculty-layout {
  @apply min-h-screen bg-base-200;
}

.main-content {
  @apply ml-64 mt-16 p-8;
  min-height: calc(100vh - 4rem);
}
</style>
