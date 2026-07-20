/** Parse API datetime `YYYY-MM-DD HH:mm:ss` (or partial) to local Date ms; 0 if empty/invalid. */
export function apiDatetimeToMs(value: string): number {
  const s = value.trim()
  if (!s) return 0
  const norm = s.replace(" ", "T").slice(0, 16)
  const d = new Date(norm)
  const t = d.getTime()
  return Number.isFinite(t) ? t : 0
}

/** Format local Date ms to API `YYYY-MM-DD HH:mm:ss`. */
export function msToApiDatetime(ms: number): string {
  if (!Number.isFinite(ms) || ms < 1) return ""
  const d = new Date(ms)
  const pad = (n: number) => String(n).padStart(2, "0")
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`
}

export function msToTimeValue(ms: number): string {
  if (!Number.isFinite(ms) || ms < 1) return "00:00"
  const d = new Date(ms)
  const pad = (n: number) => String(n).padStart(2, "0")
  return `${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export function applyTimeToMs(dateMs: number, timeValue: string): number {
  if (!Number.isFinite(dateMs) || dateMs < 1) return 0
  const m = /^(\d{1,2}):(\d{2})/.exec(timeValue.trim())
  if (!m) return dateMs
  const d = new Date(dateMs)
  d.setHours(Math.min(23, Math.max(0, parseInt(m[1]!, 10))))
  d.setMinutes(Math.min(59, Math.max(0, parseInt(m[2]!, 10))))
  d.setSeconds(0, 0)
  return d.getTime()
}

export function dateOnlyMs(ms: number): number {
  if (!Number.isFinite(ms) || ms < 1) return 0
  const d = new Date(ms)
  d.setHours(0, 0, 0, 0)
  return d.getTime()
}
