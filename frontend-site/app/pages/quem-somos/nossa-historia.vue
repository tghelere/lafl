<script setup lang="ts">
// Sobrepõe [...slug].vue para acomodar as fotos da seção — mesmo padrão de nível 2 (busca a
// página-mãe para o breadcrumb), mais a primeira foto da galeria em destaque (a placa de
// inauguração) e as demais em pares antes/depois como registro de época.
const { data, error } = await usePublicPage('quem-somos/nossa-historia')

if (error.value) {
  // 404 só quando a API disse que a página não existe; qualquer outra falha vira 503 (ver
  // app/utils/apiPageError.ts).
  lancarErroDePagina(error.value)
}

const page = computed(() => (data.value && 'data' in data.value ? data.value.data : null))

// A galeria da página, editada no painel: a primeira foto é o destaque (a placa), e as outras
// seguem em pares, na ordem da galeria (antes e depois).
const destaque = computed(() => page.value?.images.gallery[0] ?? null)
const pares = computed(() => {
  const resto = page.value?.images.gallery.slice(1) ?? []
  const grupos = []
  for (let i = 0; i < resto.length; i += 2) {
    grupos.push(resto.slice(i, i + 2))
  }
  return grupos
})

const { data: parentData } = await usePublicPage('quem-somos')
const parentPage = computed(() =>
  parentData.value && 'data' in parentData.value ? parentData.value.data : null,
)

usePageSeo({
  title: () => page.value?.meta_title || page.value?.title || 'Lar Anália Franco',
  description: () => page.value?.meta_description || undefined,
})
</script>

<template>
  <template v-if="page">
    <nav class="breadcrumb" aria-label="Trilha de navegação">
      <ol>
        <li><NuxtLink to="/">Início</NuxtLink></li>
        <li><NuxtLink to="/quem-somos">{{ parentPage?.title }}</NuxtLink></li>
        <li><span aria-current="page">{{ page.title }}</span></li>
      </ol>
    </nav>

    <AppSectionNav />

    <article class="prose">
      <h1>{{ page.title }}</h1>

      <!-- A placa é a imagem mais importante da página (traz as datas de fundação e a frase
           dos fundadores, já no texto alternativo) — destaque próprio, não ilustração
           solta entre parágrafos. -->
      <figure v-if="destaque" class="placa-destaque">
        <AppImagem :imagem="destaque" contexto="metade" prioridade />
        <figcaption v-if="destaque.caption">{{ destaque.caption }}</figcaption>
      </figure>

      <!-- Conteúdo vem do CMS, sanitizado no backend ao salvar contra uma allowlist
           explícita (App\Support\Html\ContentSanitizer, ver
           docs/decisoes/0010-html-do-cms-sanitizado-no-backend.md) — é o que torna este
           v-html seguro, não a confiança em quem escreve pelo painel. -->
      <div class="page-content" v-html="page.content" />
    </article>

    <section v-if="pares.length > 0" class="registro-epoca" aria-label="Fachada e pátio em registro de época">
      <h2>Fachada e pátio, em registro de época</h2>
      <p class="prose">
        As fotos abaixo registram reformas já concluídas e não mostram necessariamente o
        estado atual da sede.
      </p>

      <div v-for="(par, indice) in pares" :key="indice" class="registro-epoca__par">
        <figure v-for="imagem in par" :key="imagem.src">
          <AppImagem :imagem="imagem" contexto="metade" />
          <figcaption v-if="imagem.caption">{{ imagem.caption }}</figcaption>
        </figure>
      </div>
    </section>
  </template>
</template>

<style scoped>
.placa-destaque {
  max-width: 22rem;
  margin: var(--space-6) 0;
}

.placa-destaque img {
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
}

.placa-destaque figcaption {
  margin-top: var(--space-2);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.registro-epoca {
  margin-block: var(--space-8);
}

.registro-epoca__par {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: var(--space-4);
  margin-top: var(--space-5);
}

.registro-epoca__par img {
  border-radius: var(--radius-md);
}

.registro-epoca__par figcaption {
  margin-top: var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wide);
}
</style>
