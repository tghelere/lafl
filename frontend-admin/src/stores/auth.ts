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

  function clearSession(): void {
    user.value = null
  }

  return { user, isAuthenticated, login, logout, fetchCurrentUser, clearSession }
})
