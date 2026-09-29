/**
 * `/midia/{uuid}`, a forma canônica gravada no conteúdo — a API devolve a largura padrão. Na
 * prática o site recebe o `src` já com largura (ver App\Actions\Media\ExpandContentImages);
 * esta rota existe para que o endereço canônico também responda. Ver
 * server/utils/proxyMedia.ts.
 */
export default defineEventHandler(async (event) => {
  const { uuid } = getRouterParams(event)

  return proxyMedia(event, uuid)
})
