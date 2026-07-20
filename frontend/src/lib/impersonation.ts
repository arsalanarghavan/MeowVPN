import { apiBase, apiHeaders, ensureCsrfCookie, normalizeAdminApiPath } from "@/lib/api"

export async function startImpersonation(svpUserId: number): Promise<Response> {
  await ensureCsrfCookie()
  return fetch(`${apiBase()}${normalizeAdminApiPath("/dashboard/impersonate/start")}`, {
    method: "POST",
    credentials: "include",
    headers: apiHeaders(),
    body: JSON.stringify({ targetSvpUserId: svpUserId }),
  })
}

export async function stopImpersonation(): Promise<Response> {
  await ensureCsrfCookie()
  return fetch(`${apiBase()}${normalizeAdminApiPath("/dashboard/impersonate/stop")}`, {
    method: "POST",
    credentials: "include",
    headers: apiHeaders(),
  })
}
