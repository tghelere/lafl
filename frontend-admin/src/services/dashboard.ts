import { httpClient } from '@/services/http'
import type { DashboardEntry } from '@/types/submission'

export async function fetchDashboardSummary(): Promise<DashboardEntry[]> {
  const { data } = await httpClient.get<{ data: DashboardEntry[] }>('/api/v1/dashboard')

  return data.data
}
