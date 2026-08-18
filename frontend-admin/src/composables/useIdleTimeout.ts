import { onMounted, onUnmounted } from 'vue'

const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'] as const

/**
 * Desloga automaticamente após N minutos sem interação (ver docs/convencoes.md, "Painel
 * admin"). Só decide QUANDO deslogar — quem efetivamente encerra a sessão é sempre a API.
 */
export function useIdleTimeout(timeoutMinutes: number, onTimeout: () => void): void {
  let timer: ReturnType<typeof setTimeout> | undefined

  function resetTimer(): void {
    if (timer !== undefined) {
      clearTimeout(timer)
    }

    timer = setTimeout(onTimeout, timeoutMinutes * 60 * 1000)
  }

  onMounted(() => {
    for (const event of ACTIVITY_EVENTS) {
      window.addEventListener(event, resetTimer)
    }

    resetTimer()
  })

  onUnmounted(() => {
    for (const event of ACTIVITY_EVENTS) {
      window.removeEventListener(event, resetTimer)
    }

    if (timer !== undefined) {
      clearTimeout(timer)
    }
  })
}
