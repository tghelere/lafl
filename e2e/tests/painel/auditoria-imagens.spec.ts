import { expect, test } from '@playwright/test'

import { pageAs, unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { solidPng } from '../../support/fixtures'
import { ROLE_USERS, storageStatePath } from '../../support/users'

/**
 * A aba "Imagens" da Auditoria (sessão 29, item 4): o que alguém da comunicação faz com uma
 * imagem, em outra sessão, aparece para o super_admin com autor, ação, link e detalhe. A
 * exclusão, por fim, deixa a linha sem link e com o nome que a imagem tinha.
 */
test.describe('auditoria de imagens — super_admin', () => {
  test.use({ storageState: storageStatePath('super_admin') })

  test('mostra envio, texto e exclusão feitos por outra pessoa, e filtra por acontecimento', async ({ page, browser }) => {
    const alt = `Mural da entrada ${unique('e2e')}`
    const direcaoApi = await AdminApi.as('direcao')

    // Outra pessoa, outra sessão: a comunicação envia e corrige o texto.
    const comunicacao = await pageAs(browser, 'comunicacao')
    await comunicacao.goto('/admin/imagens/nova')
    await comunicacao.getByLabel('Arquivo (JPEG, PNG ou WebP, até 10 MB)').setInputFiles({
      name: 'mural.png',
      mimeType: 'image/png',
      buffer: solidPng(640, 480, [220, 180, 40]),
    })
    await comunicacao.getByLabel('Texto alternativo').fill(alt)
    await comunicacao.getByRole('radio', { name: 'Não' }).check()
    await comunicacao.getByRole('button', { name: 'Enviar', exact: true }).click()
    await expect(comunicacao.getByText('Imagem enviada.')).toBeVisible()
    const mediaId = new URL(comunicacao.url()).pathname.split('/').at(-1)!

    await comunicacao.getByLabel('Legenda (opcional)').fill('Pintado em 2024')
    await comunicacao.getByRole('button', { name: 'Salvar', exact: true }).click()
    await expect(comunicacao.getByText('Dados da imagem salvos.')).toBeVisible()
    await comunicacao.context().close()

    try {
      await page.goto('/admin/auditoria')
      await page.getByRole('navigation', { name: 'O que auditar' }).getByRole('link', { name: 'Imagens' }).click()
      await expect(page).toHaveURL(/\/admin\/auditoria\/imagens$/)
      await expect(page.getByRole('navigation', { name: 'O que auditar' }).getByRole('link', { name: 'Imagens' })).toHaveAttribute('aria-current', 'page')

      const linhas = page.locator('tbody tr').filter({ hasText: alt })
      await expect(linhas).toHaveCount(2)

      const envio = linhas.filter({ hasText: 'Imagem enviada' })
      await expect(envio.locator('td[data-label="Usuário"]')).toHaveText(ROLE_USERS.comunicacao.name)
      await expect(envio.locator('td[data-label="Detalhe"]')).toHaveText('640 × 480 px')
      await expect(envio.getByRole('link', { name: alt })).toHaveAttribute('href', `/admin/imagens/${mediaId}`)

      await expect(linhas.filter({ hasText: 'Texto alterado' }).locator('td[data-label="Detalhe"]')).toHaveText('Alterado: legenda')

      // Filtro por acontecimento: só o envio fica.
      await page.getByLabel('Acontecimento').selectOption({ label: 'Imagem enviada' })
      await page.getByRole('button', { name: 'Filtrar' }).click()
      await expect(page).toHaveURL(/event=uploaded/)
      await expect(linhas).toHaveCount(1)

      // Exclusão pela direção: a linha nova não tem link, e as anteriores mantêm o nome.
      expect((await direcaoApi.delete(`/api/v1/media/${mediaId}`)).status()).toBe(204)
      await page.getByRole('button', { name: 'Limpar' }).click()
      await expect(linhas.filter({ hasText: 'Imagem excluída' })).toContainText(`${alt} (excluída)`)
      await expect(linhas).toHaveCount(3)
      await expect(linhas.getByRole('link')).toHaveCount(0)
    } finally {
      await direcaoApi.delete(`/api/v1/media/${mediaId}`)
      await direcaoApi.dispose()
    }
  })
})

test.describe('auditoria de imagens — direcao', () => {
  test.use({ storageState: storageStatePath('direcao') })

  test('não entra pela URL', async ({ page }) => {
    await page.goto('/admin/auditoria/imagens')
    await expect(page.getByRole('alert')).toHaveText('Você não tem permissão para acessar este recurso.')
    await expect(page).toHaveURL(/\/admin\/auditoria\/imagens$/)
  })
})
