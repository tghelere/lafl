import { type Page, expect } from '@playwright/test'

import { solidPng } from './fixtures'

export type Destination = 'gallery' | 'cover'

const DESTINATION_LABEL: Record<Destination, string> = {
  gallery: 'Fim da galeria',
  cover: 'Capa, no lugar da atual',
}

/** A seção "Imagens desta página" da tela de edição. */
export function pageImagesSection(page: Page) {
  return page.locator('section.page-images')
}

/**
 * "Adicionar foto" de "Imagens desta página": abre o diálogo, escolhe o arquivo, descreve,
 * responde "Não" à declaração, escolhe o destino e envia. Espera o diálogo fechar.
 */
export async function addPhotoFromSection(
  page: Page,
  photo: { alt: string; caption?: string; destination?: Destination; color: [number, number, number]; width?: number; height?: number },
): Promise<void> {
  await pageImagesSection(page).getByRole('button', { name: 'Adicionar foto' }).click()
  const dialog = page.getByRole('dialog', { name: 'Adicionar foto a esta página' })

  await dialog.getByLabel('Escolher foto do computador').setInputFiles({
    name: 'foto.png',
    mimeType: 'image/png',
    buffer: solidPng(photo.width ?? 800, photo.height ?? 500, photo.color),
  })
  await dialog.getByLabel('Descrição da foto').fill(photo.alt)

  if (photo.caption) {
    await dialog.getByLabel('Legenda (opcional)').fill(photo.caption)
  }

  await dialog.getByRole('radio', { name: 'Não' }).check()
  await dialog.getByRole('radio', { name: DESTINATION_LABEL[photo.destination ?? 'gallery'] }).check()
  await dialog.getByRole('button', { name: /^Enviar para a/ }).click()
  await expect(dialog).toBeHidden()
}
