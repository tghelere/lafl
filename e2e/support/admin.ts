import { type Browser, type Page, expect } from '@playwright/test'

import { E2E_PASSWORD, type RoleKey, storageStatePath } from './users'

/**
 * Passa pela tela de login de verdade. Só os testes que são SOBRE login usam isto — os demais
 * entram pelo storageState gravado no global setup, para não gastar o rate limit de 5
 * tentativas por minuto por IP+e-mail (ver App\Providers\AppServiceProvider).
 */
export async function loginThroughForm(page: Page, email: string, password = E2E_PASSWORD): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('E-mail').fill(email)
  await page.getByLabel('Senha', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Entrar' }).click()
}

/** A navegação lateral do painel, pelo seu nome acessível (ver AppSidebar.vue). */
export function sidebar(page: Page) {
  return page.getByRole('navigation', { name: 'Navegação principal' })
}

/**
 * Os rótulos dos itens da navegação lateral, sem o contador de não lidos que alguns deles
 * carregam (ver AppSidebar.vue). Remove o badge de uma cópia do nó em vez de recortar o texto
 * com expressão regular — assim o teste não passa a depender do formato do contador.
 *
 * Leitura instantânea, sem espera embutida: o painel é uma SPA e o menu só existe depois de
 * `/auth/user` responder. Use sempre dentro de `expect.poll(...)`, nunca solto — uma chamada
 * direta pode pegar a tela antes de o menu montar e devolver lista vazia.
 */
export function sidebarLinkNames(page: Page): Promise<string[]> {
  return sidebar(page)
    .getByRole('link')
    .evaluateAll((nodes) =>
      nodes.map((node) => {
        const copy = node.cloneNode(true) as HTMLElement
        copy.querySelector('.app-sidebar__badge')?.remove()

        return (copy.textContent ?? '').trim()
      }),
    )
}

/**
 * A trilha de navegação da tela atual. Existe como localizador próprio porque vários nomes se
 * repetem entre ela e o menu lateral ("Usuários", "Páginas", "Transparência") — clicar sem
 * dizer em qual das duas dá conflito de seletor.
 */
export function breadcrumb(page: Page) {
  return page.getByRole('navigation', { name: 'Trilha de navegação' })
}

/** O aviso que substitui os cards do Início para quem não enxerga nenhum formulário. */
export const DASHBOARD_EMPTY_NOTICE = 'Não há formulários para o seu perfil no momento.'

/**
 * Títulos dos cards do Início, na ordem em que a tela mostra. Lista vazia quer dizer "nenhum
 * formulário recebido é do seu perfil" — é o caso de quem só tem `comunicacao` ou só
 * `financeiro`, e a tela troca os cards pelo aviso acima.
 *
 * Cada card tem três linhas — contagem, título e o qualificador "não lidos" (ver
 * DashboardView.vue) —, e o que interessa aqui é a do meio.
 */
export async function dashboardCardTitles(page: Page): Promise<string[]> {
  await page.goto('/admin')
  await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

  const cards = page.getByRole('main').getByRole('link')

  // Espera a lista estabilizar: ou há cards, ou apareceu o aviso de "nenhum formulário".
  await expect
    .poll(async () => (await cards.count()) > 0 || (await page.getByText(DASHBOARD_EMPTY_NOTICE).isVisible()))
    .toBe(true)

  return cards.evaluateAll((nodes) =>
    nodes.map((node) => node.querySelector('.summary-card__label')?.textContent?.trim() ?? ''),
  )
}

/**
 * Abre uma aba de navegador SEM sessão nenhuma — a "segunda pessoa" de um teste que precisa de
 * duas pessoas usando o sistema ao mesmo tempo.
 *
 * O `storageState` vazio é obrigatório, não decorativo: dentro de um teste, o
 * `browser.newContext()` do @playwright/test herda as opções de contexto declaradas no
 * `test.use()` — inclusive o storageState. Sem passar um explicitamente, o "segundo
 * navegador" nasce com o MESMO cookie de sessão do primeiro; quando essa segunda pessoa faz
 * login, o Laravel migra a sessão (SessionGuard::updateSession destrói o id antigo) e derruba
 * a sessão do primeiro contexto. O sintoma é o primeiro contexto cair para a tela de login em
 * uma ação qualquer, longe da causa.
 */
export async function pageInCleanContext(browser: Browser): Promise<Page> {
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } })

  return context.newPage()
}

/** Abre uma aba de navegador já autenticada como o papel pedido. */
export async function pageAs(browser: Browser, role: RoleKey): Promise<Page> {
  const context = await browser.newContext({ storageState: storageStatePath(role) })

  return context.newPage()
}

/**
 * Abre uma página do site pelo título, passando pelo filtro da listagem. Pelo filtro, e não
 * clicando direto na tabela, porque a listagem é paginada por `updated_at` — qualquer edição
 * feita por outro teste reordena a primeira página, e um teste não pode depender disso.
 */
export async function openContentPageByTitle(page: Page, title: string): Promise<void> {
  await page.goto('/admin/paginas')
  await page.getByLabel('Título').fill(title)
  await page.getByRole('button', { name: 'Filtrar' }).click()
  await page.getByRole('link', { name: title, exact: true }).click()
  await expect(page.getByRole('heading', { name: title })).toBeVisible()
}

/**
 * Aceita o próximo `window.confirm` da tela. O painel usa confirmação nativa nas ações
 * destrutivas (desativar usuário, gerar novo link, excluir documento) — sem isto o Playwright
 * as recusa por padrão e a ação simplesmente não acontece.
 */
export function acceptNextDialog(page: Page): Promise<string> {
  return new Promise((resolve) => {
    page.once('dialog', (dialog) => {
      const message = dialog.message()
      void dialog.accept().then(() => resolve(message))
    })
  })
}

/** Sufixo único por execução, para que um teste que cria registro possa rodar de novo. */
export function unique(prefix: string): string {
  return `${prefix}-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`
}
