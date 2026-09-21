import type { MaybeRefOrGetter } from 'vue'

import {
  bazaar,
  headquarters,
  type InstitutionLocation,
  jsonLdStreetAddress,
  jsonLdTelephone,
} from '~/config/institution'

/**
 * Dados institucionais publicados como JSON-LD. Todos confirmados pela instituição e
 * registrados em `docs/contexto.md` — nenhum valor `[CONFIRMAR]` entra aqui.
 *
 * Endereço e telefone de cada local vêm de app/config/institution.ts — fonte única desde a
 * sessão 23 (ver docs/relatorio-sessao-23.md), compartilhada com AppFooter.vue e contato.vue.
 * A descrição de cada local é só do JSON-LD e fica aqui mesmo.
 */
const DESCRICOES: Record<string, string> = {
  [headquarters.name]: 'Sede da associação e Centro de Educação Infantil Anália Franco.',
  [bazaar.name]: 'Loja de doações que sustenta o que o convênio da creche não cobre.',
}

const LOCAIS: InstitutionLocation[] = [headquarters, bazaar]

const CNPJ = '78.614.096/0001-75'

/**
 * O contraturno aparece aqui como o que é: programa em preparação. Nunca como serviço em
 * operação nem como oferta ativa — não há turma, aluno nem inscrição aberta (ver
 * `docs/contexto.md`, "Escola de Contraturno"). É por isso que não existe `makesOffer`,
 * `hasOfferCatalog` nem `Service` nesta marcação.
 */
const DESCRICAO =
  'Associação civil beneficente, filantrópica e de natureza espírita, em Londrina/PR. '
  + 'Mantém o CEI Anália Franco — creche e pré-escola conveniada com a Prefeitura de Londrina, '
  + 'para crianças de 1 a 5 anos — e o Bazar Beneficente, em funcionamento desde 1968. '
  + 'A escola de contraturno para crianças e adolescentes de 6 a 15 anos é um programa em '
  + 'preparação, com início de turmas previsto para 2027.'

function endereco(local: InstitutionLocation) {
  return {
    '@type': 'PostalAddress',
    'streetAddress': jsonLdStreetAddress(local),
    'addressLocality': 'Londrina',
    'addressRegion': 'PR',
    'addressCountry': 'BR',
  }
}

/**
 * Marcação `NGO` da instituição, para a home.
 *
 * Dois locais distintos (`location`), não um endereço só: a sede com o CEI e o bazar ficam em
 * ruas diferentes, e é comum alguém procurar "bazar Lar Anália Franco" no mapa e chegar à
 * creche (ver `docs/contexto.md`, "Dois locais distintos").
 *
 * `foundingDate` chega de fora porque vem calculado da API (`useInstitutionFacts`), como todo
 * marco institucional deste site: a data mora em `config/institution.php`, nunca digitada numa
 * página. Falhando a chamada, o campo simplesmente não sai — data errada no dado estruturado é
 * pior que campo ausente.
 */
export function useOrganizationJsonLd(foundingDate?: MaybeRefOrGetter<string | undefined>) {
  const { siteUrl } = useRuntimeConfig().public
  const base = siteUrl.replace(/\/$/, '')

  const dados = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'NGO',
    'name': 'Lar Anália Franco',
    'legalName': 'LAR ANÁLIA FRANCO DE LONDRINA',
    'description': DESCRICAO,
    'url': `${base}/`,
    'logo': `${base}/brand/lar-analia-franco-horizontal-600.png`,
    'image': `${base}/og/og-padrao.png`,
    'identifier': {
      '@type': 'PropertyValue',
      'propertyID': 'CNPJ',
      'value': CNPJ,
    },
    ...(toValue(foundingDate) ? { foundingDate: toValue(foundingDate) } : {}),
    'telephone': jsonLdTelephone(LOCAIS[0]!),
    'address': endereco(LOCAIS[0]!),
    'areaServed': { '@type': 'City', 'name': 'Londrina' },
    'location': LOCAIS.map((local) => ({
      '@type': 'Place',
      'name': local.name,
      'description': DESCRICOES[local.name],
      'telephone': jsonLdTelephone(local),
      'address': endereco(local),
    })),
  }))

  useHead({
    script: [
      {
        type: 'application/ld+json',
        // `<` escapado: uma string que contivesse "</script>" fecharia a tag no meio do JSON.
        // Os dados aqui são fixos, mas a defesa custa uma linha e sobrevive a quem editar.
        innerHTML: computed(() => JSON.stringify(dados.value).replace(/</g, '\\u003C')),
      },
    ],
  })
}
