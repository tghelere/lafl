<script setup lang="ts">
import { useRouter } from 'vue-router'

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
  <main class="dashboard">
    <header class="dashboard__header">
      <div>
        <h1>Painel administrativo</h1>
        <p v-if="authStore.user">
          Olá, {{ authStore.user.name }} — papéis: {{ authStore.user.roles.join(', ') }}
        </p>
      </div>
      <button
        type="button"
        @click="handleLogout"
      >
        Sair
      </button>
    </header>

    <p>
      Esta é uma rota protegida de exemplo — o domínio de assistidos ainda não foi
      implementado nesta fatia.
    </p>
  </main>
</template>

<style scoped>
.dashboard {
  padding: 2rem;
}

.dashboard__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}

button {
  padding: 0.5rem 1rem;
  cursor: pointer;
}
</style>
