<script setup lang="ts">
/**
 * `unmapped` é um caso diferente de "sem permissão": a chave de recurso pedida pela tela nem
 * existe no mapa que a API devolveu (ver App\Http\Resources\UserResource::accessMap no
 * backend) — bug de integração entre o nome usado no painel e o nome usado no backend, não
 * falta de papel do usuário. Ver AppLayout.vue, que também registra isto no console.
 */
withDefaults(
  defineProps<{
    unmapped?: boolean
  }>(),
  { unmapped: false },
)
</script>

<template>
  <p
    class="state-message state-message--error"
    role="alert"
  >
    <template v-if="unmapped">
      Não foi possível confirmar seu acesso a este recurso — é um problema técnico, não falta
      de permissão. Avise o time técnico.
    </template>
    <template v-else>
      Você não tem permissão para acessar este recurso.
    </template>
  </p>
</template>
