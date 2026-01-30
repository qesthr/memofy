<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="max-w-md w-full bg-white shadow-lg rounded-lg p-8 text-center">
      <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
        <svg class="h-10 w-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <h2 class="text-3xl font-extrabold text-gray-900 mb-4">Access Denied</h2>
      <p class="text-gray-600 mb-8">
        You do not have permission to view this page. Please contact your administrator if you believe this is an error.
      </p>
      <div class="flex justify-center space-x-4">
        <router-link to="/login" class="btn btn-outline">
          Go to Login
        </router-link>
        <button @click="$router.go(-1)" class="btn btn-primary text-white">
          Go Back
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
// No script needed
</script>
