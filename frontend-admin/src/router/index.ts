import { createRouter, createWebHistory } from 'vue-router'
import type { RouteParams } from 'vue-router'

import { SUBMISSION_RESOURCES } from '@/config/submissionResources'
import { SECTION, resolveRouteMetaText } from '@/router/meta'
import { useAuthStore } from '@/stores/auth'

/** Sufixo fixo do título de toda aba do painel. */
const TITLE_SUFFIX = 'Painel LAF'

const NOT_FOUND_TITLE = 'Página não encontrada'

/**
 * Título da listagem/detalhe de formulário: vem da mesma config que a tela e a navegação
 * lateral usam, nunca de um segundo mapa mantido à mão. Slug desconhecido (URL digitada
 * errada, ex.: /admin/recurso-que-nao-existe) cai no mesmo nome da rota coringa porque é
 * isso que a tela mostra — AppLayout troca o conteúdo por NotFoundState.
 */
function submissionTitle(route: { params: RouteParams }, suffix = ''): string {
  const config = SUBMISSION_RESOURCES[String(route.params.resource)]

  return config ? `${config.title}${suffix}` : NOT_FOUND_TITLE
}

/**
 * A seção de um formulário é o próprio slug do recurso — é ele que identifica o item na
 * navegação lateral, e as duas telas (listagem e detalhe) pertencem ao mesmo item.
 */
function submissionSection(route: { params: RouteParams }): string {
  return String(route.params.resource)
}

// Rotas de autenticação ficam fora de /admin (ver docs/estrutura-site.md §4.1: "fora do
// menu"); tudo que aparece na navegação lateral (Início, Atendimento, Bazar — ver §4.2) vive
// sob /admin (§4.3: "Rotas no padrão /admin/{recurso}").
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { public: true, title: 'Entrar' },
    },
    // Alcançável por quem recebe o link de definição de senha fora do sistema (ver
    // App\Actions\Users\GeneratePasswordLink) — fora de /admin de propósito, junto de /login
    // (§4.1 de docs/estrutura-site.md: "fora do menu").
    {
      path: '/definir-senha',
      name: 'set-password',
      component: () => import('@/views/SetPasswordView.vue'),
      meta: { public: true, title: 'Definir senha' },
    },
    {
      path: '/',
      redirect: { name: 'dashboard' },
    },
    {
      path: '/admin',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
      meta: { title: 'Início', section: SECTION.dashboard },
    },
    // Autosserviço sobre a própria conta — fora de /admin porque não é um recurso do mapa de
    // acesso (todo usuário autenticado, qualquer papel, pode trocar a própria senha).
    {
      path: '/conta',
      name: 'account',
      component: () => import('@/views/AccountView.vue'),
      meta: { title: 'Minha conta' },
    },
    {
      path: '/admin/:resource',
      name: 'submissions.index',
      component: () => import('@/views/SubmissionListView.vue'),
      meta: { title: (route) => submissionTitle(route), section: submissionSection },
    },
    {
      path: '/admin/:resource/:uuid',
      name: 'submissions.show',
      component: () => import('@/views/SubmissionDetailView.vue'),
      meta: { title: (route) => submissionTitle(route, ' — detalhe'), section: submissionSection },
    },
    // Transparência não segue o padrão genérico dos cinco formulários (§4.5) — é CRUD
    // completo, não só leitura + status —, então tem rotas e telas próprias, registradas
    // antes das genéricas acima só por organização (a especificidade estática de
    // "/admin/transparencia" já vence "/admin/:resource" na ordenação do vue-router,
    // independente de posição).
    {
      path: '/admin/transparencia',
      name: 'transparency.index',
      component: () => import('@/views/TransparencyListView.vue'),
      meta: { title: 'Transparência', section: SECTION.transparency },
    },
    {
      path: '/admin/transparencia/novo',
      name: 'transparency.create',
      component: () => import('@/views/TransparencyFormView.vue'),
      meta: { title: 'Novo documento', section: SECTION.transparency },
    },
    {
      path: '/admin/transparencia/:uuid',
      name: 'transparency.edit',
      component: () => import('@/views/TransparencyFormView.vue'),
      meta: { title: 'Editar documento', section: SECTION.transparency },
    },
    // Conteúdo das páginas do site — mesmo raciocínio de transparência: rotas próprias,
    // registradas antes das genéricas só por organização. Sem rota de criar: esta fatia
    // edita o conteúdo das páginas existentes, não cria nem exclui página (ver
    // docs/levantamento-painel.md, itens 3 e 5).
    {
      path: '/admin/paginas',
      name: 'pages.index',
      component: () => import('@/views/ContentPageListView.vue'),
      meta: { title: 'Páginas', section: SECTION.pages },
    },
    {
      path: '/admin/paginas/:uuid',
      name: 'pages.edit',
      component: () => import('@/views/ContentPageFormView.vue'),
      meta: { title: 'Editar página', section: SECTION.pages },
    },
    // Biblioteca de imagens do conteúdo — mesmo raciocínio de transparência: rotas próprias,
    // registradas antes das genéricas só por organização.
    {
      path: '/admin/imagens',
      name: 'media.index',
      component: () => import('@/views/MediaListView.vue'),
      meta: { title: 'Imagens', section: SECTION.media },
    },
    {
      path: '/admin/imagens/nova',
      name: 'media.create',
      component: () => import('@/views/MediaUploadView.vue'),
      meta: { title: 'Enviar imagem', section: SECTION.media },
    },
    {
      path: '/admin/imagens/:uuid',
      name: 'media.edit',
      component: () => import('@/views/MediaDetailView.vue'),
      meta: { title: 'Editar imagem', section: SECTION.media },
    },
    // Gestão de usuários — mesmo raciocínio de transparência: rotas próprias, registradas
    // antes das genéricas só por organização.
    {
      path: '/admin/usuarios',
      name: 'users.index',
      component: () => import('@/views/UserListView.vue'),
      meta: { title: 'Usuários', section: SECTION.users },
    },
    {
      path: '/admin/usuarios/novo',
      name: 'users.create',
      component: () => import('@/views/UserFormView.vue'),
      meta: { title: 'Novo usuário', section: SECTION.users },
    },
    {
      path: '/admin/usuarios/:uuid',
      name: 'users.edit',
      component: () => import('@/views/UserFormView.vue'),
      meta: { title: 'Editar usuário', section: SECTION.users },
    },
    // Auditoria — só leitura, só super_admin (ver App\Policies\ActivityPolicy). Rota própria,
    // registrada antes das genéricas só por organização.
    {
      path: '/admin/auditoria',
      name: 'audit.index',
      component: () => import('@/views/AuditLogView.vue'),
      meta: { title: 'Auditoria', section: SECTION.audit },
    },
    // Coringa — precisa ser a última entrada: qualquer URL que não bata com nenhuma rota
    // acima cai aqui em vez de deixar o vue-router não renderizar nada (ver
    // frontend-admin/src/views/NotFoundView.vue).
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
      meta: { title: NOT_FOUND_TITLE },
    },
  ],
})

router.beforeEach(async (to) => {
  if (to.meta.public) {
    return true
  }

  const authStore = useAuthStore()

  if (!authStore.isAuthenticated) {
    try {
      await authStore.fetchCurrentUser()
    } catch {
      return { name: 'login' }
    }
  }

  return true
})

/**
 * Título da aba. `afterEach`, não `beforeEach`: o título só deve mudar depois que a navegação
 * foi confirmada — num `beforeEach` que acaba redirecionado (sessão expirada indo para
 * /login) a aba ficaria com o nome de uma tela que não abriu.
 */
router.afterEach((to) => {
  const screen = resolveRouteMetaText(to.meta.title, to)

  document.title = screen ? `${screen} · ${TITLE_SUFFIX}` : TITLE_SUFFIX
})

export default router
