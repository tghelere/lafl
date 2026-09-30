import { type Locator, type Page, expect } from '@playwright/test'

/**
 * A área editável do RichTextEditor, pelo seu nome acessível.
 *
 * Por `getByLabel` e não por `getByRole('textbox')`: a área do Tiptap é um `contenteditable`
 * com `aria-label` (ver ContentPageFormView.vue e RichTextEditor.vue), e o motor de papéis do
 * Playwright não atribui papel implícito de textbox a um contenteditable — só a campo de
 * formulário ou a quem declara `role` explicitamente. O `aria-label` é o mesmo nome acessível
 * que um leitor de tela anuncia, então a busca continua sendo por texto visível a quem usa.
 */
export function editor(page: Page) {
  return page.getByLabel('Conteúdo da página')
}

/** Substitui todo o conteúdo do editor pelo texto informado, digitando de verdade. */
export async function replaceEditorText(page: Page, text: string): Promise<void> {
  const surface = editor(page)
  await surface.click()
  await page.keyboard.press('ControlOrMeta+a')
  await page.keyboard.type(text)
}

/**
 * Cola HTML no editor, como quem copia de outro editor ou de uma página web.
 *
 * O evento de colar é despachado à mão com um DataTransfer em vez de usar a área de
 * transferência do sistema: o navegador não dá acesso a ela sem permissão explícita, e o que
 * importa aqui é o caminho que o ProseMirror percorre ao receber HTML de fora — que é
 * idêntico nos dois casos.
 */
export async function pasteHtmlIntoEditor(page: Page, html: string): Promise<void> {
  const surface = editor(page)
  await surface.click()
  await page.keyboard.press('ControlOrMeta+a')
  await page.keyboard.press('Delete')

  await surface.evaluate((element, payload) => {
    const transfer = new DataTransfer()
    transfer.setData('text/html', payload)
    element.dispatchEvent(new ClipboardEvent('paste', { clipboardData: transfer, bubbles: true, cancelable: true }))
  }, html)
}

/**
 * Solta um arquivo sobre `alvo`, na borda esquerda da sua primeira linha, como quem arrasta
 * uma foto da pasta do computador para o texto.
 *
 * O arrasto é despachado à mão, como a colagem acima: o Playwright não arrasta arquivo de fora
 * do navegador. O caminho no editor é o mesmo — o ProseMirror recebe `dragover` e `drop` com
 * um DataTransfer que traz o arquivo e as coordenadas de onde ele caiu.
 */
export async function dropFileOnto(target: Locator, file: { name: string; mimeType: string; buffer: Buffer }): Promise<void> {
  const box = await target.boundingBox()

  if (!box) {
    throw new Error('o alvo do arrasto não está visível')
  }

  await target.page().evaluate(
    ({ name, mimeType, base64, x, y }) => {
      const bytes = Uint8Array.from(atob(base64), (char) => char.charCodeAt(0))
      const transfer = new DataTransfer()
      transfer.items.add(new File([bytes], name, { type: mimeType }))
      const element = document.elementFromPoint(x, y)!

      for (const type of ['dragenter', 'dragover', 'drop']) {
        element.dispatchEvent(new DragEvent(type, { dataTransfer: transfer, clientX: x, clientY: y, bubbles: true, cancelable: true }))
      }
    },
    { name: file.name, mimeType: file.mimeType, base64: file.buffer.toString('base64'), x: box.x + 1, y: box.y + Math.min(8, box.height / 2) },
  )
}

export async function saveContentPage(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Salvar' }).click()
  await expect(page.getByText('Página salva. A alteração já está no ar.')).toBeVisible()
}
