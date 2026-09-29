<script setup lang="ts">
// Único uso: o mapa estático de /contato. É a única imagem do site que não vem da biblioteca
// do painel, de propósito: não é foto, é um mosaico de blocos do OpenStreetMap gerado por
// script, e a exibição é de largura FIXA (a mesma do bloco dos dois cartões de endereço). Para
// largura fixa, o srcset certo é por densidade de pixel (1x/2x), e a biblioteca gera derivadas
// por largura (ver docs/fotos.md).
//
// Cada densidade foi gerada na sua própria resolução nativa de zoom e nunca redimensionada
// depois (era a causa do texto borrado de uma versão anterior): 1x = zoom 18, 1088×450; 2x =
// zoom 19, 2176×900; mesmo enquadramento geográfico nos dois.
//
// Altura baixa de propósito: o mapa é apoio, não protagonista, e 940px empurrava o formulário
// para baixo da dobra. Critério de enquadramento, nesta ordem — (1) altura de exibição entre
// 420 e 480px; (2) os dois pinos visíveis, com folga acima do pino 2 (o mais ao norte); (3) a
// Avenida Anália Franco visível abaixo do pino 1, mesmo sem folga. "Rua Helen Keller" fica
// parcialmente cortada no topo como consequência aceita.
//
// Pino 1 (Sede/CEI) usa a coordenada exata de um POI já nomeado no OSM ("C.E.I. Analia Franco
// - Lar Anália Franco de Londrina"). Pino 2 (Bazar) não tem ponto de numeração de casa mapeado
// no OSM para "Rua Rosa Siqueira, 152" — usa o centroide do trecho de rua com o mesmo CEP da
// Sede/CEI (86039-560), precisão de rua/quadra, não de fachada exata (por isso a ressalva
// "localização aproximada" no `alt`). Não inventar uma coordenada mais precisa do que isso sem
// confirmar o ponto com a administração do bazar.
const alt =
  'Mapa de ruas do Jardim Aeroporto, em Londrina/PR, com dois alfinetes numerados: 1, a Sede/CEI Anália Franco, na Avenida Anália Franco; 2, o Bazar Beneficente (localização aproximada), na Rua Rosa Siqueira, a poucas quadras de distância'
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
      width="1088"
      height="450"
      :alt="alt"
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
