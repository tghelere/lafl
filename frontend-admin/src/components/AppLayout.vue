<script setup lang="ts">
import { computed, watchEffect } from 'vue'
import { useRouter } from 'vue-router'

import AccessDeniedState from '@/components/AccessDeniedState.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import { useIdleTimeout } from '@/composables/useIdleTimeout'
import { useAuthStore } from '@/stores/auth'

/**
 * `resource`, quando informado, é checado contra authStore.user.access (ver
 * src/types/auth.ts) — o mesmo mapa que o backend calcula via Policy::viewAny em cada
 * recurso, nunca papel fixo decidido aqui. Sem `resource`, a tela é livre para qualquer
 * usuário autenticado (Início, /conta). Rota de recurso sem acesso não abre: mantém sidebar e
 * topbar, troca só o conteúdo por um aviso — nunca redireciona, então a URL, o histórico e o
 * botão voltar continuam corretos. Quem barra de verdade continua sendo a API; isto é só para
 * não fazer a pessoa esperar uma resposta 403 pra descobrir que não tinha acesso.
 */
const props = defineProps<{
  resource?: string
}>()

const authStore = useAuthStore()
const router = useRouter()

const idleTimeoutMinutes = Number(import.meta.env.VITE_SESSION_IDLE_TIMEOUT_MINUTES ?? '15')

useIdleTimeout(idleTimeoutMinutes, () => {
  void handleLogout()
})

// 'unmapped': a chave de `resource` não existe no mapa devolvido por /auth/user — bug de
// integração (nome usado na tela não bate com o nome que o backend calcula), não falta de
// permissão do usuário. Reportado à parte de "denied" de propósito (ver CLAUDE.md: "exceto
// se algo não couber no mapa, reportar").
const accessState = computed<'allowed' | 'denied' | 'unmapped' | null>(() => {
  if (!props.resource) {
    return null
  }

  const access = authStore.user?.access

  if (!access || !(props.resource in access)) {
    return 'unmapped'
  }

  return access[props.resource] ? 'allowed' : 'denied'
})

watchEffect(() => {
  if (accessState.value === 'unmapped') {
    console.error(
      `AppLayout: recurso "${props.resource}" não existe no mapa de acesso devolvido por /auth/user — confira App\\Http\\Resources\\UserResource::accessMap no backend.`,
    )
  }
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
        <AccessDeniedState
          v-if="accessState === 'denied'"
        />
        <AccessDeniedState
          v-else-if="accessState === 'unmapped'"
          unmapped
        />
        <slot v-else />
      </main>
    </div>
  </div>
</template>
