import { apiBase, apiHeaders, ensureCsrfCookie } from "@/lib/api"

export type MediaUploadResult = { ok: true; url: string } | { ok: false; message: string }

export async function postDashboardMediaUpload(file: File): Promise<MediaUploadResult> {
  await ensureCsrfCookie()
  const fd = new FormData()
  fd.append("file", file)
  const headers = apiHeaders()
  headers.delete("Content-Type")
  const res = await fetch(`${apiBase()}/admin/media`, {
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
      return { ok: false, message: `bad_json (${res.status})` }
    }
  } else if (!res.ok) {
    return { ok: false, message: `http_${res.status}` }
  }
  if (!json.ok) {
    return {
      ok: false,
      message: typeof json.message === "string" ? json.message : "upload_failed",
    }
  }
  const url = typeof json.url === "string" ? json.url : ""
  if (!url) return { ok: false, message: "no_url" }
  return { ok: true, url }
}
