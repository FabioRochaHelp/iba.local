import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) }
  },
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    // Em desenvolvimento local a API roda em: php -S localhost:8765 public/index.php
    // Em Docker, o compose injeta VITE_API_PROXY_TARGET=http://backend:80
    proxy: {
      '/api': { target: process.env.VITE_API_PROXY_TARGET || 'http://localhost:8765', changeOrigin: false }
    }
  },
  build: {
    outDir: 'dist',
    sourcemap: false,
    // Sem scripts inline: permite CSP "script-src 'self'".
    modulePreload: { polyfill: false },
    chunkSizeWarningLimit: 900
  }
})
