<script setup lang="ts">
/**
 * Usada em dois lugares: pela rota coringa do router (URL totalmente desconhecida) e por
 * AppLayout quando `resource` não existe no mapa de acesso — do ponto de vista de quem usa o
 * painel, os dois casos são a mesma coisa ("isto não existe"), mesmo a causa técnica sendo
 * diferente (rota inexistente vs. bug de integração de nome de recurso). Por isso é a mesma
 * tela nos dois casos, não duas mensagens parecidas.
 */
import { ArrowLeft } from 'lucide-vue-next'

import AppIcon from '@/components/AppIcon.vue'
</script>

<template>
  <div
    class="state-message"
    role="status"
  >
    <h1>Página não encontrada</h1>
    <p>O endereço não existe ou o recurso não está mais disponível.</p>
    <RouterLink
      :to="{ name: 'dashboard' }"
      class="btn btn--secondary"
    >
      <AppIcon :icon="ArrowLeft" />
      Voltar ao Início
    </RouterLink>
  </div>
</template>
