<script setup lang="ts">
/**
 * Substitui a página de erro padrão do Nuxt — aquela tela preta com a mensagem em inglês e a
 * pilha de chamadas, que num site institucional lê como "o site quebrou de vez".
 *
 * Duas telas, decididas pelo `statusCode` que `app/utils/apiPageError.ts` escolheu:
 *
 * - **404** — o endereço não existe. A pessoa errou o caminho ou veio de um link velho; o
 *   resto do site está de pé, então o que ajuda é mostrar para onde ir.
 * - **5xx (503)** — a API não respondeu. O endereço existe; o que falta é o conteúdo.
 *
 * **Os links de seção aparecem SÓ no 404, de propósito.** No 503 o que falhou foi a API, e
 * toda página de seção lê a API: oferecer os links ali seria oferecer uma fila de becos sem
 * saída.
 * O que serve nesse caso é dizer que o problema é nosso e pedir para voltar depois.
 *
 * Header e rodapé entram por `<NuxtLayout>`, o mesmo do site inteiro — e podem entrar porque
 * nenhum dos dois consulta a API (leem `app/config/navigation.ts`, que é arquivo). Se um dia
 * passarem a consultar, esta página cai junto com o que ela existe para cobrir.
 */
import { navigation } from '~/config/navigation'

const props = defineProps<{ error: { statusCode?: number } }>()

const status = computed(() => props.error?.statusCode ?? 500)
const naoEncontrada = computed(() => status.value === 404)

const titulo = computed(() =>
  naoEncontrada.value ? 'Página não encontrada' : 'Página indisponível no momento',
)

const explicacao = computed(() =>
  naoEncontrada.value
    ? 'O endereço que você abriu não existe ou foi movido. Pode ser um link antigo, um endereço digitado com algum engano, ou uma página que saiu do ar.'
    : 'Não conseguimos carregar o conteúdo desta página agora. A falha é do nosso lado e costuma durar pouco — tente de novo em alguns minutos.',
)

usePageSeo({
  title: () => `${titulo.value} — Lar Anália Franco`,
  // Página de erro não é conteúdo: nada aqui deve ser indexado nem virar cartão de
  // compartilhamento. `noindex` também deixa `usePageSeo` sem emitir canônico e Open Graph,
  // que aqui apontariam para um endereço que não tem o que mostrar.
  noindex: true,
})
</script>

<template>
  <NuxtLayout>
    <div class="erro">
      <p class="erro__codigo">Erro {{ status }}</p>

      <h1>{{ titulo }}</h1>

      <p class="prose erro__explicacao">{{ explicacao }}</p>

      <!-- <NuxtLink> mesmo, como no resto do site: ele renderiza uma âncora de verdade no
           HTML do servidor, então funciona sem JavaScript — que é a situação de quem chega
           aqui vindo de uma falha de carregamento. Sair do estado de erro na troca de rota é
           o Nuxt que faz sozinho (conferido nesta sessão contra o build de produção: clicar
           em "Transparência" no cabeçalho a partir do 404 abre a página certa). -->
      <NuxtLink to="/" class="btn btn--primary erro__voltar">Voltar para o início</NuxtLink>

      <nav v-if="naoEncontrada" class="erro__secoes" aria-labelledby="erro-secoes-titulo">
        <h2 id="erro-secoes-titulo" class="erro__secoes-titulo">Ou vá direto para uma seção</h2>

        <!-- Lida de app/config/navigation.ts, a mesma fonte do cabeçalho e do rodapé: link
             solto em template aqui envelheceria sozinho quando a navegação mudasse. -->
        <ul class="erro__lista">
          <li v-for="item in navigation" :key="item.to">
            <NuxtLink :to="item.to">{{ item.label }}</NuxtLink>
          </li>
        </ul>
      </nav>
    </div>
  </NuxtLayout>
</template>

<style scoped>
.erro {
  max-width: 44rem;
  padding-block: var(--space-6);
}

.erro__codigo {
  margin-bottom: var(--space-2);
  font-size: var(--text-sm);
  font-weight: var(--weight-medium);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.erro__explicacao {
  margin-bottom: var(--space-6);
}

.erro__voltar {
  /* `.btn` é inline-flex; sem isto a nav abaixo subiria para a mesma linha. */
  display: inline-flex;
}

.erro__secoes {
  margin-top: var(--space-8);
  padding-top: var(--space-6);
  border-top: 1px solid var(--color-border);
}

.erro__secoes-titulo {
  margin-bottom: var(--space-4);
  font-size: var(--text-base);
  font-weight: var(--weight-medium);
  color: var(--color-text-muted);
}

.erro__lista {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3) var(--space-6);
  margin: 0;
  padding: 0;
  list-style: none;
}

.erro__lista a {
  font-weight: var(--weight-medium);
}
</style>
