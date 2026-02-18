import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        // Note: Do NOT set Content-Type header here; let the browser set it automatically
        // for different request types (application/json for regular requests, 
        // multipart/form-data for file uploads)
        headers: {
          Accept: 'application/json',
        },
      },
    },
  },
})
