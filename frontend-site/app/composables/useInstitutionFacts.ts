/**
 * Chaves de `config('institution.milestones')` no backend — espelho da lista daquele arquivo.
 * Acrescentar um marco lá e usá-lo aqui exige acrescentar a chave aqui também.
 */
export type InstitutionMilestoneKey =
  | 'association_founded'
  | 'headquarters_construction_started'
  | 'headquarters_inaugurated'
  | 'bazaar_opened'
  | 'cei_created'

export type InstitutionMilestone = {
  year: number
  /** Data completa quando a instituição confirmou o dia; null quando só o ano é conhecido. */
  date: string | null
  age_years: number
  /** Já em português e com o plural certo ("1 ano"/"73 anos") — o site nunca monta a frase. */
  age_formatted: string
}

export type InstitutionFacts = {
  milestones: Record<InstitutionMilestoneKey, InstitutionMilestone>
  transparency_documents: { count: number; count_formatted: string }
}

/**
 * Os números institucionais que envelhecem — idades e a contagem do acervo de transparência —
 * calculados pela API (App\Services\InstitutionalFacts).
 *
 * Existe para as páginas cujo conteúdo é fixo no .vue e por isso não passa pelo CMS, onde os
 * marcadores `{{...}}` já resolvem isso sozinhos (ver App\Enums\ContentMarker). O valor chega
 * formatado: plural e substantivo são decisão da API, nunca do front.
 *
 * Quem usa isto não pode ser prerenderizada (ver nitro.prerender.routes em nuxt.config.ts) —
 * um arquivo estático gravaria o número do dia do build, que é exatamente o problema que
 * calcular resolve.
 */
export function useInstitutionFacts() {
  const config = useRuntimeConfig()

  return useAsyncData<{ data: InstitutionFacts }>('institution-facts', () =>
    $fetch(`${config.public.apiUrl}/api/v1/public/institution-facts`),
  )
}
