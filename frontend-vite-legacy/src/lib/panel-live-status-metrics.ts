/**
 * Parse flattened 3x-ui `server/status` style maps (from PHP walk_status) into typed metrics.
 */

export type ParsedPanelLiveStatus = {
  cpuPercent: number | null
  /** Raw API value before clamping (for anomaly UI). */
  cpuPercentRaw: number | null
  memUsed: number | null
  memTotal: number | null
  diskUsed: number | null
  diskTotal: number | null
  swapUsed: number | null
  swapTotal: number | null
  uptimeSeconds: number | null
  tcpCount: number | null
  cpuCores: number | null
  logicalProcessors: number | null
  cpuSpeedMhz: number | null
  /** Keys not mapped to structured fields; original key preserved. */
  remaining: [string, string][]
}

function normKey(k: string): string {
  return k.trim().toLowerCase().replace(/\s+/g, "")
}

function coerceNumber(v: unknown): number | null {
  if (typeof v === "number" && Number.isFinite(v)) return v
  if (typeof v === "string") {
    const t = v.trim()
    if (!t) return null
    const n = Number(t.replace(/,/g, ""))
    return Number.isFinite(n) ? n : null
  }
  return null
}

function formatScalar(v: unknown): string {
  if (typeof v === "number" && Number.isFinite(v)) return String(v)
  if (typeof v === "string") return v
  return ""
}

function isCpuPercentKey(nk: string): boolean {
  if (nk === "cpu") return true
  if (nk.endsWith(".cpu") && !nk.includes("cores") && !nk.includes("mhz") && !nk.includes("speed")) return true
  return false
}

function pickFirst(
  entries: [string, unknown][],
  predicate: (nk: string, orig: string) => boolean
): { value: number | null; key: string | null } {
  for (const [orig, raw] of entries) {
    const nk = normKey(orig)
    if (!predicate(nk, orig)) continue
    const value = coerceNumber(raw)
    if (value != null) return { value, key: orig }
  }
  return { value: null, key: null }
}

/**
 * Extract structured panel / external JSON metrics from a flat string/number map.
 */
export function parsePanelLiveStatus(
  status: Record<string, number | string> | null | undefined
): ParsedPanelLiveStatus {
  const empty: ParsedPanelLiveStatus = {
    cpuPercent: null,
    cpuPercentRaw: null,
    memUsed: null,
    memTotal: null,
    diskUsed: null,
    diskTotal: null,
    swapUsed: null,
    swapTotal: null,
    uptimeSeconds: null,
    tcpCount: null,
    cpuCores: null,
    logicalProcessors: null,
    cpuSpeedMhz: null,
    remaining: [],
  }
  if (!status || typeof status !== "object") return empty

  const entries = Object.entries(status) as [string, unknown][]
  const consumed = new Set<string>()

  const take = (pred: (nk: string) => boolean): number | null => {
    const { value, key } = pickFirst(entries, (nk) => pred(nk))
    if (key != null) consumed.add(key)
    return value
  }

  const memCurrent = take((nk) => nk === "mem.current" || nk.endsWith("mem.current"))
  const memTotal = take((nk) => nk === "mem.total" || nk.endsWith("mem.total"))
  const diskCurrent = take((nk) => nk === "disk.current" || nk.endsWith("disk.current"))
  const diskTotal = take((nk) => nk === "disk.total" || nk.endsWith("disk.total"))
  const swapCurrent = take((nk) => nk === "swap.current" || nk.endsWith("swap.current"))
  const swapTotal = take((nk) => nk === "swap.total" || nk.endsWith("swap.total"))
  const uptimeSeconds = take((nk) => nk === "uptime" || nk.endsWith(".uptime"))
  const tcpCount = take((nk) => nk === "tcpcount" || nk.endsWith("tcpcount") || nk === "tcp_count" || nk.endsWith("tcp_count"))
  const cpuCores = take((nk) => nk === "cpucores" || nk.endsWith("cpucores"))
  const logicalProcessors = take(
    (nk) => nk === "logicalpro" || nk.endsWith("logicalpro") || nk === "logicalprocessors" || nk.endsWith("logicalprocessors")
  )
  const cpuSpeedMhz = take((nk) => nk.includes("cpuspeedmhz") || nk.endsWith("cpuspeedmhz"))

  let cpuKey: string | null = null
  let cpuRaw: number | null = null
  for (const [orig, raw] of entries) {
    if (consumed.has(orig)) continue
    const nk = normKey(orig)
    if (!isCpuPercentKey(nk)) continue
    const v = coerceNumber(raw)
    if (v == null) continue
    cpuRaw = v
    cpuKey = orig
    break
  }
  if (cpuKey) consumed.add(cpuKey)

  let cpuPercent: number | null = null
  if (cpuRaw != null) {
    cpuPercent = Math.min(100, Math.max(0, cpuRaw))
  }

  const remaining: [string, string][] = []
  for (const [k, v] of entries) {
    if (consumed.has(k)) continue
    const s = formatScalar(v)
    if (s === "") continue
    remaining.push([k, s])
  }
  remaining.sort((a, b) => normKey(a[0]).localeCompare(normKey(b[0])))

  return {
    cpuPercent,
    cpuPercentRaw: cpuRaw,
    memUsed: memCurrent,
    memTotal,
    diskUsed: diskCurrent,
    diskTotal,
    swapUsed: swapCurrent,
    swapTotal,
    uptimeSeconds,
    tcpCount,
    cpuCores,
    logicalProcessors,
    cpuSpeedMhz,
    remaining,
  }
}

export function clampPct(used: number | null, total: number | null): number {
  if (used == null || total == null || !Number.isFinite(used) || !Number.isFinite(total) || total <= 0) return 0
  return Math.min(100, Math.max(0, (used / total) * 100))
}

export function hasAnyStructuredMetric(p: ParsedPanelLiveStatus): boolean {
  return (
    p.cpuPercentRaw != null ||
    p.memUsed != null ||
    p.memTotal != null ||
    p.diskUsed != null ||
    p.diskTotal != null ||
    p.swapUsed != null ||
    p.swapTotal != null ||
    p.uptimeSeconds != null ||
    p.tcpCount != null ||
    p.cpuCores != null ||
    p.logicalProcessors != null ||
    p.cpuSpeedMhz != null
  )
}
