<script setup lang="ts">
import axios from 'axios'
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import NoticeBanner from '@/components/NoticeBanner.vue'
import { useAuthStore } from '@/stores/auth'

// Fonte única em shared/brand/ — ver LEIA-ME.md de cada pasta. Import direto (não cópia),
// habilitado por vite.config.ts (server.fs.allow).
import logoVertical from '../../../shared/brand/lar-analia-franco/lar-analia-franco-vertical.svg'
import softhingLogo from '../../../shared/brand/softhing/softhing-fundo-claro.svg'

const email = ref('')
const password = ref('')
const errorMessage = ref<string | null>(null)
const isSubmitting = ref(false)

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

async function handleSubmit(): Promise<void> {
  errorMessage.value = null
  isSubmitting.value = true

  try {
    await authStore.login(email.value, password.value)
    await router.push({ name: 'dashboard' })
  } catch (error) {
    // A API é a única fonte de verdade para mensagens de erro (ver CLAUDE.md) — o front só
    // exibe o que ela devolveu, nunca decide o texto. Login com credencial inválida ou conta
    // desativada responde 422 com a mensagem específica dentro de errors.email, não em
    // data.message (que é só o texto genérico "the given data was invalid") — por isso o
    // campo é conferido primeiro.
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const body = error.response.data as { message?: string; errors?: Record<string, string[]> }
      errorMessage.value =
        body.errors?.email?.[0] ?? body.message ?? 'Não foi possível entrar. Tente novamente.'
    } else {
      errorMessage.value = 'Não foi possível entrar. Tente novamente.'
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="login">
    <div class="login__center">
      <form
        class="login__form card"
        @submit.prevent="handleSubmit"
      >
        <img
          :src="logoVertical"
          width="96"
          height="142"
          alt="Lar Anália Franco"
          class="login__logo"
        >
        <h1>Painel administrativo</h1>

        <NoticeBanner
          v-if="route.query['senha-definida']"
          variant="info"
        >
          Senha definida. Entre com a nova senha.
        </NoticeBanner>

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
    </div>

    <p class="login__credit">
      Desenvolvido por
      <a
        href="https://softhing.com.br/?utm_source=lar-analia-franco&utm_medium=referral&utm_campaign=credito-painel"
        target="_blank"
        rel="noopener"
        aria-label="Softhing — abre o site da desenvolvedora em nova aba"
      >
        <img
          :src="softhingLogo"
          width="73"
          height="20"
          alt=""
          class="login__credit-logo"
        >
      </a>
    </p>
  </main>
</template>

<style scoped>
.login {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  padding: var(--space-4);
  background: var(--color-surface);
}

.login__center {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.login__form {
  width: 100%;
  max-width: 22rem;
}

.login__logo {
  display: block;
  margin: 0 auto var(--space-5);
}

.login__form h1 {
  font-size: var(--text-2xl);
  text-align: center;
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

.login__credit {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding-top: var(--space-4);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.login__credit a {
  display: inline-flex;
}

.login__credit-logo {
  display: block;
}
</style>
