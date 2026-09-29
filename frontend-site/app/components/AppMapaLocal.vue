<script setup lang="ts">
// Só o link para abrir o endereço no aplicativo de mapas do aparelho — o mapa estático em si
// (imagem única cobrindo os dois locais) é AppMapaEnderecos.vue, usado direto em contato.vue,
// não por este componente (ver docs/roadmap.md, correção da sessão de mapa). Nenhuma requisição de rede: geo link
// universal do Google, resolve por texto de endereço, só navega quando clicado — não carrega
// nada em página.
const props = defineProps<{
  label: string
  address: string
}>()

const mapsAppHref = computed(
  () => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(props.address)}`,
)
</script>

<template>
  <a
    class="btn btn--secondary app-mapa-link"
    :href="mapsAppHref"
    :aria-label="`Abrir ${label} no aplicativo de mapas`"
    target="_blank"
    rel="noopener noreferrer"
  >
    Abrir no aplicativo de mapas
  </a>
</template>

<style scoped>
.app-mapa-link {
  margin-top: var(--space-3);
}
</style>
