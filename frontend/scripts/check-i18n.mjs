#!/usr/bin/env node
/**
 * Fail if en/fa message trees diverge (missing keys either side).
 */
import { readFileSync } from "node:fs"
import { resolve, dirname } from "node:path"
import { fileURLToPath } from "node:url"

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..")

function flatten(obj, prefix = "", out = new Set()) {
  if (obj && typeof obj === "object" && !Array.isArray(obj)) {
    for (const [k, v] of Object.entries(obj)) {
      flatten(v, prefix ? `${prefix}.${k}` : k, out)
    }
  } else if (prefix) {
    out.add(prefix)
  }
  return out
}

const en = flatten(JSON.parse(readFileSync(resolve(root, "messages/en.json"), "utf8")))
const fa = flatten(JSON.parse(readFileSync(resolve(root, "messages/fa.json"), "utf8")))

const missingInFa = [...en].filter((k) => !fa.has(k)).sort()
const missingInEn = [...fa].filter((k) => !en.has(k)).sort()

if (missingInFa.length || missingInEn.length) {
  console.error("i18n key mismatch")
  if (missingInFa.length) {
    console.error("Missing in fa:", missingInFa.slice(0, 50).join("\n"), missingInFa.length > 50 ? `…(+${missingInFa.length - 50})` : "")
  }
  if (missingInEn.length) {
    console.error("Missing in en:", missingInEn.slice(0, 50).join("\n"), missingInEn.length > 50 ? `…(+${missingInEn.length - 50})` : "")
  }
  process.exit(1)
}

console.log(`i18n OK — ${en.size} keys in en/fa`)
