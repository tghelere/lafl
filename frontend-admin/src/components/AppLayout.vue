<script setup lang="ts">
import { computed, watchEffect } from 'vue'
import { useRouter } from 'vue-router'

import AccessDeniedState from '@/components/AccessDeniedState.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import NotFoundState from '@/components/NotFoundState.vue'
import { sessionIdleTimeoutMinutes } from '@/config'
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

useIdleTimeout(sessionIdleTimeoutMinutes, () => {
  void handleLogout()
})

// 'unmapped': a chave de `resource` não existe no mapa devolvido por /auth/user — tanto bug de
// integração (nome usado na tela não bate com o nome que o backend calcula) quanto recurso
// inexistente vindo direto da URL (ex.: /admin/recurso-que-nao-existe). Para quem usa o
// painel isto não é "sem permissão", é "isto não existe" — por isso mostra a mesma tela da
// rota coringa (NotFoundState), não a de acesso negado. O console.error abaixo continua
// existindo para quem desenvolve identificar o primeiro caso.
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
          <RouterLink :to="{ name: 'account' }">
            {{ authStore.user.name }}
          </RouterLink>
          · {{ authStore.user.roles.join(', ') }}
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
        <NotFoundState
          v-else-if="accessState === 'unmapped'"
        />
        <slot v-else />
      </main>
    </div>
  </div>
</template>
