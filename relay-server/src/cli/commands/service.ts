import { execSync, spawnSync } from "node:child_process"

const UNIT = "svp-relay"

export function isRoot(): boolean {
  return process.getuid?.() === 0
}

export function systemdActiveState(): string {
  const r = spawnSync("systemctl", ["is-active", UNIT], { encoding: "utf8" })
  if (r.status === 0) return String(r.stdout).trim() || "active"
  const inactive = spawnSync("systemctl", ["is-enabled", UNIT], { encoding: "utf8" })
  if (inactive.status === 0) return "inactive"
  return existsUnit() ? "inactive" : "not-installed"
}

function existsUnit(): boolean {
  const r = spawnSync("systemctl", ["cat", UNIT], { stdio: "pipe" })
  return r.status === 0
}

function runSystemctl(action: string): void {
  if (!isRoot()) {
    console.warn("Note: systemctl usually requires root (sudo svp-relay).")
  }
  execSync(`systemctl ${action} ${UNIT}`, { stdio: "inherit", shell: "/bin/bash" })
}

export function serviceStart(): void {
  runSystemctl("start")
}

export function serviceStop(): void {
  runSystemctl("stop")
}

export function serviceRestart(): void {
  runSystemctl("restart")
}

export function serviceStatus(): void {
  spawnSync("systemctl", ["status", UNIT, "--no-pager"], { stdio: "inherit" })
}

export function tailLogs(lines = 100): void {
  spawnSync("journalctl", ["-u", UNIT, "-n", String(lines), "--no-pager"], { stdio: "inherit" })
}

export function followLogs(): void {
  console.log("Following logs (Ctrl+C to return to panel)...")
  spawnSync("journalctl", ["-u", UNIT, "-f"], { stdio: "inherit" })
}
