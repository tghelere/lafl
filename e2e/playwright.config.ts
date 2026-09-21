import { fileURLToPath } from 'node:url'

import { defineConfig, devices } from '@playwright/test'

import {
  ADMIN_URL,
  API_URL,
  API_URL_FORA_DO_AR,
  BIND_HOST,
  SITE_SEM_API_URL,
  SITE_URL,
  portOf,
} from './support/env'

const BACKEND_DIR = fileURLToPath(new URL('../backend/', import.meta.url))
const ADMIN_DIR = fileURLToPath(new URL('../frontend-admin/', import.meta.url))
const SITE_DIR = fileURLToPath(new URL('../frontend-site/', import.meta.url))

/**
 * Bateria de ponta a ponta contra a pilha real — backend Laravel, painel Vue e site Nuxt em
 * SSR, todos em modo de produção (build + servidor), nunca `dev`. Os defeitos que o projeto
 * acumulou até aqui foram todos de integração (busca contra o banco de verdade, comportamento
 * do editor, colisão de CSS, reuso de componente no vue-router) — nenhum deles apareceria num
 * teste de unidade de componente com a API simulada.
 *
 * Firefox é o único navegador: é o que o README já recomenda para conferência visual nesta
 * máquina, e uma bateria pequena rodando em um navegador de verdade vale mais que a mesma
 * bateria repetida em três.
 */
export default defineConfig({
  testDir: './tests',
  globalSetup: './global-setup.ts',
  outputDir: './test-results',

  // Um worker só, de propósito. O backend sob teste é o servidor embutido do PHP (`artisan
  // serve`), e os testes compartilham um banco só; paralelizar aqui trocaria tempo de relógio
  // por falha intermitente, que é exatamente o que uma bateria de e2e não pode ter.
  fullyParallel: false,
  workers: 1,

  // Nada de `.only` esquecido chegando ao CI.
  forbidOnly: !!process.env.CI,

  // No máximo uma repetição, e só no CI. Repetição não "conserta" teste instável: o relatório
  // marca como flaky quem só passou na segunda vez, e reporters/flaky-reporter.ts imprime
  // esses casos em separado no fim da execução para que ninguém precise caçá-los no HTML.
  retries: process.env.CI ? 1 : 0,

  timeout: 45_000,
  expect: { timeout: 10_000 },

  reporter: [
    ['list'],
    ['./reporters/flaky-reporter.ts'],
    ['html', { open: 'never', outputFolder: './playwright-report' }],
  ],

  use: {
    baseURL: ADMIN_URL,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    // Artefatos só do que falhou: trace, captura e vídeo são o que torna uma falha no CI
    // investigável sem reproduzir na máquina de alguém.
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
      // tests/site-sem-api roda só no projeto abaixo — ficam dentro de ./tests por
      // conveniência (um testDir só), mas exercitam uma terceira instância do site com a API
      // fora do ar, não a normal. Rodar aqui também seria a mesma bateria em dobro.
      testIgnore: '**/site-sem-api/**',
    },
    {
      name: 'site-sem-api',
      testDir: './tests/site-sem-api',
      use: { ...devices['Desktop Firefox'] },
    },
  ],

  // O runner sobe os três servidores antes do globalSetup. Painel e site são BUILDADOS aqui:
  // é o artefato de produção que precisa ser testado (o `dev` do Vite e do Nuxt tem
  // transformação e HMR que não existem no ar), e é por isso que o passo de build entra no
  // comando em vez de ficar num script à parte — assim `npx playwright test` sozinho também
  // testa a coisa certa.
  webServer: [
    {
      command: `php artisan serve --host=${BIND_HOST} --port=${portOf(API_URL)}`,
      cwd: BACKEND_DIR,
      url: `${API_URL}/up`,
      // APP_ENV é a única variável que o `artisan serve` repassa ao processo do servidor
      // embutido (ver README, seção "Divergências") — e é a única de que precisamos: o resto
      // da configuração vem de backend/.env.e2e, carregado por causa dela.
      env: { APP_ENV: 'e2e', PHP_CLI_SERVER_WORKERS: '4' },
      reuseExistingServer: false,
      // O servidor embutido do PHP escreve uma linha de log por requisição; intercalado com a
      // saída dos testes isso esconde o que interessa. Erro de verdade continua visível de
      // duas formas melhores: no corpo da resposta (APP_DEBUG=true no ambiente e2e) e em
      // backend/storage/logs/laravel.log.
      stdout: 'ignore',
      stderr: 'ignore',
      timeout: 60_000,
    },
    {
      command: `npm run build && npx vite preview --host ${BIND_HOST} --port ${portOf(ADMIN_URL)} --strictPort`,
      cwd: ADMIN_DIR,
      url: `${ADMIN_URL}/login`,
      // VITE_* entram no build pelo ambiente do processo (o loadEnv do Vite dá precedência a
      // process.env sobre o .env do projeto), o que evita ter de manter um .env de e2e no
      // frontend-admin só para apontar para outra porta.
      env: {
        VITE_API_URL: API_URL,
        VITE_SITE_URL: SITE_URL,
        // Sem isto, o logout por inatividade (15 minutos em desenvolvimento) poderia disparar
        // no meio de uma execução longa e derrubar um teste por um motivo que não é o dele.
        VITE_SESSION_IDLE_TIMEOUT_MINUTES: '120',
      },
      reuseExistingServer: false,
      stdout: 'pipe',
      stderr: 'pipe',
      timeout: process.env.CI ? 600_000 : 300_000,
    },
    {
      command: 'npm run build && node .output/server/index.mjs',
      cwd: SITE_DIR,
      url: SITE_URL,
      env: {
        HOST: BIND_HOST,
        PORT: portOf(SITE_URL),
        NUXT_PUBLIC_API_URL: API_URL,
        NUXT_PUBLIC_SITE_URL: SITE_URL,
      },
      reuseExistingServer: false,
      stdout: 'pipe',
      stderr: 'pipe',
      timeout: process.env.CI ? 600_000 : 300_000,
    },
    // Quarta instância do MESMO build do site (reaproveita o `.output` que a entrada acima
    // acabou de gerar — sem `npm run build` aqui, de propósito: os itens do array `webServer`
    // sobem em sequência, cada um esperando o anterior responder antes de começar, então o
    // build já está pronto quando este comando roda). `NUXT_PUBLIC_API_URL` aponta para uma
    // porta fechada — é o "API fora do ar" que tests/site-sem-api/ exercita: o 503 das páginas
    // que leem o CMS e a frase de fundação alternativa da home, os dois pontos que dependem do
    // SSR e por isso ficam fora do alcance do page.route() do Playwright (ver
    // docs/decisoes/0019-falha-da-api-responde-503-nao-404.md). A checagem de saúde do
    // Playwright aceita qualquer status abaixo de 404, e a home responde 200 mesmo sem API
    // (ver frontend-site/app/pages/index.vue) — é por isso que a URL de saúde abaixo é a home,
    // e não uma página que dependeria da própria API que este servidor não tem.
    {
      command: 'node .output/server/index.mjs',
      cwd: SITE_DIR,
      url: SITE_SEM_API_URL,
      env: {
        HOST: BIND_HOST,
        PORT: portOf(SITE_SEM_API_URL),
        NUXT_PUBLIC_API_URL: API_URL_FORA_DO_AR,
        NUXT_PUBLIC_SITE_URL: SITE_SEM_API_URL,
      },
      reuseExistingServer: false,
      stdout: 'pipe',
      stderr: 'pipe',
      timeout: 60_000,
    },
  ],
})
