<template>
  <div class="view-container">
    <div class="view-header">
      <h1 class="text-2xl font-bold">Settings</h1>
      <p class="text-base-content/60">System configuration and preferences</p>
    </div>
    <div class="placeholder-content">
      <p class="text-base-content/40">Settings view - Coming soon</p>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply space-y-6;
}

.view-header {
  @apply mb-6;
}

.placeholder-content {
  @apply bg-base-100 rounded-xl p-12 text-center border border-base-300;
}
</style>
