import { expect, test } from '@playwright/test'

import { pix, pixKeyFormatted } from '../../../frontend-site/app/config/institution'

/** CRC16-CCITT (poly 0x1021, init 0xFFFF) — o que o BR Code do Pix exige nos 4 últimos caracteres. */
function crc16(text: string): string {
  let crc = 0xffff

  for (const byte of Buffer.from(text, 'utf8')) {
    crc ^= byte << 8
    for (let i = 0; i < 8; i++) {
      crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff
    }
  }

  return crc.toString(16).toUpperCase().padStart(4, '0')
}

test.describe('pix — configuração institucional', () => {
  test('o payload copia e cola tem CRC16-CCITT válido', () => {
    const body = pix.payload.slice(0, -4)

    expect(body.endsWith('6304')).toBe(true)
    expect(pix.payload.slice(-4)).toBe(crc16(body))
  })

  test('o payload carrega a mesma chave exibida no site', () => {
    expect(pix.payload).toContain(`0014BR.GOV.BCB.PIX01${pix.key.length}${pix.key}`)
    expect(pixKeyFormatted()).toBe('78.614.096/0001-75')
  })
})
