<script setup lang="ts">
// Única página com layout próprio (ver docs/decisoes/0009-direcao-visual.md): sem hero de
// banco de imagem, três pilares com peso visual igual, linha de registro em modo `example`
// até validação institucional, sem carrossel nem animação decorativa.
useSeoMeta({
  title: 'Lar Anália Franco — creche, contraturno e bazar em Londrina',
  description:
    'Associação sem fins lucrativos em Londrina desde 1963. Creche conveniada, escola de contraturno e bazar beneficente, com prestação de contas pública.',
  ogTitle: 'Lar Anália Franco',
  ogDescription:
    'Associação sem fins lucrativos em Londrina desde 1963. Creche conveniada, escola de contraturno e bazar beneficente, com prestação de contas pública.',
})

// Peso visual igual entre os três pilares — quem chegou pelo bazar não precisa entender o
// que é um CEI primeiro (ver docs/estrutura-site.md §1.1).
const pillars = [
  {
    label: 'Educação Infantil',
    description:
      'CEI Tio Pedro: creche e pré-escola conveniada com a Prefeitura de Londrina, período integral, para crianças de 1 a 5 anos.',
    to: '/educacao-infantil',
    cta: 'Conhecer o CEI Tio Pedro',
  },
  {
    label: 'Contraturno',
    description:
      'Aulas de informática e inteligência artificial para adolescentes, na sala de informática inaugurada em julho de 2026.',
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
      <p class="home__eyebrow">Londrina, desde 1963</p>
      <h1>Uma creche, uma escola de contraturno e um bazar — sustentados pelo mesmo trabalho.</h1>
      <p class="home__lead">
        O Lar Anália Franco atende crianças na educação infantil, adolescentes no contraturno,
        e mantém as duas coisas em pé com a receita do próprio bazar beneficente. Esta página
        reúne as três frentes e a prestação de contas que sustenta cada uma delas.
      </p>
      <div class="home__hero-ctas">
        <NuxtLink to="/como-ajudar/doar" class="btn btn--primary">Doar</NuxtLink>
        <NuxtLink to="/transparencia" class="btn btn--secondary">Ver prestação de contas</NuxtLink>
      </div>
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
        Os valores abaixo mostram o formato da linha de registro — ainda aguardam validação da
        instituição antes de ir ao ar (ver <NuxtLink to="/transparencia">Transparência</NuxtLink>).
      </p>
      <div class="ledger">
        <LedgerLine value="250" label="Crianças atendidas na creche" date="ago/2026" example />
        <LedgerLine value="63" label="Anos de atuação em Londrina" date="1963–2026" example />
        <LedgerLine
          value="40%"
          label="Do orçamento vindo do Bazar Beneficente"
          date="ago/2026"
          example
        />
      </div>
    </section>

    <section class="home__ctas" aria-label="Como participar">
      <div class="card">
        <h2>Quer matricular sua criança?</h2>
        <p>O CEI Tio Pedro atende crianças de 1 a 5 anos, em período integral.</p>
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
  color: var(--color-linha);
  margin: 0 0 var(--space-3);
}

.home__hero h1 {
  margin-top: 0;
  max-width: 22ch;
}

.home__lead {
  font-size: var(--text-lg);
  color: var(--color-ink-soft);
  max-width: 58ch;
}

.home__hero-ctas {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  margin-top: var(--space-5);
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
  color: var(--color-quadra);
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
  color: var(--color-ink-soft);
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
