<template>
  <div class="view-container">
    <div class="view-header">
      <h1 class="text-2xl font-bold">Unauthorized Access</h1>
      <p class="text-base-content/60">You do not have permission to view this page.</p>
    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.view-container {
  @apply flex flex-col items-center justify-center min-h-screen bg-base-200;
}

.view-header {
  @apply text-center mb-6;
}
</style>
