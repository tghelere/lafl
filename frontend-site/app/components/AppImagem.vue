<script setup lang="ts">
// Imagem da biblioteca do painel (docs/decisoes/0024-biblioteca-de-midia.md): capa ou foto de
// galeria que chega pronta da API, com `srcset` das derivadas webp que existem agora e as
// dimensões da original, para não deslocar o layout enquanto carrega.
//
// Substitui o AppFoto, que montava o caminho de arquivos fixos em public/fotos/. Não há
// <picture> com fallback em JPEG: as derivadas são só webp, que todo navegador atual lê.
//
// `grupo`: a imagem é de CONTEÚDO e abre ampliada (sessão 30). Vira um link para a maior
// derivada, que funciona sem JavaScript, e a ampliação (AppAmpliacao.vue) intercepta o
// clique e navega entre as imagens do mesmo grupo. Sem `grupo`, a imagem não é ampliável: é o
// caso do destaque da página inicial e dos cartões de "O que fazemos", que são capa.

import type { PublicImage } from '~/composables/usePublicPage'

const props = withDefaults(
  defineProps<{
    imagem: PublicImage
    /** Fração aproximada da viewport que a imagem ocupa — define o atributo `sizes`. */
    contexto: 'cheia' | 'metade' | 'terco' | 'quarto'
    /** Primeira imagem visível da página: carrega imediata e com prioridade alta. */
    prioridade?: boolean
    /** Grupo de ampliação (ex.: "galeria"). Presente = a imagem abre ampliada. */
    grupo?: string
  }>(),
  { prioridade: false },
)

const SIZES: Record<typeof props.contexto, string> = {
  cheia: '100vw',
  metade: '(min-width: 768px) 50vw, 100vw',
  terco: '(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw',
  quarto: '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw',
}
</script>

<template>
  <a
    v-if="grupo"
    :href="imagem.full"
    class="midia-ampliavel"
    data-ampliar
    :data-grupo="grupo"
    :data-credito="imagem.credit || undefined"
    :aria-label="`Ampliar imagem: ${imagem.alt}`"
  >
    <img
      :src="imagem.src"
      :srcset="imagem.srcset"
      :sizes="SIZES[contexto]"
      :width="imagem.width"
      :height="imagem.height"
      :alt="imagem.alt"
      :loading="prioridade ? undefined : 'lazy'"
      :fetchpriority="prioridade ? 'high' : undefined"
      decoding="async"
    />
  </a>
  <img
    v-else
    :src="imagem.src"
    :srcset="imagem.srcset"
    :sizes="SIZES[contexto]"
    :width="imagem.width"
    :height="imagem.height"
    :alt="imagem.alt"
    :loading="prioridade ? undefined : 'lazy'"
    :fetchpriority="prioridade ? 'high' : undefined"
    decoding="async"
  />
</template>
