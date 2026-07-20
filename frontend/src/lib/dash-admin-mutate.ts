import { apiBase, apiHeaders, ensureCsrfCookie } from "@/lib/api"

export type AdminMutateResult = {
  ok: boolean
  code?: string
  message?: string
  reason?: string
  data?: unknown
  [key: string]: unknown
}

async function parseJson(res: Response): Promise<Record<string, unknown>> {
  const text = await res.text()
  if (!text.trim()) {
    return res.ok ? {} : { ok: false, message: `http_${res.status}` }
  }
  try {
    const json = JSON.parse(text) as Record<string, unknown>
    if (!res.ok && typeof json.message !== "string") {
      json.message = `http_${res.status}`
    }
    return json
  } catch {
    return { ok: false, message: `bad_json (${res.status})` }
  }
}

export async function postAdminMutate(
  op: string,
  params: Record<string, unknown> = {}
): Promise<AdminMutateResult> {
  await ensureCsrfCookie()
  const res = await fetch(`${apiBase()}/admin/mutate`, {
    method: "POST",
    credentials: "include",
    headers: apiHeaders(),
    body: JSON.stringify({ op, ...params }),
  })
  const json = await parseJson(res)
  return {
    ...json,
    ok: Boolean(json.ok),
    message: typeof json.message === "string" ? json.message : undefined,
    reason: typeof json.reason === "string" ? json.reason : undefined,
    data: "data" in json ? json.data : undefined,
  }
}

export async function getAdminState(
  activeTab: string,
  query: Record<string, string | number> = {}
): Promise<Record<string, unknown>> {
  await ensureCsrfCookie()
  const qs = new URLSearchParams({ activeTab })
  for (const [k, v] of Object.entries(query)) {
    if (v !== "" && v != null) qs.set(k, String(v))
  }
  const res = await fetch(`${apiBase()}/admin/state?${qs}`, {
    credentials: "include",
    headers: apiHeaders(),
  })
  return parseJson(res)
}

export async function getAdminJson(
  path: string,
  query: Record<string, string | number> = {}
): Promise<Record<string, unknown>> {
  await ensureCsrfCookie()
  const cleanPath = path.startsWith("/") ? path : `/admin/${path.replace(/^admin\//, "")}`
  const qs = new URLSearchParams()
  for (const [k, v] of Object.entries(query)) {
    if (v !== "" && v != null) qs.set(k, String(v))
  }
  const suffix = qs.toString() ? `?${qs}` : ""
  const res = await fetch(`${apiBase()}${cleanPath}${suffix}`, {
    credentials: "include",
    headers: apiHeaders(),
  })
  return parseJson(res)
}

export async function postAdminJson(
  path: string,
  params: Record<string, unknown> = {}
): Promise<Record<string, unknown>> {
  await ensureCsrfCookie()
  const cleanPath = path.startsWith("/") ? path : `/admin/${path.replace(/^admin\//, "")}`
  const res = await fetch(`${apiBase()}${cleanPath}`, {
    method: "POST",
    credentials: "include",
    headers: apiHeaders(),
    body: JSON.stringify(params),
  })
  return parseJson(res)
}

export function adminMutateErrorText(res: AdminMutateResult, fallback: string): string {
  if (res.message && String(res.message).trim()) return String(res.message)
  if (res.reason && String(res.reason).trim()) return String(res.reason)
  return fallback
}

export async function downloadAdminBackupFile(filename: string): Promise<{ ok: boolean; message?: string }> {
  await ensureCsrfCookie()
  const qs = new URLSearchParams({ file: filename })
  const res = await fetch(`${apiBase()}/admin/backup/download?${qs}`, {
    method: "GET",
    credentials: "include",
    headers: apiHeaders(),
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
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(objectUrl)
  return { ok: true }
}

export async function postAdminFormData(
  path: string,
  formData: FormData
): Promise<Record<string, unknown>> {
  await ensureCsrfCookie()
  const cleanPath = path.startsWith("/") ? path : `/admin/${path.replace(/^admin\//, "")}`
  const headers = apiHeaders()
  headers.delete("Content-Type")
  const res = await fetch(`${apiBase()}${cleanPath}`, {
    method: "POST",
    credentials: "include",
    headers,
    body: formData,
  })
  return parseJson(res)
}
