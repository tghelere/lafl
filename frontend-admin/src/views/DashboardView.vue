<script setup lang="ts">
import AppLayout from '@/components/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
</script>

<template>
  <AppLayout>
    <h1>Início</h1>
    <p v-if="authStore.user">
      Olá, {{ authStore.user.name }}.
    </p>

    <!-- Pendências por tipo de formulário (ver docs/estrutura-site.md §4.2) chegam na
         Etapa 3 desta sessão — este é só o layout novo aplicado à rota que já existia. -->
    <p>
      Esta é uma rota protegida de exemplo — o domínio de assistidos ainda não foi
      implementado nesta fatia.
    </p>
  </AppLayout>
</template>
