<script setup lang="ts">
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import AppLayout from '@/components/AppLayout.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import NoticeBanner from '@/components/NoticeBanner.vue'
import PageHeader from '@/components/PageHeader.vue'
import { fetchRoles } from '@/services/roles'
import {
  createUser,
  deactivateUser,
  fetchUser,
  generateUserPasswordLink,
  reactivateUser,
  updateUser,
} from '@/services/users'
import type { BreadcrumbItem } from '@/types/breadcrumb'
import type { RoleOption, UserAccount } from '@/types/users'

const route = useRoute()
const router = useRouter()

const uuid = computed(() => (typeof route.params.uuid === 'string' ? route.params.uuid : null))
const isEditing = computed(() => uuid.value !== null)

const record = ref<UserAccount | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)

/** "Usuários / Alice Atendimento / Editar" na edição; "Usuários / Novo usuário" na criação. */
const breadcrumb = computed<BreadcrumbItem[]>(() =>
  isEditing.value
    ? [
        { label: 'Usuários', to: { name: 'users.index' } },
        { label: record.value?.name ?? '', loading: isLoading.value },
        { label: 'Editar' },
      ]
    : [{ label: 'Usuários', to: { name: 'users.index' } }, { label: 'Novo usuário' }],
)

const name = ref('')
const email = ref('')
const selectedRoles = ref<string[]>([])

const roles = ref<RoleOption[]>([])
const rolesLoadError = ref<string | null>(null)

const isSaving = ref(false)
const isDeactivating = ref(false)
const isReactivating = ref(false)
const isGeneratingLink = ref(false)
const saveConfirmedAt = ref<number | null>(null)
const submitErrorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)
const fieldErrors = reactive<Record<string, string[]>>({})

// Só existe em memória, nunca persistido — sair da tela ou recarregar a torna irrecuperável
// de propósito (ver App\Actions\Users\GeneratePasswordLink: a API também não guarda o token
// em texto legível em lugar nenhum depois de gerado).
const generatedLink = ref<string | null>(null)

async function loadRoles(): Promise<void> {
  try {
    roles.value = await fetchRoles()
  } catch {
    rolesLoadError.value = 'Não foi possível carregar os papéis disponíveis. Tente novamente.'
  }
}

async function load(): Promise<void> {
  if (!uuid.value) {
    return
  }

  isLoading.value = true
  loadErrorMessage.value = null

  try {
    record.value = await fetchUser(uuid.value)
    name.value = record.value.name
    email.value = record.value.email
    selectedRoles.value = [...record.value.roles]
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 403) {
      loadErrorMessage.value = 'Você não tem permissão para ver este usuário.'
    } else if (axios.isAxiosError(error) && error.response?.status === 404) {
      loadErrorMessage.value = 'Usuário não encontrado.'
    } else {
      loadErrorMessage.value = 'Não foi possível carregar o usuário. Tente novamente.'
    }
  } finally {
    isLoading.value = false
  }
}

void loadRoles()

// "Salvar" no formulário de criação leva direto ao detalhe do usuário recém-criado
// (mesmo nome de rota 'users.edit', só troca o :uuid) — Vue Router reaproveita a mesma
// instância do componente nesse caso (não remonta), então um `load()` disparado uma vez só
// no setup nunca rodaria de novo, deixando a tela "editando" um usuário sem o `record`
// carregado (nome/e-mail/papéis ficariam com o que sobrou do formulário anterior, o resto —
// selo de ativo/inativo, botão desativar/reativar — ficaria errado por record ser null).
// Mesma lógica também cobre ir de uma edição direto para outra, ou de uma edição para "novo".
watch(
  () => route.fullPath,
  () => {
    generatedLink.value = null
    submitErrorMessage.value = null
    actionErrorMessage.value = null
    saveConfirmedAt.value = null
    clearFieldErrors()

    if (uuid.value) {
      void load()
    } else {
      record.value = null
      name.value = ''
      email.value = ''
      selectedRoles.value = []
    }
  },
  { immediate: true },
)

function clearFieldErrors(): void {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
}

/**
 * @returns true se o erro era 422 e já foi tratado campo a campo — quem chamou não precisa
 * de mensagem genérica.
 */
function applyValidationErrors(error: unknown): boolean {
  if (axios.isAxiosError(error) && error.response?.status === 422) {
    const body = error.response.data as { errors?: Record<string, string[]> }
    clearFieldErrors()
    Object.assign(fieldErrors, body.errors ?? {})
    submitErrorMessage.value = 'Corrija os campos indicados antes de salvar.'

    return true
  }

  return false
}

/**
 * As proteções do backend (ninguém desativa a si mesmo, último super_admin) respondem 422
 * com a mensagem pronta num campo (`user` ou `roles`, conforme o caso) — mostra a mensagem da
 * API tal como veio, sem reescrever, em vez de um texto genérico de erro.
 */
function extractApiMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error) && error.response?.data) {
    const body = error.response.data as { message?: string; errors?: Record<string, string[]> }
    const firstFieldError = body.errors ? Object.values(body.errors)[0]?.[0] : undefined

    return firstFieldError ?? body.message ?? fallback
  }

  return fallback
}

async function handleSubmit(): Promise<void> {
  isSaving.value = true
  submitErrorMessage.value = null
  saveConfirmedAt.value = null
  clearFieldErrors()

  const payload = { name: name.value, email: email.value, roles: selectedRoles.value }

  try {
    if (isEditing.value && uuid.value) {
      record.value = await updateUser(uuid.value, payload)
      saveConfirmedAt.value = Date.now()
    } else {
      const created = await createUser(payload)
      void router.push({ name: 'users.edit', params: { uuid: created.id }, query: { created: '1' } })

      return
    }
  } catch (error) {
    if (!applyValidationErrors(error)) {
      submitErrorMessage.value = extractApiMessage(error, 'Não foi possível salvar. Tente novamente.')
    }
  } finally {
    isSaving.value = false
  }
}

async function handleDeactivate(): Promise<void> {
  if (!uuid.value) {
    return
  }

  const confirmed = window.confirm(
    'Desativar este usuário? A pessoa perde o acesso ao painel na próxima ação que tentar fazer.',
  )

  if (!confirmed) {
    return
  }

  isDeactivating.value = true
  actionErrorMessage.value = null

  try {
    record.value = await deactivateUser(uuid.value)
  } catch (error) {
    actionErrorMessage.value = extractApiMessage(error, 'Não foi possível desativar. Tente novamente.')
  } finally {
    isDeactivating.value = false
  }
}

async function handleReactivate(): Promise<void> {
  if (!uuid.value) {
    return
  }

  const confirmed = window.confirm('Reativar este usuário? O acesso volta imediatamente.')

  if (!confirmed) {
    return
  }

  isReactivating.value = true
  actionErrorMessage.value = null

  try {
    record.value = await reactivateUser(uuid.value)
  } catch (error) {
    actionErrorMessage.value = extractApiMessage(error, 'Não foi possível reativar. Tente novamente.')
  } finally {
    isReactivating.value = false
  }
}

async function handleGenerateLink(): Promise<void> {
  if (!uuid.value) {
    return
  }

  const confirmed = window.confirm(
    'Gerar um novo link invalida qualquer link anterior enviado a esta pessoa, mesmo que ainda não tenha sido usado. Continuar?',
  )

  if (!confirmed) {
    return
  }

  isGeneratingLink.value = true
  actionErrorMessage.value = null

  try {
    generatedLink.value = await generateUserPasswordLink(uuid.value)
  } catch (error) {
    actionErrorMessage.value = extractApiMessage(error, 'Não foi possível gerar o link. Tente novamente.')
  } finally {
    isGeneratingLink.value = false
  }
}

const whatsappMessage = computed(() => {
  if (!generatedLink.value || !record.value) {
    return ''
  }

  return `Olá, ${record.value.name}! Aqui está o link para você definir sua senha de acesso ao painel do Lar Anália Franco: ${generatedLink.value}\n\nEle vale por 24 horas e só funciona uma vez.`
})

const clipboardErrorMessage = ref<string | null>(null)

async function copyToClipboard(text: string): Promise<void> {
  clipboardErrorMessage.value = null

  try {
    await navigator.clipboard.writeText(text)
  } catch {
    clipboardErrorMessage.value = 'Não foi possível copiar automaticamente — selecione e copie o texto manualmente.'
  }
}
</script>

<template>
  <AppLayout resource="users">
    <AppBreadcrumb :items="breadcrumb" />

    <LoadingState v-if="isLoading" />
    <ErrorState
      v-else-if="loadErrorMessage"
      :message="loadErrorMessage"
    />

    <template v-else>
      <PageHeader :title="isEditing ? 'Editar usuário' : 'Novo usuário'">
        <template
          v-if="isEditing && record"
          #badge
        >
          <span
            class="badge"
            :class="record.active ? 'badge--published' : 'badge--draft'"
          >
            {{ record.active ? 'Ativo' : 'Inativo' }}
          </span>
        </template>
      </PageHeader>

      <NoticeBanner
        v-if="route.query.created"
        variant="info"
      >
        Usuário criado. Gere o link de definição de senha abaixo para que a pessoa possa
        entrar.
      </NoticeBanner>
      <NoticeBanner
        v-if="submitErrorMessage"
        variant="error"
      >
        {{ submitErrorMessage }}
      </NoticeBanner>
      <NoticeBanner
        v-if="saveConfirmedAt"
        variant="info"
      >
        Usuário salvo.
      </NoticeBanner>
      <NoticeBanner
        v-if="actionErrorMessage"
        variant="error"
      >
        {{ actionErrorMessage }}
      </NoticeBanner>

      <form
        class="card"
        @submit.prevent="handleSubmit"
      >
        <div class="field">
          <label for="user-name">Nome</label>
          <input
            id="user-name"
            v-model="name"
            type="text"
            maxlength="255"
            required
          >
          <span
            v-if="fieldErrors.name"
            class="field__error"
          >{{ fieldErrors.name[0] }}</span>
        </div>

        <div class="field">
          <label for="user-email">E-mail</label>
          <input
            id="user-email"
            v-model="email"
            type="email"
            maxlength="255"
            required
          >
          <span
            v-if="fieldErrors.email"
            class="field__error"
          >{{ fieldErrors.email[0] }}</span>
        </div>

        <div class="field">
          <span class="field__legend">Papéis</span>
          <p
            v-if="rolesLoadError"
            class="field__error"
          >
            {{ rolesLoadError }}
          </p>
          <div class="role-checkbox-list">
            <label
              v-for="role in roles"
              :key="role.value"
              class="role-checkbox"
            >
              <input
                v-model="selectedRoles"
                type="checkbox"
                :value="role.value"
              >
              <span class="role-checkbox__body">
                <span class="role-checkbox__label">{{ role.label }}</span>
                <span class="role-checkbox__description">{{ role.description }}</span>
              </span>
            </label>
          </div>
          <span
            v-if="fieldErrors.roles"
            class="field__error"
          >{{ fieldErrors.roles[0] }}</span>
        </div>

        <button
          type="submit"
          class="btn btn--primary"
          :disabled="isSaving"
        >
          {{ isSaving ? 'Salvando…' : 'Salvar' }}
        </button>
      </form>

      <div
        v-if="isEditing"
        class="user-form__actions"
      >
        <button
          v-if="record?.active"
          type="button"
          class="btn btn--danger"
          :disabled="isDeactivating"
          @click="handleDeactivate"
        >
          {{ isDeactivating ? 'Desativando…' : 'Desativar' }}
        </button>
        <button
          v-else
          type="button"
          class="btn btn--primary"
          :disabled="isReactivating"
          @click="handleReactivate"
        >
          {{ isReactivating ? 'Reativando…' : 'Reativar' }}
        </button>

        <button
          type="button"
          class="btn btn--secondary"
          :disabled="isGeneratingLink"
          @click="handleGenerateLink"
        >
          {{ isGeneratingLink ? 'Gerando…' : 'Gerar link de definição de senha' }}
        </button>
      </div>

      <div
        v-if="generatedLink"
        class="card user-form__link-card"
      >
        <h2>Link de definição de senha</h2>
        <p class="field__hint">
          Copie e envie por fora (WhatsApp) — a tela não manda nada sozinha. Vale por 24
          horas, funciona uma única vez, e gerar um novo invalida este. Depois de sair desta
          tela ou recarregar a página, o link não pode ser recuperado — só gerar outro.
        </p>
        <p class="user-form__link-value">
          {{ generatedLink }}
        </p>
        <div class="user-form__link-actions">
          <button
            type="button"
            class="btn btn--primary"
            @click="copyToClipboard(generatedLink)"
          >
            Copiar link
          </button>
          <button
            type="button"
            class="btn btn--secondary"
            @click="copyToClipboard(whatsappMessage)"
          >
            Copiar mensagem
          </button>
        </div>
        <p
          v-if="clipboardErrorMessage"
          class="field__error"
        >
          {{ clipboardErrorMessage }}
        </p>
      </div>
    </template>
  </AppLayout>
</template>

<style scoped>
.user-form__actions {
  display: flex;
  gap: var(--space-3);
  margin-top: var(--space-5);
}

.user-form__link-card {
  margin-top: var(--space-5);
}

.user-form__link-value {
  font-family: monospace;
  font-size: var(--text-sm);
  padding: var(--space-3);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow-wrap: break-word;
  margin: var(--space-3) 0;
}

.user-form__link-actions {
  display: flex;
  gap: var(--space-3);
}
</style>
