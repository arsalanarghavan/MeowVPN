import { formatNumber } from "@/lib/format-locale"

export type AuditRow = {
  id: number
  created_at: string
  domain: string
  event_type: string
  actor_kind: string
  actor_wp_user_id: number
  actor_svp_user_id: number
  target_type: string
  target_id: number
  reseller_scope_id: number
  payload: unknown
}

type AuditT = (key: string, opts?: Record<string, string | number>) => string

/** WordPress sanitize_key() strips dots; map stored values to canonical dotted form. */
const SANITIZED_EVENT_ALIASES: Record<string, string> = {
  receiptapprove: "receipt.approve",
  receipt_approve: "receipt.approve",
  receiptreject: "receipt.reject",
  receipt_reject: "receipt.reject",
  receiptreject_after_approve: "receipt.reject_after_approve",
  receipt_reject_after_approve: "receipt.reject_after_approve",
  receiptamount_adjust: "receipt.amount_adjust",
  receipt_amount_adjust: "receipt.amount_adjust",
  impersonationstart: "impersonation.start",
  impersonation_start: "impersonation.start",
  impersonationstop: "impersonation.stop",
  impersonation_stop: "impersonation.stop",
  dashboardlogin_fail: "dashboard.login_fail",
  dashboard_login_fail: "dashboard.login_fail",
  backuprestore: "backup.restore",
  backup_restore: "backup.restore",
  panelrebuild_from_db: "panel.rebuild_from_db",
  panel_rebuild_from_db: "panel.rebuild_from_db",
  servicepurge_expired: "service.purge_expired",
  service_purge_expired: "service.purge_expired",
  marketingoffer_sent: "marketing.offer_sent",
  marketing_offer_sent: "marketing.offer_sent",
  marketingoffer_converted: "marketing.offer_converted",
  marketing_offer_converted: "marketing.offer_converted",
}

const SUMMARY_EVENT_TYPES = new Set([
  "receipt.approve",
  "receipt.reject",
  "receipt.reject_after_approve",
  "receipt.amount_adjust",
  "impersonation.start",
  "impersonation.stop",
  "dashboard.login_fail",
  "backup.restore",
  "panel.rebuild_from_db",
  "bot_reseller_save",
  "reseller_inbound_labels_save",
  "reseller_bind_users",
  "service.purge_expired",
])

export function canonicalAuditEventType(raw: string): string {
  const s = raw.trim().toLowerCase()
  if (!s) return ""
  if (SANITIZED_EVENT_ALIASES[s]) return SANITIZED_EVENT_ALIASES[s]
  if (s.includes(".")) return s
  return s
}

function eventI18nKey(canonical: string): string {
  return `event_${canonical.replace(/\./g, "_").replace(/-/g, "_")}`
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function payloadRecord(payload: unknown): Record<string, unknown> {
  if (payload && typeof payload === "object" && !Array.isArray(payload)) {
    return payload as Record<string, unknown>
  }
  return {}
}

function fmtId(id: number, isFa: boolean): string {
  return id > 0 ? formatNumber(id, isFa) : "—"
}

function fmtAmount(v: unknown, isFa: boolean): string {
  const n = num(v)
  return formatNumber(Math.round(n * 100) / 100, isFa)
}

export function formatAuditDomain(domain: string, t: AuditT): string {
  const d = domain.trim().toLowerCase()
  const key = `domain_${d.replace(/[^a-z0-9_]/g, "_")}`
  const translated = t(key)
  if (translated !== key) return translated
  return d || "—"
}

export function formatAuditEventLabel(eventType: string, t: AuditT): string {
  const canonical = canonicalAuditEventType(eventType)
  if (!canonical) return "—"
  const key = eventI18nKey(canonical)
  const translated = t(key)
  if (translated !== key) return translated
  return t("eventGeneric", { event: canonical })
}

export function formatAuditActor(row: AuditRow, t: AuditT, isFa: boolean): string {
  const kind = row.actor_kind.trim().toLowerCase()
  const kindKey = `actor_${kind.replace(/[^a-z0-9_]/g, "_")}`
  const kindLabel = t(kindKey) !== kindKey ? t(kindKey) : kind || t("actor_unknown")

  if (row.actor_svp_user_id > 0) {
    return t("actorWithSvpId", { kind: kindLabel, id: fmtId(row.actor_svp_user_id, isFa) })
  }
  if (row.actor_wp_user_id > 0) {
    return t("actorWithWpId", { kind: kindLabel, id: fmtId(row.actor_wp_user_id, isFa) })
  }
  return kindLabel
}

export function formatAuditTarget(row: AuditRow, t: AuditT, isFa: boolean): string {
  const type = row.target_type.trim().toLowerCase()
  const id = row.target_id
  if (!type && id < 1) return "—"

  const typeKey = `target_${type.replace(/[^a-z0-9_]/g, "_")}`
  const typeLabel = type && t(typeKey) !== typeKey ? t(typeKey) : type || t("target_unknown")

  if (id > 0) {
    return t("targetWithId", { type: typeLabel, id: fmtId(id, isFa) })
  }
  return typeLabel
}

function formatPayloadField(key: string, value: unknown, t: AuditT, isFa: boolean): string | null {
  if (value === null || value === undefined || value === "") return null

  const labelKey = `payload_${key.replace(/[^a-z0-9_]/g, "_")}`
  const label = t(labelKey) !== labelKey ? t(labelKey) : key

  if (typeof value === "boolean") {
    return `${label}: ${value ? t("payloadYes") : t("payloadNo")}`
  }
  if (typeof value === "number") {
    if (key.includes("amount") || key === "delta") {
      return `${label}: ${fmtAmount(value, isFa)}`
    }
    return `${label}: ${fmtId(value, isFa)}`
  }
  if (typeof value === "string") {
    return `${label}: ${value}`
  }
  if (Array.isArray(value)) {
    if (value.length === 0) return null
    const items = value.map((x) => String(x)).join(", ")
    return `${label}: ${items}`
  }
  if (typeof value === "object") {
    try {
      const s = JSON.stringify(value)
      if (s.length > 120) return `${label}: ${s.slice(0, 120)}…`
      return `${label}: ${s}`
    } catch {
      return null
    }
  }
  return `${label}: ${String(value)}`
}

export function formatAuditPayloadDetails(
  payload: unknown,
  t: AuditT,
  isFa: boolean
): string[] {
  const pl = payloadRecord(payload)
  const keys = Object.keys(pl)
  if (keys.length === 0) return []

  const lines: string[] = []
  for (const key of keys) {
    const line = formatPayloadField(key, pl[key], t, isFa)
    if (line) lines.push(line)
  }
  return lines
}

export function formatAuditSummary(
  row: AuditRow,
  t: AuditT,
  isFa: boolean
): { headline: string; details: string[]; technical?: string } {
  const pl = payloadRecord(row.payload)
  const ev = canonicalAuditEventType(row.event_type)
  const receiptId = row.target_type === "receipt" && row.target_id > 0 ? row.target_id : num(pl.receipt_id)

  let headline = formatAuditEventLabel(row.event_type, t)

  switch (ev) {
    case "receipt.approve":
      headline = t("summary_receipt_approve", {
        receipt: fmtId(receiptId || row.target_id, isFa),
        user: fmtId(num(pl.user_id), isFa),
        tx: fmtId(num(pl.tx_id), isFa),
        label: String(pl.label ?? "—"),
      })
      break
    case "receipt.reject":
      headline = t("summary_receipt_reject", {
        receipt: fmtId(row.target_id, isFa),
        tx: fmtId(num(pl.tx_id), isFa),
        reason: String(pl.reject_reason ?? "—"),
      })
      break
    case "receipt.reject_after_approve":
      headline = t("summary_receipt_reject_after_approve", {
        receipt: fmtId(row.target_id, isFa),
        tx: fmtId(num(pl.tx_id), isFa),
        service: fmtId(num(pl.service_id), isFa),
        reason: String(pl.reason ?? "—"),
      })
      break
    case "receipt.amount_adjust":
      headline = t("summary_receipt_amount_adjust", {
        receipt: fmtId(row.target_id, isFa),
        user: fmtId(num(pl.user_id), isFa),
        old: fmtAmount(pl.old_amount, isFa),
        new: fmtAmount(pl.new_amount, isFa),
        delta: fmtAmount(pl.delta, isFa),
        status: String(pl.status ?? "—"),
      })
      break
    case "impersonation.start":
      headline = t("summary_impersonation_start", {
        target: fmtId(row.target_id, isFa),
      })
      break
    case "impersonation.stop":
      headline = t("summary_impersonation_stop", {
        target: fmtId(row.target_id, isFa),
      })
      break
    case "dashboard.login_fail":
      headline = t("summary_dashboard_login_fail", {
        login: String(pl.login ?? "—"),
      })
      break
    case "backup.restore":
      headline = t("summary_backup_restore", {
        source: String(pl.source ?? "—"),
        filename: String(pl.filename ?? "—"),
      })
      break
    case "panel.rebuild_from_db":
      headline = t("summary_panel_rebuild_from_db", {
        done: pl.done ? t("payloadYes") : t("payloadNo"),
        total: fmtId(num(pl.total), isFa),
        next: fmtId(num(pl.next_offset), isFa),
      })
      break
    case "bot_reseller_save":
      headline = t("summary_bot_reseller_save", {
        user: fmtId(row.target_id, isFa),
        enabled: pl.enabled ? t("payloadYes") : t("payloadNo"),
      })
      break
    case "reseller_inbound_labels_save":
      headline = t("summary_reseller_inbound_labels_save", {
        user: fmtId(row.target_id, isFa),
        count: fmtId(num(pl.count), isFa),
      })
      break
    case "reseller_bind_users":
      headline = t("summary_reseller_bind_users", {
        user: fmtId(row.target_id, isFa),
        count: fmtId(Array.isArray(pl.user_ids) ? pl.user_ids.length : 0, isFa),
      })
      break
    case "service.purge_expired":
      headline = t("summary_service_purge_expired", {
        service: fmtId(row.target_id, isFa),
        remark: String(pl.remark ?? "—"),
        user: fmtId(num(pl.user_id), isFa),
        days: fmtId(num(pl.days_since_expiry), isFa),
      })
      break
    default:
      break
  }

  const details = SUMMARY_EVENT_TYPES.has(ev)
    ? []
    : formatAuditPayloadDetails(row.payload, t, isFa)
  const technical = ev || undefined

  return { headline, details, technical }
}
