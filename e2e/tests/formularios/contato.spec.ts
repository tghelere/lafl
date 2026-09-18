import { expect, test } from '@playwright/test'

import { unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { gotoSite } from '../../support/site'

/**
 * Cobre o caminho que a pessoa percorre para mandar uma mensagem pelo formulário de contato
 * (docs/estrutura-site.md, Parte 2): preencher e enviar pela interface, sem JavaScript — o
 * <form method="post"> vai direto para server/api/forms/[tipo].post.ts, o proxy que chama a
 * API e redireciona para /obrigado/contato. Esse proxy é o que devolvia 500 em vez de
 * redirecionar em requisições malformadas (ver relatório desta sessão); este teste garante o
 * caminho feliz pela interface, e a mensagem sendo lida pelo painel confirma que o proxy
 * chegou a chamar a API de verdade, não só a exibir a tela de confirmação.
 */
test.describe('site — formulário de contato', () => {
  test('envio pela interface chega ao painel', async ({ page }) => {
    const subject = unique('Assunto e2e')

    await gotoSite(page, '/contato')

    await page.getByLabel('Seu nome').fill('Marina Visitante')
    await page.getByLabel('E-mail').fill('marina.visitante@e2e.local')
    await page.getByLabel('Assunto').fill(subject)
    await page.getByLabel('Mensagem').fill('Mensagem enviada pela bateria de e2e.')
    await page.getByRole('checkbox').check()

    await page.getByRole('button', { name: 'Enviar mensagem' }).click()

    await expect(page).toHaveURL(/\/obrigado\/contato$/)
    await expect(page.getByRole('heading', { name: 'Mensagem recebida' })).toBeVisible()

    // Asserção do lado do servidor: quem lê mensagem de contato é `atendimento`, além de
    // `direcao` (ver App\Policies\ContactMessagePolicy). A listagem ordena por created_at
    // decrescente, então a mensagem recém-enviada é sempre a primeira da primeira página.
    const api = await AdminApi.as('atendimento')

    try {
      const response = await api.get('/api/v1/contact-messages', { per_page: 1 })

      expect(response.ok(), `GET /api/v1/contact-messages respondeu ${response.status()}`).toBe(true)

      const body = (await response.json()) as { data: Array<{ subject: string }> }

      expect(body.data[0]?.subject).toBe(subject)
    } finally {
      await api.dispose()
    }
  })
})
