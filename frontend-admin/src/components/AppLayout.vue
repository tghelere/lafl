<script setup lang="ts">
import { useRouter } from 'vue-router'

import AppSidebar from '@/components/AppSidebar.vue'
import { useIdleTimeout } from '@/composables/useIdleTimeout'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()

const idleTimeoutMinutes = Number(import.meta.env.VITE_SESSION_IDLE_TIMEOUT_MINUTES ?? '15')

useIdleTimeout(idleTimeoutMinutes, () => {
  void handleLogout()
})

async function handleLogout(): Promise<void> {
  await authStore.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="app-shell">
    <a
      class="skip-link"
      href="#main"
    >Pular para o conteúdo</a>

    <AppSidebar />

    <div class="app-main">
      <header class="app-topbar">
        <span
          v-if="authStore.user"
          class="app-topbar__user"
        >
          {{ authStore.user.name }} · {{ authStore.user.roles.join(', ') }}
        </span>
        <button
          type="button"
          class="btn btn--secondary"
          @click="handleLogout"
        >
          Sair
        </button>
      </header>

      <main
        id="main"
        class="app-content"
      >
        <slot />
      </main>
    </div>
  </div>
</template>
