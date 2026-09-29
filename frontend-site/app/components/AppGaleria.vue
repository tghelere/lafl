<script setup lang="ts">
// A galeria de uma página, na ordem que a equipe deixou no painel ("Imagens desta página").
// Foto com legenda vira <figure>; sem legenda, fica só a imagem, como era nas galerias fixas.
// Galeria vazia não desenha nada, nem o título.
import type { PublicImage } from '~/composables/usePublicPage'

withDefaults(
  defineProps<{
    imagens: PublicImage[]
    titulo: string
    /** Rótulo da região para leitor de tela. */
    rotulo: string
    /** Largura mínima de cada coluna antes de a grade quebrar a linha. */
    colunaMinima?: string
    /** A primeira foto é a primeira imagem visível da página. */
    prioridade?: boolean
  }>(),
  { colunaMinima: '16rem', prioridade: false },
)
</script>

<template>
  <section v-if="imagens.length > 0" class="foto-galeria" :aria-label="rotulo">
    <h2>{{ titulo }}</h2>
    <div class="foto-galeria__grid" :style="{ '--coluna-minima': colunaMinima }">
      <template v-for="(imagem, indice) in imagens" :key="imagem.src">
        <figure v-if="imagem.caption" class="foto-galeria__figura">
          <AppImagem :imagem="imagem" contexto="metade" grupo="galeria" :prioridade="prioridade && indice === 0" />
          <figcaption>{{ imagem.caption }}</figcaption>
        </figure>
        <AppImagem
          v-else
          :imagem="imagem"
          contexto="metade"
          grupo="galeria"
          :prioridade="prioridade && indice === 0"
        />
      </template>
    </div>
  </section>
</template>

<style scoped>
.foto-galeria {
  margin-block: var(--space-8);
}

.foto-galeria__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(var(--coluna-minima), 1fr));
  gap: var(--space-5);
  margin-top: var(--space-5);
}

/* :deep — a raiz do AppImagem ampliável é o <a>, e o <img> dentro dele não leva o escopo. */
.foto-galeria__grid :deep(img) {
  border-radius: var(--radius-md);
}

.foto-galeria__figura {
  margin: 0;
}

.foto-galeria__figura figcaption {
  margin-top: var(--space-2);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}
</style>
