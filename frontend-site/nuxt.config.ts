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
      // `staging` liga o bloqueio de indexação do site inteiro (ver
      // server/middleware/staging-noindex.ts). Vazio em desenvolvimento e em produção.
      environment: '',
    },
  },

  app: {
    head: {
      htmlAttrs: { lang: 'pt-BR' },
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
        { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
        { rel: 'apple-touch-icon', sizes: '180x180', href: '/apple-touch-icon.png' },
      ],
    },
  },

  // AppHeader.vue e AppFooter.vue importam a logo de shared/brand/ (fora da raiz do
  // projeto) — mesmo padrão do tokens.css de marca acima, evita duplicar o arquivo à mão.
  // Sem isto o dev server nega leitura de arquivo fora de frontend-site/ (o build de
  // produção não usa o dev server, então funciona sem isto — mas dev quebraria).
  vite: {
    server: {
      fs: {
        allow: ['..'],
      },
    },
  },

  // Fotos em public/fotos/ (ver docs/fotos.md e app/components/AppFoto.vue) não têm hash de
  // conteúdo no nome — trocar o arquivo mantendo o mesmo nome não muda a URL. Por isso
  // Cache-Control de 30 dias, sem `immutable` (immutable diz ao navegador "nunca revalide
  // isto", o que impediria ver uma foto trocada antes de 30 dias mesmo com refresh).
  routeRules: {
    '/fotos/**': {
      headers: { 'cache-control': 'public, max-age=2592000' },
    },
  },

  nitro: {
    prerender: {
      // O cabeçalho (AppHeader.vue) lê a navegação de app/config/navigation.ts (ver
      // docs/design/navegacao.md), mas nem toda seção tem página própria ainda (ver
      // docs/roadmap.md). Com o crawler padrão (`crawlLinks: true`), `nuxt generate` seguiria
      // esses links e falharia tentando prerenderizar uma rota sem página ou sem página do
      // CMS correspondente. `crawlLinks: false` faz o generate prerenderizar só o que está
      // listado abaixo.
      //
      // A lista só tem página cujo conteúdo é FIXO no .vue E não depende de nenhum número
      // calculado. Toda rota que lê o conteúdo da API de `pages` saiu daqui quando o painel
      // passou a editar esse conteúdo: uma rota prerenderizada vira arquivo estático gravado
      // no build, então o texto salvo pelo painel só apareceria no site depois de um novo
      // `nuxt generate` — exatamente o que a edição pelo painel existe para evitar. Essas
      // rotas passam a ser SSR a cada request, e a alteração aparece já na requisição
      // seguinte (o backend invalida o cache de 10 minutos ao salvar, ver
      // App\Actions\Content\SavePage).
      //
      // A HOME saiu daqui pelo mesmo motivo, por outro caminho: o conteúdo dela é fixo, mas a
      // linha de registro lê idade e ano de /api/v1/public/institution-facts (ver
      // app/composables/useInstitutionFacts.ts). Prerenderizada, a home congelaria a idade no
      // dia do build e só voltaria a acertar no build seguinte — a mesma falha silenciosa que
      // o número escrito à mão tinha, agora com outra fachada.
      //
      // Isso não muda a exigência de deploy: o servidor Nitro (`nuxt build` + node) já era
      // necessário para os cinco formulários e para /transparencia/documentos (ver abaixo),
      // então o site nunca foi hospedagem 100% estática.
      crawlLinks: false,
      routes: [
        // '/robots.txt' NÃO entra aqui: precisa ser decidido em tempo de execução, porque o
        // mesmo pacote de deploy vai para homologação e para produção e só uma das duas pode
        // ser indexada (ver server/routes/robots.txt.ts).
        '/sitemap.xml',
        // Conteúdo fixo no próprio .vue, sem `usePublicPage` — o painel não edita nenhuma
        // destas, então prerenderizar continua sendo a melhor opção.
        '/o-que-fazemos',
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
