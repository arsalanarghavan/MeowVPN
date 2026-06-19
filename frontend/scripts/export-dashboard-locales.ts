import fs from "node:fs"
import path from "node:path"
import { fileURLToPath } from "node:url"
import { buildDashboardResources } from "../frontend/shared/locales/dashboard.ts"

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const repoRoot = path.resolve(__dirname, "..")

const r = buildDashboardResources()

fs.writeFileSync(
  path.join(repoRoot, "frontend/shared/locales/en.json"),
  `${JSON.stringify(r.en.translation, null, 2)}\n`,
)
fs.writeFileSync(
  path.join(repoRoot, "frontend/shared/locales/fa.json"),
  `${JSON.stringify(r.fa.translation, null, 2)}\n`,
)

console.log("Wrote frontend/shared/locales/en.json and frontend/shared/locales/fa.json")
