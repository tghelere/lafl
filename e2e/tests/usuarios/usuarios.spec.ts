import { expect, test } from '@playwright/test'

import { AdminApi } from '../../support/api'
import { acceptNextDialog, breadcrumb, pageInCleanContext, sidebar, sidebarLinkNames, unique } from '../../support/admin'
import { createUser, currentUserId, generatePasswordLink } from '../../support/fixtures'
import { E2E_PASSWORD, ROLE_USERS, STANDALONE_USERS, storageStatePath } from '../../support/users'

const NOVA_SENHA = 'senha-nova-do-teste-e2e'

/**
 * Define a senha pelo link de uso único e entra — o caminho de quem recebe o link por fora
 * (ver App\Actions\Users\GeneratePasswordLink). Roda sempre num contexto de navegador à parte,
 * porque é uma segunda pessoa usando o sistema ao mesmo tempo que o super_admin.
 */
async function definirSenhaEEntrar(page: import('@playwright/test').Page, link: string, email: string): Promise<void> {
  await page.goto(link)

  // Mesma logo da tela de login (ver LoginView.vue) — ficou de fora quando a marca chegou ao
  // painel (sessão 14) e foi corrigido depois (ver relatório desta sessão).
  await expect(page.getByRole('img', { name: 'Lar Anália Franco' })).toBeVisible()

  await page.getByLabel('E-mail').fill(email)
  await page.getByLabel('Nova senha', { exact: true }).fill(NOVA_SENHA)
  await page.getByLabel('Confirmar nova senha').fill(NOVA_SENHA)
  await page.getByRole('button', { name: 'Definir senha' }).click()

  await expect(page.getByText('Senha definida. Entre com a nova senha.')).toBeVisible()

  await page.getByLabel('E-mail').fill(email)
  await page.getByLabel('Senha', { exact: true }).fill(NOVA_SENHA)
  await page.getByRole('button', { name: 'Entrar' }).click()
}

test.describe('super_admin', () => {
  test.use({ storageState: storageStatePath('super_admin') })

  test('cria usuário com dois papéis, gera o link e a pessoa entra vendo o menu dos dois', async ({ page, browser }) => {
    const email = `${unique('novato')}@e2e.local`

    await page.goto('/admin/usuarios/novo')
    await page.getByLabel('Nome').fill('Nara Novata')
    await page.getByLabel('E-mail').fill(email)
    await page.getByRole('checkbox', { name: 'Financeiro' }).check()
    await page.getByRole('checkbox', { name: 'Contraturno' }).check()
    await page.getByRole('button', { name: 'Salvar' }).click()

    await expect(page.getByText('Usuário criado.')).toBeVisible()

    const dialogo = acceptNextDialog(page)
    await page.getByRole('button', { name: 'Gerar link de definição de senha' }).click()
    await dialogo

    await expect(page.getByRole('heading', { name: 'Link de definição de senha' })).toBeVisible()
    const link = (await page.locator('.user-form__link-value').innerText()).trim()
    expect(link).toContain('/definir-senha?token=')

    // Outra pessoa, outro navegador: nenhum cookie do super_admin atravessa para cá (ver
    // pageInCleanContext — herdar o storageState aqui derrubaria a sessão do super_admin).
    const pessoa = await pageInCleanContext(browser)

    await definirSenhaEEntrar(pessoa, link, email)

    await expect(pessoa.getByRole('heading', { name: 'Início' })).toBeVisible()
    await expect.poll(() => sidebarLinkNames(pessoa)).toEqual([
      'Pendências',
      'Avisos do contraturno',
      'Propostas de apoio',
      'Documentos',
    ])

    await pessoa.context().close()
  })

  test('desativar a conta de quem está logado leva essa pessoa ao login, sem tela quebrada', async ({ page, browser }) => {
    const email = `${unique('efemero')}@e2e.local`
    const api = await AdminApi.as('super_admin')
    const usuario = await createUser(api, { name: 'Elias Efêmero', email, roles: ['atendimento'] })
    const link = await generatePasswordLink(api, usuario.id)
    await api.dispose()

    const pessoa = await pageInCleanContext(browser)
    await definirSenhaEEntrar(pessoa, link, email)
    await expect(pessoa.getByRole('heading', { name: 'Início' })).toBeVisible()

    // Primeiro contexto: o super_admin desativa a conta enquanto a sessão dela está aberta.
    await page.goto(`/admin/usuarios/${usuario.id}`)
    const dialogo = acceptNextDialog(page)
    await page.getByRole('button', { name: 'Desativar' }).click()
    await dialogo
    await expect(page.getByText('Inativo')).toBeVisible()

    // Segundo contexto: a sessão não cai sozinha (o cookie continua válido) — ela cai na
    // próxima requisição, barrada por App\Http\Middleware\EnsureUserIsActive. O que se
    // verifica aqui é que essa queda é limpa: tela de login de verdade, não erro nem página
    // em branco.
    await sidebar(pessoa).getByRole('link', { name: 'Voluntários' }).click()

    await expect(pessoa).toHaveURL(/\/login$/)
    await expect(pessoa.getByRole('button', { name: 'Entrar' })).toBeVisible()
    await expect(pessoa.getByLabel('E-mail')).toBeVisible()
    await expect(pessoa.getByRole('heading', { name: 'Painel administrativo' })).toBeVisible()

    await pessoa.context().close()
  })

  test('tentar desativar a própria conta mostra a mensagem da API', async ({ page }) => {
    const api = await AdminApi.as('super_admin')
    const meuId = await currentUserId(api)
    await api.dispose()

    await page.goto(`/admin/usuarios/${meuId}`)
    await expect(page.getByLabel('E-mail')).toHaveValue(ROLE_USERS.super_admin.email)

    const dialogo = acceptNextDialog(page)
    await page.getByRole('button', { name: 'Desativar' }).click()
    await dialogo

    // Texto da API (App\Actions\Users\DeactivateUser), repassado sem reescrita pelo painel.
    await expect(page.getByText('Não é possível desativar a própria conta.')).toBeVisible()
    await expect(page.getByText('Ativo', { exact: true })).toBeVisible()
  })

  test('criar e em seguida editar outro usuário, sem recarregar, mostra os dados certos', async ({ page }) => {
    const email = `${unique('recem-criado')}@e2e.local`

    await page.goto('/admin/usuarios/novo')
    await page.getByLabel('Nome').fill('Rita Recém-criada')
    await page.getByLabel('E-mail').fill(email)
    await page.getByRole('checkbox', { name: 'Bazar' }).check()
    await page.getByRole('button', { name: 'Salvar' }).click()

    // Salvar na criação leva à tela de edição pela MESMA instância de componente (o
    // vue-router não remonta quando só o parâmetro da rota muda) — sem o watch em
    // route.fullPath de UserFormView.vue, o registro carregado ficaria do formulário anterior.
    await expect(page).toHaveURL(/\/admin\/usuarios\/[0-9a-f-]{36}/)
    await expect(page.getByRole('heading', { name: 'Editar usuário' })).toBeVisible()
    await expect(page.getByLabel('Nome')).toHaveValue('Rita Recém-criada')
    await expect(page.getByLabel('E-mail')).toHaveValue(email)
    await expect(page.getByText('Ativo', { exact: true })).toBeVisible()

    // E agora de uma edição direto para outra, ainda sem recarregar a página.
    await breadcrumb(page).getByRole('link', { name: 'Usuários' }).click()
    await page.getByLabel('Nome ou e-mail').fill(ROLE_USERS.bazar.email)
    await page.getByRole('button', { name: 'Filtrar' }).click()
    await page.getByRole('link', { name: ROLE_USERS.bazar.name }).click()

    await expect(page.getByLabel('Nome')).toHaveValue(ROLE_USERS.bazar.name)
    await expect(page.getByLabel('E-mail')).toHaveValue(ROLE_USERS.bazar.email)
    await expect(page.getByRole('checkbox', { name: 'Bazar' })).toBeChecked()
    await expect(page.getByRole('checkbox', { name: 'Financeiro' })).not.toBeChecked()
  })
})

test.describe('conta própria', () => {
  // Contexto limpo e conta exclusiva: trocar a senha invalida as outras sessões daquela conta
  // (Sanctum\AuthenticateSession), então isto não pode acontecer com um usuário cujo
  // storageState os outros testes reaproveitam.
  test.use({ storageState: { cookies: [], origins: [] } })

  test('trocar a própria senha em /conta mantém a sessão depois de recarregar', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel('E-mail').fill(STANDALONE_USERS.trocaDeSenha.email)
    await page.getByLabel('Senha', { exact: true }).fill(E2E_PASSWORD)
    await page.getByRole('button', { name: 'Entrar' }).click()
    await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible()

    await page.goto('/conta')
    await page.getByLabel('Senha atual').fill(E2E_PASSWORD)
    await page.getByLabel('Nova senha', { exact: true }).fill(NOVA_SENHA)
    await page.getByLabel('Confirmar nova senha').fill(NOVA_SENHA)
    await page.getByRole('button', { name: 'Trocar senha' }).click()

    await expect(page.getByText('Senha alterada.')).toBeVisible()

    // O ponto do teste: a sessão que trocou a senha continua de pé. Recarregar força o
    // painel a pedir /auth/user de novo — é ali que uma sessão derrubada apareceria.
    await page.reload()

    await expect(page.getByRole('heading', { name: 'Minha conta' })).toBeVisible()
    await expect(page.getByText(STANDALONE_USERS.trocaDeSenha.email)).toBeVisible()
    await expect(page).toHaveURL(/\/conta$/)
  })
})
