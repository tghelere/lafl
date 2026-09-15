<script setup lang="ts">
// Único componente de imagem responsiva do site — nenhuma página referencia caminho de
// imagem diretamente (ver docs/fotos.md). Lê tudo de app/data/fotos.ts a partir do slug;
// monta o caminho em /fotos/{secao}/{slug}-{largura}.{webp,jpg} em tempo de execução (por
// isso os arquivos ficam em public/, não em assets/ — o Vite não analisa esse caminho
// estaticamente para gerar variante).
//
// Slug inexistente lança erro em setup(): como a maioria das páginas é prerenderizada (ver
// nuxt.config.ts), isso quebra `nuxt generate`/`nuxt build` em vez de falhar em silêncio no
// navegador de quem visita.
import { fotos, type FotoSlug } from '~/data/fotos'

const props = withDefaults(
  defineProps<{
    slug: FotoSlug
    /** Fração aproximada da viewport que a imagem ocupa — define o atributo `sizes`. */
    contexto: 'cheia' | 'metade' | 'terco' | 'quarto'
    /** Primeira imagem visível da página: carrega imediata e com prioridade alta. */
    prioridade?: boolean
  }>(),
  { prioridade: false },
)

const foto = fotos[props.slug]

if (!foto) {
  throw new Error(`AppFoto: slug "${props.slug}" não existe em app/data/fotos.ts`)
}

const SIZES: Record<typeof props.contexto, string> = {
  cheia: '100vw',
  metade: '(min-width: 768px) 50vw, 100vw',
  terco: '(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw',
  quarto: '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw',
}

const base = `/fotos/${foto.secao}/${props.slug}`
const larguraMaxima = Math.max(...foto.larguras)

const srcsetWebp = foto.larguras.map((w) => `${base}-${w}.webp ${w}w`).join(', ')
const srcsetJpg = foto.larguras.map((w) => `${base}-${w}.jpg ${w}w`).join(', ')
const srcJpg = `${base}-${larguraMaxima}.jpg`
const sizes = SIZES[props.contexto]
</script>

<template>
  <picture>
    <source type="image/webp" :srcset="srcsetWebp" :sizes="sizes" />
    <img
      :src="srcJpg"
      :srcset="srcsetJpg"
      :sizes="sizes"
      :width="foto.largura"
      :height="foto.altura"
      :alt="foto.alt"
      :loading="prioridade ? undefined : 'lazy'"
      :fetchpriority="prioridade ? 'high' : undefined"
    />
  </picture>
</template>
