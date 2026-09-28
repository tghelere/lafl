import { createRouter, createWebHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    public?: boolean
  }
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
      meta: { public: true },
    },
    // Alcançável por quem recebe o link de definição de senha fora do sistema (ver
    // App\Actions\Users\GeneratePasswordLink) — fora de /admin de propósito, junto de /login
    // (§4.1 de docs/estrutura-site.md: "fora do menu").
    {
      path: '/definir-senha',
      name: 'set-password',
      component: () => import('@/views/SetPasswordView.vue'),
      meta: { public: true },
    },
    {
      path: '/',
      redirect: { name: 'dashboard' },
    },
    {
      path: '/admin',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
    },
    // Autosserviço sobre a própria conta — fora de /admin porque não é um recurso do mapa de
    // acesso (todo usuário autenticado, qualquer papel, pode trocar a própria senha).
    {
      path: '/conta',
      name: 'account',
      component: () => import('@/views/AccountView.vue'),
    },
    {
      path: '/admin/:resource',
      name: 'submissions.index',
      component: () => import('@/views/SubmissionListView.vue'),
    },
    {
      path: '/admin/:resource/:uuid',
      name: 'submissions.show',
      component: () => import('@/views/SubmissionDetailView.vue'),
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
    },
    {
      path: '/admin/transparencia/novo',
      name: 'transparency.create',
      component: () => import('@/views/TransparencyFormView.vue'),
    },
    {
      path: '/admin/transparencia/:uuid',
      name: 'transparency.edit',
      component: () => import('@/views/TransparencyFormView.vue'),
    },
    // Conteúdo das páginas do site — mesmo raciocínio de transparência: rotas próprias,
    // registradas antes das genéricas só por organização. Sem rota de criar: esta fatia
    // edita o conteúdo das páginas existentes, não cria nem exclui página (ver
    // docs/levantamento-painel.md, itens 3 e 5).
    {
      path: '/admin/paginas',
      name: 'pages.index',
      component: () => import('@/views/ContentPageListView.vue'),
    },
    {
      path: '/admin/paginas/:uuid',
      name: 'pages.edit',
      component: () => import('@/views/ContentPageFormView.vue'),
    },
    // Gestão de usuários — mesmo raciocínio de transparência: rotas próprias, registradas
    // antes das genéricas só por organização.
    {
      path: '/admin/usuarios',
      name: 'users.index',
      component: () => import('@/views/UserListView.vue'),
    },
    {
      path: '/admin/usuarios/novo',
      name: 'users.create',
      component: () => import('@/views/UserFormView.vue'),
    },
    {
      path: '/admin/usuarios/:uuid',
      name: 'users.edit',
      component: () => import('@/views/UserFormView.vue'),
    },
    // Auditoria — só leitura, só super_admin (ver App\Policies\ActivityPolicy). Rota própria,
    // registrada antes das genéricas só por organização.
    {
      path: '/admin/auditoria',
      name: 'audit.index',
      component: () => import('@/views/AuditLogView.vue'),
    },
    // Coringa — precisa ser a última entrada: qualquer URL que não bata com nenhuma rota
    // acima cai aqui em vez de deixar o vue-router não renderizar nada (ver
    // frontend-admin/src/views/NotFoundView.vue).
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
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

export default router
