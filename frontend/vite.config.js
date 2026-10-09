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
    // Atrás de reverse proxy (nginx) com Host != localhost, liste o(s)
    // domínio(s) em VITE_ALLOWED_HOSTS (separados por vírgula) — o Vite
    // bloqueia por padrão qualquer Host não reconhecido (anti DNS-rebinding).
    allowedHosts: process.env.VITE_ALLOWED_HOSTS
      ? process.env.VITE_ALLOWED_HOSTS.split(',').map((h) => h.trim()).filter(Boolean)
      : undefined,
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
