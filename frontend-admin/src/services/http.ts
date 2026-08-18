import axios, { type AxiosInstance } from 'axios'

import router from '@/router'
import { useAuthStore } from '@/stores/auth'

/**
 * Camada única de acesso HTTP (ver docs/convencoes.md) — nenhum outro lugar do app deve
 * chamar axios/fetch diretamente.
 */
export const httpClient: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

let csrfCookiePromise: Promise<void> | null = null

/**
 * Sanctum em SPA mode exige o cookie de CSRF emitido antes de qualquer requisição que mude
 * estado (ver docs/arquitetura.md, seção Autenticação). A promise é cacheada para não buscar
 * o cookie de novo a cada chamada.
 */
export function ensureCsrfCookie(): Promise<void> {
  csrfCookiePromise ??= httpClient.get('/sanctum/csrf-cookie').then(() => undefined)

  return csrfCookiePromise
}

httpClient.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      const authStore = useAuthStore()
      authStore.clearSession()

      if (router.currentRoute.value.name !== 'login') {
        void router.push({ name: 'login' })
      }
    }

    return Promise.reject(error)
  },
)
