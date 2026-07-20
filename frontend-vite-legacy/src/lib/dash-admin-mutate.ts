/** Prefer human-readable provision / API errors from a mutate response. */
import i18n from "./i18n"
import { apiBase, apiHeaders, normalizeAdminApiPath } from "./api-base"

const MUTATE_ERROR_I18N_KEYS: Record<string, string> = {
  forbidden_op: "mutateErrors.forbiddenOp",
  forbidden_perm: "mutateErrors.forbiddenPerm",
  forbidden_scope: "mutateErrors.forbiddenScope",
  referrer_cycle: "mutateErrors.referrerCycle",
  invalid_reseller: "mutateErrors.invalidReseller",
  not_reseller: "mutateErrors.notReseller",
  policy_missing: "mutateErrors.policyMissing",
  forbidden: "mutateErrors.forbidden",
  forbidden_plan: "mutateErrors.forbiddenPlan",
  module_missing: "mutateErrors.moduleMissing",
  no_payment_methods: "mutateErrors.noPaymentMethods",
}

export function adminMutateErrorText(
  res: AdminMutateResult,
  fallback: string
): string {
  const code = String(res.message ?? res.reason ?? "").trim()
  if (code && MUTATE_ERROR_I18N_KEYS[code]) {
    const key = MUTATE_ERROR_I18N_KEYS[code]
    const translated = i18n.t(key)
    if (translated && translated !== key) {
      return translated
    }
  }
  if (res.message && String(res.message).trim()) {
    return String(res.message)
  }
  if (res.reason && String(res.reason).trim()) {
    return String(res.reason)
  }
  const d = res.data
  if (d && typeof d === "object") {
    const rec = d as Record<string, unknown>
    const pe = rec.provision_error
    if (typeof pe === "string" && pe.trim()) {
      return pe
    }
    const msg = rec.message
    if (typeof msg === "string" && msg.trim()) {
      return msg
    }
    const rsn = rec.reason
    if (typeof rsn === "string" && rsn.trim()) {
      return rsn
    }
  }
  return fallback
}

/** Parse REST body as JSON; on failure return a short diagnostic (status + body snippet). */
async function parseAdminRestJson(res: Response): Promise<Record<string, unknown>> {
  const text = await res.text()
  if (!text.trim()) {
    if (!res.ok) {
      return { ok: false, message: `http_${res.status}` }
    }
    return {}
  }
  const trimmed = text.trim()
  if (trimmed.startsWith("{") || trimmed.startsWith("[")) {
    try {
      const json = JSON.parse(trimmed) as Record<string, unknown>
      if (!res.ok && typeof json.message !== "string" && typeof json.reason !== "string") {
        json.message = `http_${res.status}`
      }
      return json
    } catch {
      // fall through to HTML / bad_json handling
    }
  }
  try {
    const json = JSON.parse(text) as Record<string, unknown>
    if (!res.ok && typeof json.message !== "string" && typeof json.reason !== "string") {
      json.message = `http_${res.status}`
    }
    return json
  } catch {
    const lower = trimmed.slice(0, 64).toLowerCase()
    if (lower.startsWith("<!doctype") || lower.startsWith("<html")) {
      return {
        ok: false,
        message: "invalid_html_response",
        http_status: res.status,
      }
    }
    const snippet = text.replace(/\s+/g, " ").trim().slice(0, 120)
    return {
      ok: false,
      message: snippet ? `bad_json (${res.status}: ${snippet})` : `bad_json (${res.status})`,
    }
  }
}

export type AdminMutateResult = {
  ok: boolean
  code?: string
  message?: string
  plan_id?: number
  data?: unknown
  reason?: string
  iterations?: number
  transaction_id?: number
  notify_sent?: boolean
  rows?: unknown[]
  /** Unknown catalog panel IDs skipped during reseller_panel_prices_save (admin path). */
  skipped_panel_ids?: number[]
  users?: unknown[]
  billing?: Record<string, unknown>
  invited?: Record<string, unknown>
  unitEconomics?: unknown
  panelEconomics?: unknown
  panelEconomicsMap?: unknown
  checks?: unknown
  [key: string]: unknown
}

const MUTATE_RESULT_KNOWN_KEYS = new Set([
  "ok",
  "code",
  "message",
  "plan_id",
  "data",
  "reason",
  "iterations",
  "transaction_id",
  "notify_sent",
  "rows",
  "skipped_panel_ids",
  "users",
  "billing",
  "invited",
  "unitEconomics",
  "panelEconomics",
  "panelEconomicsMap",
  "checks",
])

export async function postAdminMutate(
  op: string,
  params: Record<string, unknown>
): Promise<AdminMutateResult> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = apiBase(boot as Record<string, unknown>)
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const path = typeof window !== "undefined" ? window.location.pathname : ""
  const m = path.match(/\/dashboard\/reseller_workspace\/(\d+)(?:\/|$)/)
  const resellerCtx = m ? Number(m[1]) : 0
  const payload: Record<string, unknown> = { op, ...params }
  if (resellerCtx > 0 && !("reseller_context_svp_user_id" in payload)) {
    payload.reseller_context_svp_user_id = resellerCtx
  }
  const res = await fetch(`${restBase}/admin/mutate`, {
    method: "POST",
    headers: apiHeaders(),
    credentials: "include",
    body: JSON.stringify(payload),
  })
  const json = await parseAdminRestJson(res)
  const skippedRaw = json.skipped_panel_ids
  const skipped_panel_ids = Array.isArray(skippedRaw)
    ? skippedRaw.map((x) => Number(x)).filter((x) => Number.isFinite(x) && x > 0)
    : undefined

  const result: AdminMutateResult = {
    ok: Boolean(json.ok),
    code: typeof json.code === "string" ? json.code : undefined,
    message: typeof json.message === "string" ? json.message : undefined,
    plan_id: typeof json.plan_id === "number" ? json.plan_id : undefined,
    data: "data" in json ? json.data : undefined,
    reason: typeof json.reason === "string" ? json.reason : undefined,
    iterations: typeof json.iterations === "number" ? json.iterations : undefined,
    transaction_id: typeof json.transaction_id === "number" ? json.transaction_id : undefined,
    notify_sent: typeof json.notify_sent === "boolean" ? json.notify_sent : undefined,
    rows: Array.isArray(json.rows) ? json.rows : undefined,
    skipped_panel_ids: skipped_panel_ids?.length ? skipped_panel_ids : undefined,
    users: Array.isArray(json.users) ? json.users : undefined,
    billing:
      "billing" in json && json.billing && typeof json.billing === "object"
        ? (json.billing as Record<string, unknown>)
        : undefined,
    invited:
      "invited" in json && json.invited && typeof json.invited === "object"
        ? (json.invited as Record<string, unknown>)
        : undefined,
    unitEconomics: "unitEconomics" in json ? json.unitEconomics : undefined,
    panelEconomics: "panelEconomics" in json ? json.panelEconomics : undefined,
    panelEconomicsMap: "panelEconomicsMap" in json ? json.panelEconomicsMap : undefined,
    checks: "checks" in json ? json.checks : undefined,
  }

  for (const [key, value] of Object.entries(json)) {
    if (!MUTATE_RESULT_KNOWN_KEYS.has(key)) {
      result[key] = value
    }
  }

  return result
}

/**
 * GET helper for dashboard admin REST (Sanctum cookie + CSRF).
 */
/**
 * Download a site-stored backup zip via admin REST (cookie + CSRF).
 */
export async function downloadAdminBackupFile(filename: string): Promise<{ ok: boolean; message?: string }> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = apiBase(boot as Record<string, unknown>)
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const q = new URLSearchParams({ filename })
  const url = `${restBase}${normalizeAdminApiPath("/dashboard/admin/backup/download")}?${q.toString()}`
  const res = await fetch(url, {
    method: "GET",
    headers: apiHeaders(),
    credentials: "include",
  })
  if (!res.ok) {
    try {
      const json = (await res.json()) as { message?: string }
      return { ok: false, message: String(json.message || `http_${res.status}`) }
    } catch {
      return { ok: false, message: `http_${res.status}` }
    }
  }
  const blob = await res.blob()
  const objectUrl = URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = objectUrl
  a.download = filename
  a.rel = "noopener"
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(objectUrl)
  return { ok: true }
}

export async function getAdminJson(path: string, query: Record<string, string | number>): Promise<Record<string, unknown>> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = apiBase(boot as Record<string, unknown>)
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const q = new URLSearchParams()
  for (const [k, v] of Object.entries(query)) {
    q.set(k, String(v))
  }
  const p = normalizeAdminApiPath(path)
  const url = `${restBase}${p}?${q.toString()}`
  const res = await fetch(url, {
    method: "GET",
    headers: apiHeaders(),
    credentials: "include",
  })
  return parseAdminRestJson(res)
}

/**
 * POST helper for dashboard admin REST (Sanctum cookie + CSRF).
 */
export async function postAdminJson(
  path: string,
  body: Record<string, unknown>
): Promise<Record<string, unknown>> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = apiBase(boot as Record<string, unknown>)
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const p = normalizeAdminApiPath(path)
  const res = await fetch(`${restBase}${p}`, {
    method: "POST",
    headers: apiHeaders(),
    credentials: "include",
    body: JSON.stringify(body),
  })
  return parseAdminRestJson(res)
}

/**
 * POST multipart/form-data for dashboard admin REST (file uploads).
 */
export async function postAdminFormData(
  path: string,
  formData: FormData
): Promise<Record<string, unknown>> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = apiBase(boot as Record<string, unknown>)
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const p = normalizeAdminApiPath(path)
  const headers = apiHeaders() as Record<string, string>
  delete headers["Content-Type"]
  const res = await fetch(`${restBase}${p}`, {
    method: "POST",
    headers,
    credentials: "include",
    body: formData,
  })
  return parseAdminRestJson(res)
}
