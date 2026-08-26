// Redirect 301, decidido em docs/estrutura-site.md §1.2. Ficou pendente na sessão anterior
// porque o destino (formulário de agendamento de coleta) ainda não existia — agora existe.
export default defineEventHandler((event) => {
  return sendRedirect(event, '/bazar/agendar-coleta', 301)
})
