/**
 * `/midia/{uuid}/{largura}.webp` — ver server/utils/proxyMedia.ts.
 *
 * Sem sufixo de método (`.get.ts`) pelo mesmo motivo do PDF: HEAD também precisa responder.
 */
export default defineEventHandler(async (event) => {
  const { uuid, variant } = getRouterParams(event)

  return proxyMedia(event, uuid, variant)
})
