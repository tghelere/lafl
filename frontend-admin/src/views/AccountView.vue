<script setup lang="ts">
import axios from 'axios'
import { computed, reactive, ref } from 'vue'

import AppLayout from '@/components/AppLayout.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)
const successAt = ref<number | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

const passwordsMismatch = computed(
  () => passwordConfirmation.value !== '' && password.value !== passwordConfirmation.value,
)

function clearFieldErrors(): void {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

async function handleSubmit(): Promise<void> {
  errorMessage.value = null
  successAt.value = null
  clearFieldErrors()
  isSubmitting.value = true

  try {
    // PUT /api/v1/auth/password — mesmo endpoint usado por qualquer papel para trocar a
    // própria senha. Com o fix em App\Actions\Auth\ChangeUserPassword a sessão atual continua
    // válida depois da troca; só as outras sessões abertas em outros lugares caem.
    await authStore.changePassword({
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })

    successAt.value = Date.now()
    currentPassword.value = ''
    password.value = ''
    passwordConfirmation.value = ''
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      const body = error.response.data as { errors?: Record<string, string[]> }
      Object.assign(fieldErrors, body.errors ?? {})
      errorMessage.value = 'Corrija os campos indicados antes de salvar.'
    } else {
      errorMessage.value = 'Não foi possível trocar a senha. Tente novamente.'
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <AppLayout>
    <PageHeader title="Minha conta" />

    <p class="field__hint">
      {{ authStore.user?.name }} · {{ authStore.user?.email }}
    </p>

    <NoticeBanner
      v-if="successAt"
      variant="info"
    >
      Senha alterada.
    </NoticeBanner>
    <NoticeBanner
      v-if="errorMessage"
      variant="error"
    >
      {{ errorMessage }}
    </NoticeBanner>

    <form
      class="card"
      @submit.prevent="handleSubmit"
    >
      <input
        type="text"
        name="email"
        autocomplete="username"
        :value="authStore.user?.email"
        hidden
      >

      <div class="field">
        <label for="account-current-password">Senha atual</label>
        <input
          id="account-current-password"
          v-model="currentPassword"
          type="password"
          autocomplete="current-password"
          required
        >
        <span
          v-if="fieldErrors.current_password"
          class="field__error"
        >{{ fieldErrors.current_password[0] }}</span>
      </div>

      <div class="field">
        <label for="account-password">Nova senha</label>
        <input
          id="account-password"
          v-model="password"
          type="password"
          autocomplete="new-password"
          minlength="8"
          required
        >
        <span class="field__hint">Mínimo de 8 caracteres.</span>
        <span
          v-if="fieldErrors.password"
          class="field__error"
        >{{ fieldErrors.password[0] }}</span>
      </div>

      <div class="field">
        <label for="account-password-confirmation">Confirmar nova senha</label>
        <input
          id="account-password-confirmation"
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

      <button
        type="submit"
        class="btn btn--primary"
        :disabled="isSubmitting || passwordsMismatch"
      >
        {{ isSubmitting ? 'Salvando…' : 'Trocar senha' }}
      </button>
    </form>
  </AppLayout>
</template>
