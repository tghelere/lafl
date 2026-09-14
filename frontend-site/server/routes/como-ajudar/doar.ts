// Redirect 301 — "Doar" saiu do topo antigo e virou rota própria em /doar, ver
// docs/design/navegacao.md §2. Mesmo padrão de server/routes/como-ajudar/doar-itens.ts.
export default defineEventHandler((event) => {
  return sendRedirect(event, '/doar', 301)
})
