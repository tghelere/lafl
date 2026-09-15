<script setup lang="ts">
// Única página com layout próprio (ver docs/decisoes/0009-direcao-visual.md): sem hero de
// banco de imagem, três pilares com peso visual igual, linha de registro em modo `example`
// até validação institucional, sem carrossel nem animação decorativa.
useSeoMeta({
  title: 'Lar Anália Franco — creche, contraturno e bazar em Londrina',
  description:
    'Associação civil beneficente, filantrópica e de natureza espírita em Londrina. Creche conveniada, escola de contraturno e bazar beneficente, com prestação de contas pública.',
  ogTitle: 'Lar Anália Franco',
  ogDescription:
    'Associação civil beneficente, filantrópica e de natureza espírita em Londrina. Creche conveniada, escola de contraturno e bazar beneficente, com prestação de contas pública.',
})

// Peso visual igual entre os três pilares — quem chegou pelo bazar não precisa entender o
// que é um CEI primeiro (ver docs/estrutura-site.md §1.1).
const pillars = [
  {
    label: 'Educação Infantil',
    description:
      'CEI Anália Franco: creche e pré-escola conveniada com a Prefeitura de Londrina, período integral, para crianças de 1 a 5 anos.',
    to: '/educacao-infantil',
    cta: 'Conhecer o CEI Anália Franco',
  },
  {
    label: 'Contraturno',
    description:
      'Programa em preparação para crianças e adolescentes de 6 a 15 anos — oficinas de audiovisual, música, esportes e informática, com início previsto para 2027.',
    to: '/contraturno',
    cta: 'Conhecer o contraturno',
  },
  {
    label: 'Bazar Beneficente',
    description:
      'Loja de doações em funcionamento desde 1968 — financia o que o convênio da creche não cobre.',
    to: '/bazar',
    cta: 'Conhecer o bazar',
  },
]
</script>

<template>
  <div class="home">
    <section class="home__hero">
      <p class="home__eyebrow">Londrina</p>
      <h1>Uma creche, uma escola de contraturno e um bazar — sustentados pelo mesmo trabalho.</h1>
      <p class="home__lead">
        O Lar Anália Franco atende crianças na educação infantil e prepara uma escola de
        contraturno para adolescentes, com início de turmas previsto para 2027. A creche é
        custeada pelo Termo de Colaboração com a Prefeitura de Londrina — R$ 2.819.892,84
        previstos para 2026 — e é o bazar beneficente que cobre o que esse convênio não cobre.
        Esta página reúne as três frentes e a prestação de contas que sustenta cada uma delas.
      </p>
      <div class="home__hero-ctas">
        <NuxtLink to="/doar" class="btn btn--primary">Doar</NuxtLink>
        <NuxtLink to="/transparencia" class="btn btn--secondary">Ver prestação de contas</NuxtLink>
      </div>
    </section>

    <section class="home__figure" aria-label="Fachada da sede">
      <AppFoto slug="fachada-sede" contexto="cheia" prioridade />
    </section>

    <section class="home__pillars" aria-label="Os três pilares da instituição">
      <article v-for="pillar in pillars" :key="pillar.to" class="card home__pillar">
        <h2>{{ pillar.label }}</h2>
        <p>{{ pillar.description }}</p>
        <NuxtLink :to="pillar.to" class="home__pillar-link">{{ pillar.cta }} →</NuxtLink>
      </article>
    </section>

    <section class="home__ledger" aria-labelledby="home-ledger-heading">
      <h2 id="home-ledger-heading">A instituição em números</h2>
      <p class="home__ledger-note">
        Números com fonte documental — veja o acervo completo em
        <NuxtLink to="/transparencia">Transparência</NuxtLink>.
      </p>
      <div class="ledger">
        <LedgerLine
          value="15"
          label="Turmas de educação infantil em período integral"
          date="Plano de trabalho 2026"
        />
        <LedgerLine
          value="R$ 2.819.892,84"
          label="Repasse do Termo de Colaboração com o Município"
          date="2026"
        />
        <LedgerLine value="1968" label="Bazar beneficente em funcionamento desde" date="58 anos" />
        <LedgerLine value="1953" label="Fundação da associação" />
      </div>
    </section>

    <section class="home__ctas" aria-label="Como participar">
      <div class="card">
        <h2>Quer matricular sua criança?</h2>
        <p>O CEI Anália Franco atende crianças de 1 a 5 anos, em período integral.</p>
        <NuxtLink to="/educacao-infantil" class="btn btn--secondary">Conhecer o CEI</NuxtLink>
      </div>
      <div class="card">
        <h2>Quer ajudar sem doar dinheiro?</h2>
        <p>O Bazar Beneficente aceita doação de itens e sustenta boa parte da instituição.</p>
        <NuxtLink to="/bazar/o-que-aceitamos" class="btn btn--secondary">Doar itens</NuxtLink>
      </div>
      <div class="card">
        <h2>Quer entender de onde vem cada real?</h2>
        <p>Balanços, atas e editais — o acervo de prestação de contas está todo publicado.</p>
        <NuxtLink to="/transparencia" class="btn btn--secondary">Ver documentos</NuxtLink>
      </div>
    </section>
  </div>
</template>

<style scoped>
.home__hero {
  max-width: 46rem;
  padding-block: var(--space-6) var(--space-7);
}

.home__eyebrow {
  font-size: var(--text-xs);
  font-weight: var(--weight-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--color-text-muted);
  margin: 0 0 var(--space-3);
}

.home__hero h1 {
  margin-top: 0;
  max-width: 22ch;
}

.home__lead {
  font-size: var(--text-lg);
  color: var(--color-text-muted);
  max-width: 58ch;
}

.home__hero-ctas {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  margin-top: var(--space-5);
}

.home__figure {
  margin-bottom: var(--space-8);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

/* Peso visual igual: mesma largura mínima para os três cartões, cresce por igual. */
.home__pillars {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
  gap: var(--space-5);
  margin-block: var(--space-8);
}

.home__pillar {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.home__pillar h2 {
  margin: 0;
  font-size: var(--text-xl);
}

.home__pillar-link {
  margin-top: auto;
  padding-top: var(--space-3);
  font-weight: var(--weight-medium);
  color: var(--color-focus);
  text-decoration: none;
}

.home__pillar-link:hover {
  text-decoration: underline;
}

.home__ledger {
  margin-block: var(--space-8);
}

.home__ledger h2 {
  margin-top: 0;
}

.home__ledger-note {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  max-width: 58ch;
  margin-bottom: var(--space-5);
}

.home__ctas {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
  gap: var(--space-5);
  margin-block: var(--space-8);
}

.home__ctas .card {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.home__ctas .card h2 {
  margin-top: 0;
  font-size: var(--text-lg);
}

.home__ctas .btn {
  align-self: flex-start;
  margin-top: var(--space-2);
}
</style>
