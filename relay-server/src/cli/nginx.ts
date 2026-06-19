import { execSync } from "node:child_process"
import { readFileSync, writeFileSync, existsSync, mkdirSync } from "node:fs"
import { dirname, resolve } from "node:path"
import { fileURLToPath } from "node:url"
import { collectAllDomains } from "../store.js"
import { env } from "../env.js"

const tplDir = resolve(dirname(fileURLToPath(import.meta.url)), "../../templates")

function applyTemplate(file: string, vars: Record<string, string>): string {
  let tpl = readFileSync(resolve(tplDir, file), "utf8")
  for (const [k, v] of Object.entries(vars)) {
    tpl = tpl.replace(new RegExp(`\\{\\{${k}\\}\\}`, "g"), v)
  }
  return tpl
}

function writeConfig(out: string, content: string): string {
  mkdirSync(dirname(out), { recursive: true })
  writeFileSync(out, content, { mode: 0o644 })
  return out
}

function sanitizeIps(ips: string[]): string[] {
  return ips.map((ip) => String(ip).trim()).filter((ip) => /^[\d.:a-fA-F]+$/.test(ip))
}

function buildAllowRules(laravelIps: string[]): string {
  const ips = sanitizeIps(laravelIps)
  if (!ips.length) return "allow all;"
  return ips.map((ip) => `allow ${ip};`).join("\n    ") + "\n    deny all;"
}

export function renderNginxTelegram(opts?: {
  domains?: string[]
  port?: number
  sslCert?: string
  sslKey?: string
  outPath?: string
}): string {
  const domains = (opts?.domains?.length ? opts.domains : collectAllDomains()).join(" ")
  const first = (opts?.domains?.[0] || collectAllDomains()[0] || "DOMAIN").replace(/^https?:\/\//, "").split("/")[0]
  const sslCert = opts?.sslCert || `/etc/letsencrypt/live/${first}/fullchain.pem`
  const sslKey = opts?.sslKey || `/etc/letsencrypt/live/${first}/privkey.pem`
  const content = applyTemplate("nginx-telegram.conf", {
    DOMAINS: domains || "_",
    PORT: String(opts?.port ?? env.port),
    SSL_CERT: sslCert,
    SSL_KEY: sslKey,
  })
  const out = opts?.outPath || env.nginxTelegramConfigPath
  return writeConfig(out, content)
}

export function renderNginxAdmin(opts?: {
  laravelIps?: string[]
  port?: number
  adminCert?: string
  adminKey?: string
  outPath?: string
}): string {
  const laravelIps = opts?.laravelIps ?? env.allowedLaravelIps
  const content = applyTemplate("nginx-admin.conf", {
    PORT: String(opts?.port ?? env.port),
    ADMIN_SSL_CERT: opts?.adminCert || env.adminSslCertPath,
    ADMIN_SSL_KEY: opts?.adminKey || env.adminSslKeyPath,
    ALLOW_RULES: buildAllowRules(laravelIps),
  })
  const out = opts?.outPath || env.nginxAdminConfigPath
  return writeConfig(out, content)
}

/** Render both telegram + admin vhosts. */
export function renderAllNginx(opts?: {
  domains?: string[]
  sslCert?: string
  sslKey?: string
  laravelIps?: string[]
  telegramOut?: string
  adminOut?: string
}): { telegram: string; admin: string } {
  const telegram = renderNginxTelegram({
    domains: opts?.domains,
    sslCert: opts?.sslCert,
    sslKey: opts?.sslKey,
    outPath: opts?.telegramOut,
  })
  const admin = renderNginxAdmin({ laravelIps: opts?.laravelIps, outPath: opts?.adminOut })
  return { telegram, admin }
}

/** @deprecated use renderNginxTelegram or renderAllNginx */
export function renderNginxConfig(opts?: {
  domains?: string[]
  port?: number
  sslCert?: string
  sslKey?: string
  outPath?: string
}): string {
  renderNginxAdmin()
  return renderNginxTelegram(opts)
}

export function defaultSslPaths(domain: string): { cert: string; key: string } {
  const d = domain.replace(/^https?:\/\//, "").split("/")[0]
  return {
    cert: `/etc/letsencrypt/live/${d}/fullchain.pem`,
    key: `/etc/letsencrypt/live/${d}/privkey.pem`,
  }
}

export function acmeSslPaths(domain: string): { cert: string; key: string } {
  const d = domain.replace(/^https?:\/\//, "").split("/")[0]
  const home = process.env.HOME || "/root"
  return {
    cert: `${home}/.acme.sh/${d}_ecc/fullchain.cer`,
    key: `${home}/.acme.sh/${d}_ecc/${d}.key`,
  }
}

export function sslCertExpiry(certPath: string): string | null {
  if (!existsSync(certPath)) return null
  try {
    const out = execSync(`openssl x509 -enddate -noout -in ${JSON.stringify(certPath)}`, { encoding: "utf8" })
    const m = out.match(/notAfter=(.+)/)
    return m ? m[1].trim() : null
  } catch {
    return null
  }
}
