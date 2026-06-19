import { execSync, spawnSync } from "node:child_process";
const UNIT = "svp-relay";
export function isRoot() {
    return process.getuid?.() === 0;
}
export function systemdActiveState() {
    const r = spawnSync("systemctl", ["is-active", UNIT], { encoding: "utf8" });
    if (r.status === 0)
        return String(r.stdout).trim() || "active";
    const inactive = spawnSync("systemctl", ["is-enabled", UNIT], { encoding: "utf8" });
    if (inactive.status === 0)
        return "inactive";
    return existsUnit() ? "inactive" : "not-installed";
}
function existsUnit() {
    const r = spawnSync("systemctl", ["cat", UNIT], { stdio: "pipe" });
    return r.status === 0;
}
function runSystemctl(action) {
    if (!isRoot()) {
        console.warn("Note: systemctl usually requires root (sudo svp-relay).");
    }
    execSync(`systemctl ${action} ${UNIT}`, { stdio: "inherit", shell: "/bin/bash" });
}
export function serviceStart() {
    runSystemctl("start");
}
export function serviceStop() {
    runSystemctl("stop");
}
export function serviceRestart() {
    runSystemctl("restart");
}
export function serviceStatus() {
    spawnSync("systemctl", ["status", UNIT, "--no-pager"], { stdio: "inherit" });
}
export function tailLogs(lines = 100) {
    spawnSync("journalctl", ["-u", UNIT, "-n", String(lines), "--no-pager"], { stdio: "inherit" });
}
export function followLogs() {
    console.log("Following logs (Ctrl+C to return to panel)...");
    spawnSync("journalctl", ["-u", UNIT, "-f"], { stdio: "inherit" });
}
