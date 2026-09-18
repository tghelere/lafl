/**
 * Fonte única da configuração do painel.
 *
 * Duas origens, nesta ordem: o que o servidor escreveu em `public/config.js` (tempo de
 * execução) e, se aquilo estiver vazio, o que o Vite gravou no bundle a partir das `VITE_*`
 * (tempo de build). A primeira é a que vale em servidor; a segunda é a que vale em
 * `npm run dev` e na bateria de ponta a ponta, onde não existe ninguém para reescrever o
 * arquivo. Ver docs/decisoes/0015-painel-configurado-em-tempo-de-execucao.md.
 *
 * Nada de regra de negócio aqui: isto é endereço de servidor e tempo de inatividade, não
 * decisão sobre dado (ver CLAUDE.md, regra 1).
 */
type RuntimeConfig = {
  apiUrl?: string
  siteUrl?: string
  sessionIdleTimeoutMinutes?: string
}

declare global {
  interface Window {
    __LAF_CONFIG__?: RuntimeConfig
  }
}

const runtime: RuntimeConfig = window.__LAF_CONFIG__ ?? {}

function resolve(runtimeValue: string | undefined, buildValue: string | undefined): string {
  return (runtimeValue ?? '').trim() || (buildValue ?? '').trim()
}

/** URL base da API, sem barra final. */
export const apiUrl = resolve(runtime.apiUrl, import.meta.env.VITE_API_URL)

/** URL base do site público, sem barra final — só para o botão "Abrir no site". */
export const siteUrl = resolve(runtime.siteUrl, import.meta.env.VITE_SITE_URL)

/**
 * Minutos de inatividade antes do logout automático. O padrão de 15 é o mesmo de
 * `.env.example`: um painel sem timeout numa tela destravada é acesso a dado de menor de
 * idade por quem passar na frente do computador.
 */
export const sessionIdleTimeoutMinutes = (() => {
  const minutos = Number(resolve(runtime.sessionIdleTimeoutMinutes, import.meta.env.VITE_SESSION_IDLE_TIMEOUT_MINUTES))

  return Number.isFinite(minutos) && minutos > 0 ? minutos : 15
})()
