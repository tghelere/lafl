import { fileURLToPath } from 'node:url'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  // Ordem importa: fontes antes de tokens de marca (compartilhados — cor e tipografia,
  // única fonte de verdade com frontend-admin), tokens de marca antes de tokens locais
  // (espaçamento/raio/sombra/layout), tokens antes de base, base antes de componentes.
  css: [
    '~/assets/css/fonts.css',
    fileURLToPath(new URL('../shared/design-tokens/tokens.css', import.meta.url)),
    '~/assets/css/tokens.css',
    '~/assets/css/base.css',
    '~/assets/css/components.css',
  ],

  runtimeConfig: {
    public: {
      // Preenchidos via NUXT_PUBLIC_* no .env — ver .env.example.
      apiUrl: '',
      siteUrl: '',
      umamiWebsiteId: '',
      umamiUrl: '',
    },
  },

  app: {
    head: {
      htmlAttrs: { lang: 'pt-BR' },
    },
  },

  nitro: {
    prerender: {
      // O cabeçalho (AppHeader.vue) já linka os sete itens do menu principal de
      // docs/estrutura-site.md §1.1, mas nem toda seção tem página própria ainda (ver
      // docs/roadmap.md). Com o crawler padrão (`crawlLinks: true`), `nuxt generate` seguiria
      // esses links e falharia tentando prerenderizar uma rota sem página ou sem página do
      // CMS correspondente. `crawlLinks: false` faz o generate prerenderizar só o que está
      // listado abaixo. Toda página do CMS é servida pela rota genérica `[...slug].vue`
      // (Etapa 1 do roadmap) — generalizar isso é responsabilidade do template, não deste
      // arquivo; aqui ainda listamos cada slug manualmente porque não existe endpoint público
      // de listagem de páginas (só `GET /pages/{slug}`, ver docs/estrutura-site.md §3.1) para
      // descobrir as rotas publicadas em tempo de build.
      crawlLinks: false,
      routes: [
        '/',
        '/robots.txt',
        '/sitemap.xml',
        // Slugs atuais conhecidos no momento do build. Renomear um deles exige rodar `nuxt
        // generate` de novo para o novo slug entrar no output estático; o slug antigo só
        // resolve com 301 se o site estiver rodando com o servidor Nitro (`nuxt build` +
        // node), não em hospedagem 100% estática (ver docs/roadmap.md).
        '/quem-somos',
        '/quem-somos/nossa-historia',
        '/quem-somos/missao-visao-valores',
        '/quem-somos/governanca',
        '/quem-somos/o-lar-hoje',
        '/educacao-infantil',
        '/educacao-infantil/dia-da-crianca',
        '/educacao-infantil/proposta-pedagogica',
        '/educacao-infantil/alimentacao-e-saude',
        '/educacao-infantil/estrutura',
        '/educacao-infantil/depoimentos',
        '/educacao-infantil/matricula',
        '/contraturno',
        '/contraturno/o-projeto',
        '/contraturno/para-quem-e',
        '/contraturno/como-funciona',
        '/contraturno/parceiros',
        '/contraturno/o-que-vem-por-ai',
        '/bazar',
        '/bazar/visite-a-loja',
        '/bazar/o-que-aceitamos',
        '/bazar/para-onde-vai',
        '/bazar/sua-compra-vira-educacao',
        '/como-ajudar',
        '/como-ajudar/doar',
        '/como-ajudar/parceiros',
        '/transparencia',
        '/politica-de-privacidade',
        // Os cinco formulários (ver docs/estrutura-site.md Parte 2) NÃO entram aqui, de
        // propósito — mesmo raciocínio de /transparencia/documentos: cada página lê
        // route.query (erro=1&campos=...) para reexibir erro de validação sem JavaScript
        // (ver App\Support\useFormErrorState.ts e server/api/forms/[tipo].post.ts). Uma
        // página prerenderizada vira arquivo estático servido por caminho, ignorando query
        // string — colocá-las aqui já causou bug real nesta sessão: o Nitro passou a
        // devolver sempre o snapshot sem erro, não importa a query. As cinco rotas continuam
        // funcionando normalmente porque o servidor Nitro faz SSR delas a cada request (o
        // mesmo servidor que já é exigido para o proxy de formulário e para
        // /transparencia/documentos).
        //
        // Confirmação pós-formulário, noindex — as cinco variações são enumeráveis, então
        // prerenderizamos todas por completude (ver App\Enums\FormSubmissionType no backend).
        '/obrigado/inscricao',
        '/obrigado/coleta',
        '/obrigado/voluntariado',
        '/obrigado/parceria',
        '/obrigado/contato',
      ],
    },
  },
})
