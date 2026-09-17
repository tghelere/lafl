import { httpClient } from '@/services/http'
import type { ContentMarker } from '@/types/pages'

export async function fetchContentMarkers(): Promise<ContentMarker[]> {
  const { data } = await httpClient.get<{ data: ContentMarker[] }>('/api/v1/content-markers')

  return data.data
}
