import type { PortalUser } from "@/components/portal/types"

export type TrafficChartSlice = {
  up?: number
  down?: number
  total?: number
  used?: number
}

export type ResolvedTrafficStats = {
  total: number
  used: number
  down: number
  up: number
  remaining: number
}

export function resolveTrafficStats(
  user: PortalUser,
  chart?: TrafficChartSlice | null
): ResolvedTrafficStats {
  const hideQuota = (user.quota_hidden_from_user ?? 0) === 1
  const total = hideQuota ? 0 : Math.max(0, user.data_limit ?? 0)
  const used = Math.max(0, user.used_traffic ?? 0)
  let down = Math.max(0, chart?.down ?? 0)
  const up = Math.max(0, chart?.up ?? 0)
  const chartUsed = Math.max(0, chart?.used ?? 0)
  const effectiveUsed = used > 0 ? used : chartUsed
  if (down + up < 1 && effectiveUsed > 0) {
    down = effectiveUsed
  }
  const remaining = total > 0 ? Math.max(0, total - effectiveUsed) : 0
  return { total, used: effectiveUsed, down, up, remaining }
}
