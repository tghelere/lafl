<script setup lang="ts">
// Página de índice curta dos três pilares — substitui a exposição deles no topo (ver
// docs/design/navegacao.md §2). Os rótulos e o subtítulo de uma linha de cada pilar vêm de
// app/config/navigation.ts (o mesmo texto do painel "O que fazemos" do header), para não
// manter a mesma frase em dois lugares.
import { navigation } from '~/config/navigation'

const pillars = navigation.find((item) => item.to === '/o-que-fazemos')?.children ?? []

// Uma foto por pilar: a capa da página de cada frente, escolhida no painel (ver App\Enums\
// PageImageRole). Pilar sem capa fica sem imagem, de propósito — hoje é o contraturno, que
// não tem foto própria —, em vez de ganhar uma foto de outra frente. As três páginas são
// pedidas juntas, e uma falha da API tira só as fotos, não os cartões.
const paginasDosPilares = await Promise.all(pillars.map((pillar) => usePublicPage(pillar.to.slice(1))))

const capaPorPilar = computed(() =>
  Object.fromEntries(
    pillars.map((pillar, indice) => {
      const envelope = paginasDosPilares[indice]?.data.value
      return [pillar.to, envelope && 'data' in envelope ? envelope.data.images.cover : null]
    }),
  ),
)

usePageSeo({
  title: 'O Que Fazemos — Lar Anália Franco',
  description: 'As três frentes do Lar Anália Franco: educação infantil, escola de contraturno e bazar beneficente.',
})
</script>

<template>
  <div>
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><span aria-current="page">O Que Fazemos</span></li>
      </ol>
    </nav>

    <h1>O que fazemos</h1>
    <p class="prose">
      Três frentes sustentadas pelo mesmo trabalho — conheça cada uma.
    </p>

    <div class="pillars-grid">
      <NuxtLink
        v-for="(pillar, index) in pillars"
        :key="pillar.to"
        :to="pillar.to"
        class="card pillars-grid__item"
      >
        <AppImagem
          v-if="capaPorPilar[pillar.to]"
          :imagem="capaPorPilar[pillar.to]!"
          contexto="terco"
          :prioridade="index === 0"
        />
        <h2>{{ pillar.label }}</h2>
        <p v-if="pillar.hint">{{ pillar.hint }}</p>
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.pillars-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
  gap: var(--space-5);
  margin-block: var(--space-7);
}

.pillars-grid__item {
  display: block;
  color: inherit;
  text-decoration: none;
}

.pillars-grid__item img {
  margin-bottom: var(--space-3);
  border-radius: var(--radius-md);
}

.pillars-grid__item h2 {
  margin-top: 0;
  font-size: var(--text-xl);
}

.pillars-grid__item p {
  margin-bottom: 0;
  color: var(--color-text-muted);
}
</style>
