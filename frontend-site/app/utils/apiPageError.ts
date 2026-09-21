/**
 * Traduz a falha de uma leitura da API em erro de página do Nuxt — e essa tradução é o
 * ponto inteiro deste arquivo.
 *
 * Até a sessão 22 toda página fazia `if (error.value) throw createError({ statusCode: 404 })`.
 * Isso confunde duas coisas que o buscador lê de formas opostas:
 *
 * - **404** é uma afirmação sobre o conteúdo: "esta página não existe". O Google a trata como
 *   removida e, repetida, tira o endereço do índice.
 * - **503** é uma afirmação sobre o servidor: "não consigo responder agora, volte depois". O
 *   Google mantém o endereço indexado e tenta de novo.
 *
 * Com tudo virando 404, uma instabilidade de dez minutos da API — Redis fora do ar, banco
 * fora do ar, timeout — anunciava o site inteiro como removido. Foi exatamente o que
 * aconteceu em desenvolvimento na sessão 22 (ver README, "Troubleshooting"): o motor do
 * Docker parou, a API passou a responder 500, e a maioria das páginas do site virou 404 sem
 * que nada tivesse sido apagado.
 *
 * A regra: **só o 404 vindo da API vira 404 do site**. Qualquer outra coisa — 500, 502, 429,
 * timeout, conexão recusada, resposta ilegível — vira 503. Na dúvida, 503: errar para o lado
 * do 503 custa uma visita adiada; errar para o lado do 404 custa o endereço no índice.
 *
 * Não é raciocínio novo no projeto: `server/routes/sitemap.xml.ts` já respondia 503 em vez de
 * servir um sitemap truncado com 200, pelo mesmo motivo (sessão 21). O que faltava era aplicar
 * isso às páginas.
 */

/** Erro que o `useAsyncData` guarda: `NuxtError`, com o status da resposta quando houve uma. */
type ErroDeLeitura = { statusCode?: number } | null | undefined

export const STATUS_API_INDISPONIVEL = 503

/**
 * `true` só quando a API afirmou que o conteúdo não existe. Conexão recusada e timeout não
 * chegam aqui com status nenhum (o `createError` do Nuxt os normaliza para 500), e é por isso
 * que a checagem é por igualdade a 404, nunca por "não é 2xx".
 */
export function ehConteudoInexistente(erro: ErroDeLeitura): boolean {
  return erro?.statusCode === 404
}

/**
 * `statusMessage` em inglês, e curto, de propósito — exceção consciente ao "português para o
 * que a pessoa lê". Ela vai para a linha de status do HTTP, que é latin-1: 'Serviço
 * temporariamente indisponível' sai do servidor como "Servio temporariamente indisponvel",
 * com os acentos comidos (medido nesta sessão contra o build de produção), e o h3 ainda avisa
 * que `statusMessage` longa será sanitizada. Ninguém lê essa linha — quem escreve o que a
 * pessoa vê é `app/error.vue`, em português, a partir do `statusCode`.
 */

/**
 * Interrompe a renderização com o status certo. `fatal: true` porque a página não tem o que
 * mostrar sem esse conteúdo — quem renderiza daqui em diante é `app/error.vue`.
 *
 * O `Cache-Control: no-store` do 503 não é posto aqui: quem o aplica é
 * `server/plugins/sem-cache-em-erro.ts`, no gancho que alcança a resposta de erro de verdade.
 */
export function lancarErroDePagina(erro: ErroDeLeitura): never {
  if (ehConteudoInexistente(erro)) {
    throw createError({ statusCode: 404, statusMessage: 'Not Found', fatal: true })
  }

  throw createError({
    statusCode: STATUS_API_INDISPONIVEL,
    statusMessage: 'Service Unavailable',
    fatal: true,
  })
}
