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
      class="login__form card"
      @submit.prevent="handleSubmit"
    >
      <p class="login__eyebrow">
        Painel administrativo
      </p>
      <h1>Lar Anália Franco</h1>

      <div class="field">
        <label for="email">E-mail</label>
        <input
          id="email"
          v-model="email"
          type="email"
          autocomplete="username"
          required
        >
      </div>

      <div class="field">
        <label for="password">Senha</label>
        <input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
        >
      </div>

      <p
        v-if="errorMessage"
        role="alert"
        class="login__error"
      >
        {{ errorMessage }}
      </p>

      <button
        type="submit"
        class="btn btn--primary login__submit"
        :disabled="isSubmitting"
      >
        {{ isSubmitting ? 'Entrando…' : 'Entrar' }}
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
  padding: var(--space-4);
  background: var(--color-paper);
}

.login__form {
  width: 100%;
  max-width: 22rem;
}

.login__eyebrow {
  font-size: var(--text-xs);
  font-weight: var(--weight-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--color-linha);
  margin: 0 0 var(--space-2);
}

.login__form h1 {
  font-size: var(--text-2xl);
  margin-bottom: var(--space-5);
}

.login__error {
  color: var(--color-error);
  font-size: var(--text-sm);
  margin: 0 0 var(--space-4);
}

.login__submit {
  width: 100%;
  margin-top: var(--space-2);
}
</style>
