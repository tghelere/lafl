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
    {
      path: '/',
      redirect: { name: 'dashboard' },
    },
    {
      path: '/admin',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
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
