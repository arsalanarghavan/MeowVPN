import type { PortalInitialData } from "@/components/portal/types"
import { apiOrigin } from "@/lib/api"

type PortalSearchParams = Record<string, string | string[] | undefined>

function first(v: string | string[] | undefined): string {
  if (Array.isArray(v)) return String(v[0] ?? "")
  return v == null ? "" : String(v)
}

function isPortalInitialData(value: unknown): value is PortalInitialData {
  return Boolean(value && typeof value === "object" && "meta" in (value as object))
}

/**
 * Fetch signed portal theme bootstrap from Laravel `GET /info`.
 * Accepts docs params (`uid`/`exp`/`sig`) and legacy (`svp_u`/`svp_e`/`svp_s`).
 */
export async function fetchPortalBootstrap(
  searchParams: PortalSearchParams
): Promise<PortalInitialData | null> {
  const uid = first(searchParams.uid) || first(searchParams.svp_u)
  const exp = first(searchParams.exp) || first(searchParams.svp_e)
  const sig = first(searchParams.sig) || first(searchParams.svp_s)
  if (!uid || !exp || !sig) {
    return null
  }

  const qs = new URLSearchParams()
  qs.set("uid", uid)
  qs.set("exp", exp)
  qs.set("sig", sig)
  qs.set("svp_u", uid)
  qs.set("svp_e", exp)
  qs.set("svp_s", sig)

  const sid =
    first(searchParams.svp_sid) ||
    first(searchParams.service_id) ||
    first(searchParams.sid)
  if (sid) {
    qs.set("svp_sid", sid)
    qs.set("service_id", sid)
  }

  const svpAdm = first(searchParams.svp_adm)
  if (svpAdm) {
    qs.set("svp_adm", svpAdm)
  }

  const theme = first(searchParams.theme)
  if (theme) {
    qs.set("theme", theme)
  }

  try {
    const origin = apiOrigin()
    const res = await fetch(`${origin}/info?${qs.toString()}`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    })
    if (!res.ok) return null
    const json: unknown = await res.json()
    if (!isPortalInitialData(json)) return null
    if ("note" in (json as object) && (json as { note?: string }).note === "portal_html") {
      return null
    }
    return json
  } catch {
    return null
  }
}
