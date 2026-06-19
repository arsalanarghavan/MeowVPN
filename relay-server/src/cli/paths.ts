import { existsSync, readFileSync } from "node:fs"
import { resolve, dirname } from "node:path"
import { fileURLToPath } from "node:url"

let cachedRoot: string | null = null

/** Package / install root (e.g. /opt/svp-relay). */
export function resolveInstallRoot(): string {
  if (cachedRoot) return cachedRoot
  const fromEnv = process.env.SVP_RELAY_DIR || process.env.INSTALL_DIR
  if (fromEnv) {
    cachedRoot = resolve(fromEnv)
    return cachedRoot
  }
  cachedRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..")
  return cachedRoot
}

function applyDotEnvFile(path: string): void {
  if (!existsSync(path)) return
  const raw = readFileSync(path, "utf8")
  for (const line of raw.split("\n")) {
    const t = line.trim()
    if (!t || t.startsWith("#")) continue
    const i = t.indexOf("=")
    if (i < 1) continue
    const k = t.slice(0, i).trim()
    let v = t.slice(i + 1).trim()
    if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
      v = v.slice(1, -1)
    }
    if (!(k in process.env)) process.env[k] = v
  }
}

/** Load .env from cwd, then install root if keys are still missing. */
export function loadInstallEnv(): void {
  applyDotEnvFile(resolve(process.cwd(), ".env"))
  applyDotEnvFile(resolve(resolveInstallRoot(), ".env"))
}

export function pkgRoot(): string {
  return resolveInstallRoot()
}
