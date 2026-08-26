declare global {
  interface Window {
    umami?: { track: (eventName: string) => void }
  }
}

/**
 * Evento Umami em envio bem-sucedido (ver docs/estrutura-site.md §2.3). Disparado a partir de
 * /obrigado/[tipo].vue, não do próprio formulário — o redirect de sucesso já funciona 100% sem
 * JavaScript (ver server/api/forms/[tipo].post.ts); só a métrica em si é progressive
 * enhancement, então tudo bem se o script do Umami ainda não estiver configurado
 * (NUXT_PUBLIC_UMAMI_WEBSITE_ID vazio, ver app/plugins/umami.client.ts) ou o visitante não
 * tiver JS.
 */
export function useUmamiTrack() {
  function track(eventName: string): void {
    if (import.meta.client) {
      window.umami?.track(eventName)
    }
  }

  return { track }
}
