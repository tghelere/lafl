import { expect, test } from '@playwright/test'

import { unique } from '../../support/admin'
import { AdminApi } from '../../support/api'
import { gotoSite } from '../../support/site'

/**
 * A versão da política que a página mostra e a que o banco grava em `consent_terms_version`
 * são o mesmo fato em dois repositórios de verdade diferentes: uma constante em
 * `frontend-site/app/pages/politica-de-privacidade.vue` e a variável
 * `FORM_CONSENT_TERMS_VERSION` lida por `backend/config/forms.php`. Nada no código impede que
 * uma mude sem a outra, e o sintoma da divergência é silencioso — o site continua no ar, o
 * formulário continua enviando, e o banco passa a guardar o número de uma versão que ninguém
 * leu. Como prova de consentimento, isso é pior do que não guardar nada.
 *
 * Só um teste de ponta a ponta alcança isso: o valor de um lado vem do HTML renderizado e o
 * do outro, do registro gravado depois de um envio real pela interface.
 */
test.describe('site — política de privacidade', () => {
  test('a versão publicada é a mesma que o envio grava no banco', async ({ page }) => {
    await gotoSite(page, '/politica-de-privacidade')

    const versaoPublicada = (await page.getByText(/^Versão /).innerText()).replace(/^Versão\s+/, '')

    expect(versaoPublicada, 'a página precisa exibir a versão do texto').toMatch(/^\d{4}-\d{2}-\d{2}$/)

    // Envio de verdade pela interface, e não pela API: é o caminho que grava o consentimento.
    const subject = unique('Versão da política e2e')

    await gotoSite(page, '/contato')

    await page.getByLabel('Seu nome').fill('Vera Versão')
    await page.getByLabel('E-mail').fill('vera.versao@e2e.local')
    await page.getByLabel('Assunto').fill(subject)
    await page.getByLabel('Mensagem').fill('Mensagem da bateria de e2e.')
    await page.getByRole('checkbox').check()

    await page.getByRole('button', { name: 'Enviar mensagem' }).click()

    await expect(page).toHaveURL(/\/obrigado\/contato$/)

    const api = await AdminApi.as('atendimento')

    try {
      // `consent_terms_version` só existe no detalhe (ContactMessageResource), não na
      // listagem — daí os dois passos.
      const listagem = await api.get('/api/v1/contact-messages', { per_page: 1 })

      expect(listagem.ok(), `GET /api/v1/contact-messages respondeu ${listagem.status()}`).toBe(true)

      const { data } = (await listagem.json()) as { data: Array<{ uuid: string; subject: string }> }

      expect(data[0]?.subject).toBe(subject)

      const detalhe = await api.get(`/api/v1/contact-messages/${data[0]!.uuid}`)

      expect(detalhe.ok(), `GET do detalhe respondeu ${detalhe.status()}`).toBe(true)

      const { data: mensagem } = (await detalhe.json()) as { data: { consent_terms_version: string } }

      expect(
        mensagem.consent_terms_version,
        'a política publicada e o FORM_CONSENT_TERMS_VERSION do backend divergiram — ver '
        + 'docs/roadmap.md, "Política de privacidade — pendências que sobreviveram à reescrita"',
      ).toBe(versaoPublicada)
    } finally {
      await api.dispose()
    }
  })
})
