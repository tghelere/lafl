import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { ensureCsrfCookie, httpClient } from '@/services/http'
import type { User } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)

  const isAuthenticated = computed(() => user.value !== null)

  async function login(email: string, password: string): Promise<void> {
    await ensureCsrfCookie()

    const { data } = await httpClient.post<{ data: User }>('/api/v1/auth/login', {
      email,
      password,
    })

    user.value = data.data
  }

  async function logout(): Promise<void> {
    try {
      await httpClient.post('/api/v1/auth/logout')
    } finally {
      clearSession()
    }
  }

  async function fetchCurrentUser(): Promise<void> {
    const { data } = await httpClient.get<{ data: User }>('/api/v1/auth/user')

    user.value = data.data
  }

  /**
   * Definição da senha pela primeira vez, via link de uso único — rota pública, mas o
   * endpoint continua exigindo o cookie de CSRF (o domínio é stateful independente de sessão
   * autenticada), daí o mesmo ensureCsrfCookie() do login.
   */
  async function setPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    await ensureCsrfCookie()

    await httpClient.post('/api/v1/auth/set-password', payload)
  }

  /**
   * Troca da própria senha já autenticado (ver /conta). AuthenticateSession compara o hash de
   * senha guardado na sessão com o hash atual a cada requisição — sem o fix em
   * App\Actions\Auth\ChangeUserPassword, a própria sessão que troca a senha caía junto das
   * demais; aqui o front só chama o endpoint e mantém a sessão como está, sem logout.
   */
  async function changePassword(payload: {
    current_password: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    await httpClient.put('/api/v1/auth/password', payload)
  }

  function clearSession(): void {
    user.value = null
  }

  return {
    user,
    isAuthenticated,
    login,
    logout,
    fetchCurrentUser,
    setPassword,
    changePassword,
    clearSession,
  }
})
