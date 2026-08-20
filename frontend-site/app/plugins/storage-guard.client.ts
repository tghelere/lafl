/**
 * Extensão de privacidade (ou modo estrito de navegador) pode bloquear localStorage/
 * sessionStorage lançando exceção já no ACESSO à propriedade — antes de qualquer método ser
 * chamado. Isso acontece antes da hidratação do Vue, então a exceção não tratada aborta o
 * bootstrap inteiro do client e a página fica com o HTML estático da SSR sem nenhuma
 * interatividade (foi exatamente o que quebrou o menu mobile numa extensão de privacidade do
 * Chrome — ver docs/decisoes/0009-direcao-visual.md).
 *
 * O site não usa localStorage/sessionStorage para nada próprio hoje — esta guarda protege
 * contra código de terceiro ou do próprio Nuxt que assuma que a API existe. `enforce: 'pre'`
 * garante que ela rode antes de qualquer outro plugin.
 */
function createMemoryStorage(): Storage {
  const memory = new Map<string, string>()
  return {
    getItem: (key) => memory.get(key) ?? null,
    setItem: (key, value) => {
      memory.set(key, String(value))
    },
    removeItem: (key) => {
      memory.delete(key)
    },
    clear: () => {
      memory.clear()
    },
    key: (index) => Array.from(memory.keys())[index] ?? null,
    get length() {
      return memory.size
    },
  }
}

function guard(name: 'localStorage' | 'sessionStorage') {
  try {
    const probe = '__storage_probe__'
    window[name].setItem(probe, probe)
    window[name].removeItem(probe)
  } catch {
    try {
      Object.defineProperty(window, name, {
        value: createMemoryStorage(),
        configurable: true,
        writable: true,
      })
    } catch {
      // Se nem redefinir a propriedade for possível, não há mais o que fazer aqui — mas ao
      // menos essa exceção não sobe e não trava o resto do bootstrap do client.
    }
  }
}

export default defineNuxtPlugin({
  name: 'storage-guard',
  enforce: 'pre',
  setup() {
    guard('localStorage')
    guard('sessionStorage')
  },
})
