#!/usr/bin/env node
/**
 * Scan TSX/TS for leftover demo/placeholder UI strings.
 */
import { readdirSync, readFileSync, statSync } from "node:fs"
import { join, relative } from "node:path"

const root = new URL("..", import.meta.url).pathname
const src = join(root, "src")
const banned = [
  /loginFormDemo/,
  /Upgrade to Pro/,
  /m@example\.com/,
  /Acme Inc/,
  /Login with Google/,
  /placeholder\.svg/,
  /Forgot your password\?/,
  /Don't have an account\?/,
]

function walk(dir, files = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name)
    const st = statSync(p)
    if (st.isDirectory()) walk(p, files)
    else if (/\.(tsx|ts)$/.test(name)) files.push(p)
  }
  return files
}

const hits = []
for (const file of walk(src)) {
  const text = readFileSync(file, "utf8")
  for (const re of banned) {
    if (re.test(text)) {
      hits.push(`${relative(root, file)} matches ${re}`)
    }
  }
}

if (hits.length) {
  console.error("Placeholder/demo remnants found:")
  hits.forEach((h) => console.error(" -", h))
  process.exit(1)
}
console.log("placeholder audit OK")
