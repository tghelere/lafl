<script setup lang="ts">
// Imagem da biblioteca do painel (docs/decisoes/0024-biblioteca-de-midia.md): capa ou foto de
// galeria que chega pronta da API, com `srcset` das derivadas webp que existem agora e as
// dimensões da original, para não deslocar o layout enquanto carrega.
//
// Substitui o AppFoto, que montava o caminho de arquivos fixos em public/fotos/. Não há
// <picture> com fallback em JPEG: as derivadas são só webp, que todo navegador atual lê.
import type { PublicImage } from '~/composables/usePublicPage'

const props = withDefaults(
  defineProps<{
    imagem: PublicImage
    /** Fração aproximada da viewport que a imagem ocupa — define o atributo `sizes`. */
    contexto: 'cheia' | 'metade' | 'terco' | 'quarto'
    /** Primeira imagem visível da página: carrega imediata e com prioridade alta. */
    prioridade?: boolean
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
</template>
