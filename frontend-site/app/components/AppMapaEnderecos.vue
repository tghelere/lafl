<script setup lang="ts">
// Único uso: o mapa estático de /contato. Não passa por AppFoto — AppFoto é para fotos
// exibidas em largura fluida (sizes + srcset por largura, ver docs/fotos.md); aqui a exibição
// é de largura FIXA (a mesma do bloco dos dois cartões de endereço), e o srcset correto para
// largura fixa é por densidade de pixel (1x/2x), não por largura.
//
// Arquivos gerados por script direto de tiles do OpenStreetMap, cada densidade na sua própria
// resolução nativa de zoom — nunca redimensionado depois (era a causa do texto borrado da
// versão anterior): 1x = zoom 18, 1088×612; 2x = zoom 19, 2176×1224; mesmo enquadramento
// geográfico nos dois. `alt` e as dimensões do 1x vêm de app/data/fotos.ts (mesma fonte de
// verdade do catálogo de fotos, ainda que esta imagem não seja uma foto).
import { fotos } from '~/data/fotos'

const foto = fotos['mapa-enderecos']
</script>

<template>
  <picture class="mapa-enderecos">
    <source
      type="image/webp"
      srcset="/fotos/contato/mapa-enderecos-1x.webp 1x, /fotos/contato/mapa-enderecos-2x.webp 2x"
    />
    <img
      src="/fotos/contato/mapa-enderecos-1x.jpg"
      srcset="/fotos/contato/mapa-enderecos-1x.jpg 1x, /fotos/contato/mapa-enderecos-2x.jpg 2x"
      :width="foto.largura"
      :height="foto.altura"
      :alt="foto.alt"
      loading="lazy"
    />
  </picture>
</template>

<style scoped>
.mapa-enderecos {
  display: block;
  width: 100%;
}
</style>
