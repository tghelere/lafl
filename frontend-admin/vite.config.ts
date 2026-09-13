import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    fs: {
      // src/main.ts importa shared/design-tokens/tokens.css, fora da raiz do projeto (é a
      // fonte única de tokens de marca, compartilhada com frontend-site — ver main.ts).
      allow: ['..'],
    },
  },
})
