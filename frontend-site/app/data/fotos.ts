// Fonte única das fotos do site — gerado a partir de docs/fotos-manifest.json (apagado ao
// final desta etapa, ver docs/fotos.md). Arquivos reais em public/fotos/{secao}/
// {slug}-{largura}.{webp,jpg} — ver app/components/AppFoto.vue, que é o único lugar que monta
// esse caminho. Nenhuma página referencia caminho de imagem diretamente.
//
// Toda foto tem .webp e .jpg nas mesmas larguras (fallback, não formato "melhor"). EXIF já
// removido e orientação já gravada no pixel na preparação dos arquivos — nada disso acontece
// em tempo de execução.
export type Secao =
  | 'home'
  | 'historia'
  | 'educacao-infantil'
  | 'bazar'
  | 'quem-somos'
  | 'transparencia'
  | 'contato'

export type Foto = {
  secao: Secao
  alt: string
  largura: number
  altura: number
  larguras: number[]
}

export const fotos = {
  'fachada-sede': {
    secao: 'home',
    alt: 'Fachada da sede do Lar Anália Franco, com o letreiro da instituição sobre a entrada principal',
    largura: 5184,
    altura: 3888,
    larguras: [1920, 1280, 960, 640, 400],
  },
  'fachada-sede-atual': {
    secao: 'home',
    alt: 'Entrada da sede do Lar Anália Franco vista do gramado da frente',
    largura: 903,
    altura: 1600,
    larguras: [640, 400],
  },
  'fachada-antes': {
    secao: 'historia',
    alt: 'Fachada da sede antes da reforma, com pintura desgastada em cinza e azul',
    largura: 1024,
    altura: 768,
    larguras: [960, 640, 400],
  },
  'fachada-depois': {
    secao: 'historia',
    alt: 'Fachada da sede depois da reforma, vista do jardim da entrada',
    largura: 1024,
    altura: 768,
    larguras: [960, 640, 400],
  },
  'patio-antes': {
    secao: 'historia',
    alt: 'Pátio interno antes da reforma, com parede de tinta descascada',
    largura: 1024,
    altura: 768,
    larguras: [960, 640, 400],
  },
  'patio-depois': {
    secao: 'historia',
    alt: 'Pátio interno depois da reforma, com parede pintada de branco e faixa geométrica colorida',
    largura: 5184,
    altura: 3888,
    larguras: [1920, 1280, 960, 640, 400],
  },
  'placa-inauguracao': {
    secao: 'historia',
    alt: 'Placa de bronze na parede da sede: obra iniciada em 18 de abril de 1957 e inaugurada em 15 de novembro de 1963',
    largura: 903,
    altura: 1600,
    larguras: [640, 400],
  },
  'recanto-da-amizade': {
    secao: 'historia',
    alt: 'Entrada do Recanto da Amizade, área arborizada com mesas no terreno da instituição',
    largura: 1196,
    altura: 880,
    larguras: [960, 640, 400],
  },
  'recanto-vista': {
    secao: 'historia',
    alt: 'Área arborizada do Recanto da Amizade, com mangueiras e mesas de concreto',
    largura: 5184,
    altura: 3888,
    larguras: [1920, 1280, 960, 640, 400],
  },
  recepcao: {
    secao: 'historia',
    alt: 'Recepção da sede, com parede listrada em laranja e amarelo',
    largura: 1024,
    altura: 768,
    larguras: [960, 640, 400],
  },
  'horta-kids': {
    secao: 'educacao-infantil',
    alt: 'Horta Kids: canteiros feitos com pneus coloridos diante de um muro com desenho de crianças plantando',
    largura: 1600,
    altura: 1200,
    larguras: [1280, 960, 640, 400],
  },
  'horta-kids-vista': {
    secao: 'educacao-infantil',
    alt: 'Vista ampla da Horta Kids, com canteiros de pneu no gramado e painéis de paletes no muro',
    largura: 1600,
    altura: 1200,
    larguras: [1280, 960, 640, 400],
  },
  'sala-multiuso-conto': {
    secao: 'educacao-infantil',
    alt: 'Sala multiuso com tapete de tatame colorido, cantinho da Hora do Conto e Casa do Faz de Conta',
    largura: 720,
    altura: 1280,
    larguras: [640, 400],
  },
  'sala-multiuso-imaginacao': {
    secao: 'educacao-infantil',
    alt: 'Cantinho Mundo da Imaginação, com brinquedos, casa de bonecas e tatame colorido',
    largura: 1280,
    altura: 720,
    larguras: [1280, 960, 640, 400],
  },
  'sala-multiuso-brinquedos': {
    secao: 'educacao-infantil',
    alt: 'Brinquedoteca da sala multiuso, com fantasias, carrinhos e material de faz de conta',
    largura: 1280,
    altura: 720,
    larguras: [1280, 960, 640, 400],
  },
  'bazar-placa': {
    secao: 'bazar',
    alt: 'Placa externa do Bazar Beneficente do Lar Anália Franco, com telefone e endereço',
    largura: 903,
    altura: 1600,
    larguras: [640, 400],
  },
  'bazar-entrada': {
    secao: 'bazar',
    alt: 'Entrada do Bazar Beneficente, com toldo azul e carrinhos de compra ao lado',
    largura: 903,
    altura: 1600,
    larguras: [640, 400],
  },
  'bazar-salao': {
    secao: 'bazar',
    alt: 'Salão do bazar com araras de roupas, manequins e chapéus expostos',
    largura: 899,
    altura: 1599,
    larguras: [640, 400],
  },
  'bazar-moveis': {
    secao: 'bazar',
    alt: 'Setor de móveis do bazar, com sofás, poltronas e utensílios à venda',
    largura: 899,
    altura: 1599,
    larguras: [640, 400],
  },
  'bazar-leitura': {
    secao: 'bazar',
    alt: 'Setor de livros do bazar, com estantes cheias e bancos para leitura',
    largura: 903,
    altura: 1600,
    larguras: [640, 400],
  },
  'bazar-deposito': {
    secao: 'bazar',
    alt: 'Depósito de móveis do bazar, com mesas, cadeiras e armários organizados',
    largura: 903,
    altura: 1600,
    larguras: [640, 400],
  },
  'equipe-formacao': {
    secao: 'quem-somos',
    alt: 'Equipe do Lar Anália Franco reunida em sessão de formação no auditório',
    largura: 1600,
    altura: 1200,
    larguras: [1280, 960, 640, 400],
  },
  'almoxarifado-alimentos': {
    secao: 'transparencia',
    alt: 'Almoxarifado de alimentos, com prateleiras organizadas de arroz, feijão e mantimentos',
    largura: 768,
    altura: 1024,
    larguras: [640, 400],
  },
  'almoxarifado-limpeza': {
    secao: 'transparencia',
    alt: 'Almoxarifado de materiais de limpeza e higiene, com prateleiras organizadas',
    largura: 768,
    altura: 1024,
    larguras: [640, 400],
  },
  'mapa-enderecos': {
    secao: 'contato',
    // Mapa gerado a partir de blocos do OpenStreetMap (ver docs/fotos.md — não é foto), servido
    // por AppMapaEnderecos.vue, não por AppFoto: largura de exibição FIXA (a do bloco dos dois
    // cartões de /contato), então o srcset é por densidade (1x/2x), não por largura — `larguras`
    // abaixo é só metadado, sem efeito nesta imagem. `largura`/`altura` são as dimensões do
    // arquivo 1x (1088×940, zoom 18); o 2x (2176×1880, zoom 19) tem o mesmo enquadramento, cada
    // densidade renderizada direto no zoom nativo, nunca redimensionada depois. Altura maior
    // que 16:9 de propósito (ajuste de enquadramento desta sessão): a proporção mais estreita
    // não cabia a Avenida Anália Franco inteira com folga e os dois pinos aproximadamente
    // centrados ao mesmo tempo — instrução explícita foi preferir a faixa mais alta a
    // sacrificar a avenida.
    //
    // Pino 1 (Sede/CEI) usa a coordenada exata de um POI já nomeado no OSM ("C.E.I. Analia
    // Franco - Lar Anália Franco de Londrina"). Pino 2 (Bazar) não tem ponto de numeração de
    // casa mapeado no OSM para "Rua Rosa Siqueira, 152" — usa o centroide do trecho de rua com
    // o mesmo CEP da Sede/CEI (86039-560), a precisão real disponível: nível de rua/quadra, não
    // de fachada exata (por isso a ressalva "localização aproximada" no `alt` abaixo). Não
    // inventar uma coordenada mais precisa do que isso sem confirmar o ponto com a
    // administração do bazar.
    alt: 'Mapa de ruas do Jardim Aeroporto, em Londrina/PR, com dois alfinetes numerados: 1, a Sede/CEI Anália Franco, na Avenida Anália Franco; 2, o Bazar Beneficente (localização aproximada), na Rua Rosa Siqueira, a poucas quadras de distância',
    largura: 1088,
    altura: 940,
    larguras: [1088],
  },
} as const satisfies Record<string, Foto>

export type FotoSlug = keyof typeof fotos
