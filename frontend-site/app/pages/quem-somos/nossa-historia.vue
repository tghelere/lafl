<script setup lang="ts">
// Sobrepõe [...slug].vue para acomodar as fotos da seção — mesmo padrão de nível 2 (busca a
// página-mãe para o breadcrumb), mais a placa de inauguração em destaque e os pares
// antes/depois como registro de época.
const { data, error } = await usePublicPage('quem-somos/nossa-historia')

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
}

const page = computed(() => (data.value && 'data' in data.value ? data.value.data : null))

const { data: parentData } = await usePublicPage('quem-somos')
const parentPage = computed(() =>
  parentData.value && 'data' in parentData.value ? parentData.value.data : null,
)

useSeoMeta({
  title: () => page.value?.meta_title || page.value?.title || 'Lar Anália Franco',
  description: () => page.value?.meta_description || undefined,
  ogTitle: () => page.value?.meta_title || page.value?.title || 'Lar Anália Franco',
  ogDescription: () => page.value?.meta_description || undefined,
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
      <figure class="placa-destaque">
        <AppFoto slug="placa-inauguracao" contexto="metade" prioridade />
        <figcaption>Placa de bronze na entrada da sede, com a frase dos fundadores.</figcaption>
      </figure>

      <!-- Conteúdo vem do CMS, sanitizado no backend ao salvar contra uma allowlist
           explícita (App\Support\Html\ContentSanitizer, ver
           docs/decisoes/0010-html-do-cms-sanitizado-no-backend.md) — é o que torna este
           v-html seguro, não a confiança em quem escreve pelo painel. -->
      <div class="page-content" v-html="page.content" />
    </article>

    <section class="registro-epoca" aria-label="Fachada e pátio em registro de época">
      <h2>Fachada e pátio, em registro de época</h2>
      <p class="prose">
        As fotos abaixo registram reformas já concluídas e não mostram necessariamente o
        estado atual da sede.
      </p>

      <div class="registro-epoca__par">
        <figure>
          <AppFoto slug="fachada-antes" contexto="metade" />
          <figcaption>Fachada — antes</figcaption>
        </figure>
        <figure>
          <AppFoto slug="fachada-depois" contexto="metade" />
          <figcaption>Fachada — depois (registro de época)</figcaption>
        </figure>
      </div>

      <div class="registro-epoca__par">
        <figure>
          <AppFoto slug="patio-antes" contexto="metade" />
          <figcaption>Pátio interno — antes</figcaption>
        </figure>
        <figure>
          <AppFoto slug="patio-depois" contexto="metade" />
          <figcaption>Pátio interno — depois (registro de época)</figcaption>
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

.placa-destaque picture {
  border-radius: var(--radius-lg);
  overflow: hidden;
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

.registro-epoca__par picture {
  border-radius: var(--radius-md);
  overflow: hidden;
}

.registro-epoca__par figcaption {
  margin-top: var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wide);
}
</style>
