import { spawnSync } from "node:child_process"
import { existsSync, mkdirSync } from "node:fs"
import { renderAllNginx, defaultSslPaths, acmeSslPaths } from "./nginx.js"

function run(cmd: string, args: string[]): void {
  const r = spawnSync(cmd, args, { stdio: "inherit" })
  if (r.status !== 0) {
    throw new Error(`${cmd} ${args.join(" ")} failed`)
  }
}

export function issueSslCertbot(domain: string, email: string): void {
  const d = domain.replace(/^https?:\/\//, "").split("/")[0]
  const args = ["certonly", "--nginx", "-d", d, "--agree-tos", "--non-interactive"]
  if (email) args.push("-m", email)
  else args.push("--register-unsafely-without-email")
  run("certbot", args)
  const paths = defaultSslPaths(d)
  renderAllNginx({ domains: [d], sslCert: paths.cert, sslKey: paths.key })
}

export function issueSslAcme(domain: string, email: string): void {
  const d = domain.replace(/^https?:\/\//, "").split("/")[0]
  const home = process.env.HOME || "/root"
  const acme = `${home}/.acme.sh/acme.sh`
  if (!existsSync(acme)) {
    const get = spawnSync("curl", ["-fsSL", "https://get.acme.sh"], { encoding: "utf8" })
    if (get.status !== 0) throw new Error("failed to download acme.sh installer")
    const install = spawnSync("sh", ["-s", `email=${email || "admin@localhost"}`], {
      input: get.stdout,
      stdio: ["pipe", "inherit", "inherit"],
    })
    if (install.status !== 0) throw new Error("acme.sh install failed")
  }
  mkdirSync("/var/www/certbot", { recursive: true })
  run(acme, ["--issue", "-d", d, "--nginx"])
  const paths = acmeSslPaths(d)
  renderAllNginx({ domains: [d], sslCert: paths.cert, sslKey: paths.key })
}

export function renewSsl(method: "certbot" | "acme"): void {
  if (method === "certbot") {
    run("certbot", ["renew", "--quiet"])
  } else {
    const home = process.env.HOME || "/root"
    run(`${home}/.acme.sh/acme.sh`, ["--renew-all"])
  }
}
