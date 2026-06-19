import type { Request } from "express"

export type RelayMainBot = {
  telegram_token: string
  telegram_webhook_secret: string
  telegram_secret_header: string
  telegram_enabled: boolean
  enabled: boolean
  admin_telegram_ids: number[]
}

export type RelayResellerBot = {
  reseller_svp_user_id: number
  telegram_token: string
  webhook_secret: string
  telegram_secret_token: string
  enabled: boolean
  telegram_enabled: boolean
  admin_telegram_ids: number[]
  relay_public_url?: string
}

export type TenantConfig = {
  tenant_id: string
  shared_secret: string
  shared_secret_fingerprint: string
  laravel_base_url: string
  default_public_url: string
  domains: string[]
  main: RelayMainBot
  resellers: RelayResellerBot[]
  config_version: string
  updated_at?: string
}

/** Payload from Laravel POST /internal/config */
export type TenantConfigPayload = {
  tenant_id?: string
  domains?: string[]
  config_version: string
  laravel_base_url?: string
  relay_public_url: string
  main: RelayMainBot
  resellers: RelayResellerBot[]
}

/** Legacy single-site config (migration). */
export type LegacySiteConfig = TenantConfigPayload & {
  updated_at?: string
}

export type RelayAuth = {
  tenant: TenantConfig | null
  isMaster: boolean
  secret: string
}

export type ForwardJob = {
  id: string
  url: string
  body: string
  headers: Record<string, string>
  tries: number
  nextAt: number
}

export type AuthedRequest = Request & { relayAuth?: RelayAuth }
