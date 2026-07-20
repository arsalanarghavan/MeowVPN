import { apiBase } from "@/lib/api-base"

const TOKEN_KEY = "svp_install_token"

export function readInstallToken(): string {
  if (typeof window === "undefined") return ""
  const fromQuery = new URLSearchParams(window.location.search).get("token")
  if (fromQuery && fromQuery.trim() !== "") {
    sessionStorage.setItem(TOKEN_KEY, fromQuery.trim())
    return fromQuery.trim()
  }
  return sessionStorage.getItem(TOKEN_KEY) || ""
}

export function setupApiBase(): string {
  return apiBase()
}

function setupHeaders(json = true): HeadersInit {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Install-Token": readInstallToken(),
  }
  if (json) {
    headers["Content-Type"] = "application/json"
  }
  return headers
}

export type SetupStatus = {
  ok?: boolean
  pending?: boolean
  completed?: boolean
  open?: boolean
  dashboard_login_url?: string
}

export type DomainProbe = {
  key: string
  label: string
  url: string
  probe_url?: string
  dns_ok?: boolean
  http_ok?: boolean
  ok?: boolean
  status_code?: number
  http_error?: string
  dns_error?: string
  hints?: string[]
}

export async function fetchSetupStatus(): Promise<SetupStatus> {
  const res = await fetch(`${setupApiBase()}/setup/status`, { credentials: "omit" })
  return (await res.json()) as SetupStatus
}

export async function fetchSetupDomains(): Promise<{
  ok?: boolean
  urls?: Record<string, string>
  probes?: DomainProbe[]
  snapshot?: Record<string, string>
  host_reconfigure_required?: boolean
  reinstall_hint?: string
}> {
  const res = await fetch(`${setupApiBase()}/setup/domains`, {
    headers: setupHeaders(),
    credentials: "omit",
  })
  return (await res.json()) as Record<string, unknown>
}

export async function updateSetupDomains(body: Record<string, string>): Promise<Record<string, unknown>> {
  const res = await fetch(`${setupApiBase()}/setup/domains`, {
    method: "POST",
    headers: setupHeaders(),
    credentials: "omit",
    body: JSON.stringify(body),
  })
  return (await res.json()) as Record<string, unknown>
}

export async function probeSetupDomains(): Promise<{ ok?: boolean; probes?: DomainProbe[] }> {
  const res = await fetch(`${setupApiBase()}/setup/domains/probe`, {
    method: "POST",
    headers: setupHeaders(),
    credentials: "omit",
  })
  return (await res.json()) as { ok?: boolean; probes?: DomainProbe[] }
}

export async function registerSetupWebhooks(): Promise<Record<string, unknown>> {
  const res = await fetch(`${setupApiBase()}/setup/domains/register-webhooks`, {
    method: "POST",
    headers: setupHeaders(),
    credentials: "omit",
    body: JSON.stringify({ platform: "both" }),
  })
  return (await res.json()) as Record<string, unknown>
}

export async function restoreMeowvpnBackup(file: File, restorePanelDb: boolean): Promise<Record<string, unknown>> {
  const fd = new FormData()
  fd.append("file", file)
  fd.append("confirm", "1")
  if (restorePanelDb) {
    fd.append("restore_panel_db", "1")
  }
  const res = await fetch(`${setupApiBase()}/setup/backup/restore`, {
    method: "POST",
    headers: { Accept: "application/json", "X-Install-Token": readInstallToken() },
    credentials: "omit",
    body: fd,
  })
  return (await res.json()) as Record<string, unknown>
}

export async function importWordpressBackup(
  file: File,
  opts: { dryRun?: boolean; force?: boolean }
): Promise<Record<string, unknown>> {
  const fd = new FormData()
  fd.append("file", file)
  if (opts.dryRun) fd.append("dry_run", "1")
  if (opts.force) fd.append("force", "1")
  const res = await fetch(`${setupApiBase()}/setup/backup/wordpress`, {
    method: "POST",
    headers: { Accept: "application/json", "X-Install-Token": readInstallToken() },
    credentials: "omit",
    body: fd,
  })
  return (await res.json()) as Record<string, unknown>
}

export async function saveSetupAdminCredentials(body: {
  username: string
  password: string
  password_confirm: string
}): Promise<Record<string, unknown>> {
  const res = await fetch(`${setupApiBase()}/setup/admin-credentials`, {
    method: "POST",
    headers: setupHeaders(),
    credentials: "omit",
    body: JSON.stringify(body),
  })
  return (await res.json()) as Record<string, unknown>
}

export async function completeSetupWizard(): Promise<{ ok?: boolean; dashboard_login_url?: string }> {
  const res = await fetch(`${setupApiBase()}/setup/complete`, {
    method: "POST",
    headers: setupHeaders(),
    credentials: "omit",
  })
  return (await res.json()) as { ok?: boolean; dashboard_login_url?: string }
}
