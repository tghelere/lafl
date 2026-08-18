<script setup lang="ts">
import axios from 'axios'
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')
const errorMessage = ref<string | null>(null)
const isSubmitting = ref(false)

const authStore = useAuthStore()
const router = useRouter()

async function handleSubmit(): Promise<void> {
  errorMessage.value = null
  isSubmitting.value = true

  try {
    await authStore.login(email.value, password.value)
    await router.push({ name: 'dashboard' })
  } catch (error) {
    // A API é a única fonte de verdade para mensagens de erro (ver CLAUDE.md) — o front só
    // exibe o que ela devolveu, nunca decide o texto.
    errorMessage.value = axios.isAxiosError(error)
      ? (error.response?.data?.message ?? 'Não foi possível entrar. Tente novamente.')
      : 'Não foi possível entrar. Tente novamente.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="login">
    <form
      class="login__form"
      @submit.prevent="handleSubmit"
    >
      <h1>Lar Anália Franco</h1>
      <p class="login__subtitle">
        Painel administrativo
      </p>

      <label for="email">E-mail</label>
      <input
        id="email"
        v-model="email"
        type="email"
        autocomplete="username"
        required
      >

      <label for="password">Senha</label>
      <input
        id="password"
        v-model="password"
        type="password"
        autocomplete="current-password"
        required
      >

      <p
        v-if="errorMessage"
        role="alert"
        class="login__error"
      >
        {{ errorMessage }}
      </p>

      <button
        type="submit"
        :disabled="isSubmitting"
      >
        {{ isSubmitting ? 'Entrando...' : 'Entrar' }}
      </button>
    </form>
  </main>
</template>

<style scoped>
.login {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 1rem;
}

.login__form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
  max-width: 320px;
}

.login__subtitle {
  margin: 0 0 1rem;
  color: #666;
}

.login__error {
  color: #b91c1c;
  margin: 0;
}

input {
  padding: 0.5rem;
  font-size: 1rem;
}

button {
  margin-top: 1rem;
  padding: 0.6rem;
  font-size: 1rem;
  cursor: pointer;
}
</style>
