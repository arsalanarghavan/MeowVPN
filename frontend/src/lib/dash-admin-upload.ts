import { apiBase, apiHeaders, normalizeAdminApiPath } from "./api-base"

export type MediaUploadResult = { ok: true; url: string } | { ok: false; message: string }

export async function postDashboardMediaUpload(file: File): Promise<MediaUploadResult> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = apiBase(boot as Record<string, unknown>)
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const fd = new FormData()
  fd.append("file", file)
  const headers = apiHeaders() as Record<string, string>
  delete headers["Content-Type"]
  const res = await fetch(`${restBase}${normalizeAdminApiPath("/dashboard/admin/media")}`, {
    method: "POST",
    headers,
    credentials: "include",
    body: fd,
  })
  const text = await res.text()
  let json: Record<string, unknown> = {}
  if (text.trim()) {
    try {
      json = JSON.parse(text) as Record<string, unknown>
    } catch {
      const snippet = text.replace(/\s+/g, " ").trim().slice(0, 120)
      return {
        ok: false,
        message: snippet ? `bad_json (${res.status}: ${snippet})` : `bad_json (${res.status})`,
      }
    }
  } else if (!res.ok) {
    return { ok: false, message: `http_${res.status}` }
  }
  if (!json.ok) {
    return { ok: false, message: typeof json.message === "string" ? json.message : "upload_failed" }
  }
  const url = typeof json.url === "string" ? json.url : ""
  if (!url) return { ok: false, message: "no_url" }
  return { ok: true, url }
}
