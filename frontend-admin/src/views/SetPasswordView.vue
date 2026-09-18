<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

// Fonte única em shared/brand/ — ver LEIA-ME.md. Import direto (não cópia), como em
// LoginView.vue: sai empacotada no bundle do painel, não é um recurso externo — não conflita
// com o referrer=no-referrer abaixo, que é sobre requisição a domínio terceiro.
import logoVertical from '../../../shared/brand/lar-analia-franco/lar-analia-franco-vertical.svg'

/**
 * Link de definição de senha (ver App\Actions\Users\GeneratePasswordLink) — página pública,
 * alcançável por quem recebe a URL fora do sistema (WhatsApp, por exemplo), com o token na
 * própria query string. Declara referrer=no-referrer e não carrega recurso externo nenhum
 * (sem fonte, imagem ou script de fora): um Referer vazado para um domínio terceiro
 * exporia o token de uso único de quem está definindo a senha.
 */
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

let referrerMeta: HTMLMetaElement | null = null

onMounted(() => {
  referrerMeta = document.createElement('meta')
  referrerMeta.name = 'referrer'
  referrerMeta.content = 'no-referrer'
  document.head.appendChild(referrerMeta)
})

onUnmounted(() => {
  referrerMeta?.remove()
})

const token = typeof route.query.token === 'string' ? route.query.token : ''

const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const errorMessage = ref<string | null>(null)
const isSubmitting = ref(false)

const passwordsMismatch = computed(
  () => passwordConfirmation.value !== '' && password.value !== passwordConfirmation.value,
)

/**
 * Token, e-mail que não confere, conta desativada e link já usado dão todos a mesma
 * mensagem genérica de propósito (ver App\Actions\Auth\SetUserPassword) — o front só repassa
 * o que a API devolveu, nunca decide o texto.
 */
function extractApiMessage(error: unknown): string {
  if (axios.isAxiosError(error) && error.response?.data) {
    const body = error.response.data as { message?: string; errors?: Record<string, string[]> }
    const firstFieldError = body.errors ? Object.values(body.errors)[0]?.[0] : undefined

    return firstFieldError ?? body.message ?? 'Não foi possível definir a senha. Tente novamente.'
  }

  return 'Não foi possível definir a senha. Tente novamente.'
}

async function handleSubmit(): Promise<void> {
  errorMessage.value = null

  if (!token) {
    errorMessage.value = 'Link inválido ou expirado.'

    return
  }

  isSubmitting.value = true

  try {
    await authStore.setPassword({
      token,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })

    await router.push({ name: 'login', query: { 'senha-definida': '1' } })
  } catch (error) {
    errorMessage.value = extractApiMessage(error)
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
      <img
        :src="logoVertical"
        width="96"
        height="142"
        alt="Lar Anália Franco"
        class="login__logo"
      >
      <p class="login__eyebrow">
        Painel administrativo
      </p>
      <h1>Definir senha</h1>

      <p
        v-if="!token"
        role="alert"
        class="login__error"
      >
        Link inválido ou expirado.
      </p>

      <template v-else>
        <div class="field">
          <label for="set-password-email">E-mail</label>
          <input
            id="set-password-email"
            v-model="email"
            type="email"
            autocomplete="username"
            required
          >
        </div>

        <div class="field">
          <label for="set-password-password">Nova senha</label>
          <input
            id="set-password-password"
            v-model="password"
            type="password"
            autocomplete="new-password"
            minlength="8"
            required
          >
          <span class="field__hint">Mínimo de 8 caracteres.</span>
        </div>

        <div class="field">
          <label for="set-password-confirmation">Confirmar nova senha</label>
          <input
            id="set-password-confirmation"
            v-model="passwordConfirmation"
            type="password"
            autocomplete="new-password"
            minlength="8"
            required
          >
          <span
            v-if="passwordsMismatch"
            class="field__error"
          >
            As senhas não conferem.
          </span>
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
          :disabled="isSubmitting || passwordsMismatch"
        >
          {{ isSubmitting ? 'Salvando…' : 'Definir senha' }}
        </button>
      </template>
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
  background: var(--color-surface);
}

.login__form {
  width: 100%;
  max-width: 22rem;
}

.login__logo {
  display: block;
  margin: 0 auto var(--space-5);
}

.login__eyebrow {
  font-size: var(--text-xs);
  font-weight: var(--weight-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--color-text-muted);
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
