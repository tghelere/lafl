import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { fetchDashboardSummary } from '@/services/dashboard'
import type { DashboardEntry } from '@/types/submission'

/**
 * Contagem de formulários não lidos por recurso — a mesma para os cards da tela Início e para os
 * contadores da navegação lateral (ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md).
 *
 * Uma store, e não uma chamada em cada componente, porque a navegação lateral está em TODA tela:
 * duas fontes independentes acabariam mostrando números diferentes na mesma tela — o card
 * dizendo 3 e o menu dizendo 4 — que é o pior resultado possível para um contador.
 *
 * Quem lê ou desmarca um registro chama `refresh()`; o número desce (ou sobe) sem recarregar a
 * página. A contagem vem sempre da API: o painel nunca soma nem subtrai localmente, porque
 * outra pessoa da equipe pode ter lido o mesmo registro no mesmo minuto — a leitura é
 * compartilhada.
 */
export const useUnreadCountsStore = defineStore('unreadCounts', () => {
  const entries = ref<DashboardEntry[]>([])
  const isLoading = ref(false)
  const hasLoaded = ref(false)
  const errorMessage = ref<string | null>(null)

  /** Contagem por slug de recurso (ex.: "contact-messages"), para o menu consultar direto. */
  const byResource = computed<Record<string, number>>(() =>
    Object.fromEntries(entries.value.map((entry) => [entry.resource, entry.unread])),
  )

  async function refresh(): Promise<void> {
    isLoading.value = true
    errorMessage.value = null

    try {
      entries.value = await fetchDashboardSummary()
      hasLoaded.value = true
    } catch {
      errorMessage.value = 'Não foi possível carregar a contagem de não lidos.'
    } finally {
      isLoading.value = false
    }
  }

  /** Para a navegação lateral, que monta em toda tela e não deve refazer a chamada em cada uma. */
  async function ensureLoaded(): Promise<void> {
    if (hasLoaded.value || isLoading.value) {
      return
    }

    await refresh()
  }

  function clear(): void {
    entries.value = []
    hasLoaded.value = false
    errorMessage.value = null
  }

  return { entries, byResource, isLoading, hasLoaded, errorMessage, refresh, ensureLoaded, clear }
})
