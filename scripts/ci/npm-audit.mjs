#!/usr/bin/env node
// Roda `npm audit --omit=dev` no diretório atual e falha só para avisos que NÃO estão na
// lista de exceções abaixo. Cada exceção carrega o motivo; sem motivo, não entra.
//
// Uso: node ../scripts/ci/npm-audit.mjs   (a partir de frontend-admin/ ou frontend-site/)
import { spawnSync } from 'node:child_process'

const EXCECOES = {
  // node-forge: verificação de assinatura RSA PKCS#1 v1.5. Última versão publicada (1.4.0)
  // ainda é a vulnerável — não existe correção. Só chega via nuxt → @nuxt/cli → listhen, que é
  // o servidor HTTPS do `nuxt dev`; não está em .output (conferido na sessão 35) e nada no
  // servidor de produção verifica assinatura com ele. A "correção" que o npm sugere
  // (nuxt@3.15.1, --force) é rebaixamento de major. Revisar quando houver node-forge > 1.4.0.
  'GHSA-86w9-cpqp-85rv': 'node-forge sem versão corrigida; só no nuxt dev (listhen), fora do .output',
}

const r = spawnSync('npm', ['audit', '--omit=dev', '--json'], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 })
const relatorio = JSON.parse(r.stdout)

// `via` de cada pacote mistura avisos próprios (objetos com url) e nomes de pacotes
// (strings, propagação). Só os objetos são avisos de verdade.
const avisos = new Map()
for (const [pacote, v] of Object.entries(relatorio.vulnerabilities ?? {})) {
  for (const via of v.via) {
    if (typeof via === 'object') avisos.set(via.url, { pacote, titulo: via.title, severidade: via.severity })
  }
}

let falhou = false
for (const [url, a] of avisos) {
  const id = url.split('/').pop()
  if (id in EXCECOES) {
    console.log(`isento  ${id}  ${a.pacote}: ${EXCECOES[id]}`)
  } else {
    falhou = true
    console.log(`AVISO   ${id}  ${a.pacote} (${a.severidade}): ${a.titulo}`)
  }
}
if (avisos.size === 0) console.log('npm audit: nenhum aviso.')
process.exit(falhou ? 1 : 0)
