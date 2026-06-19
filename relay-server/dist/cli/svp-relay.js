#!/usr/bin/env node
import { spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { resolve } from "node:path";
import { loadInstallEnv, pkgRoot } from "./paths.js";
import { migrateLegacyConfigIfNeeded } from "../store.js";
import { issueSslAcme, issueSslCertbot, renewSsl } from "./ssl.js";
import { domainAdd, domainRemove, domainsList } from "./commands/domains.js";
import { statusJson, runDoctor } from "./commands/status.js";
import { tenantShowJson, tenantsListText } from "./commands/tenants.js";
import { runWhiptailPanel } from "./whiptail-ui.js";
loadInstallEnv();
const argv = process.argv.slice(2);
const cmd = argv[0] || "";
const sub = argv[1];
function flag(name) {
    const i = argv.indexOf(name);
    return i >= 0 ? argv[i + 1] : undefined;
}
function runInstall(extraArgs) {
    const script = resolve(pkgRoot(), "scripts/install.sh");
    if (!existsSync(script)) {
        console.error("install.sh not found");
        process.exit(1);
    }
    const r = spawnSync("bash", [script, ...extraArgs], { stdio: "inherit" });
    if (r.status !== 0)
        process.exit(r.status ?? 1);
}
function help() {
    console.log(`svp-relay — SimpleVPBot Telegram relay

Interactive control panel (default):
  svp-relay
  svp-relay panel

Usage:
  svp-relay install [install.sh flags]
  svp-relay status
  svp-relay tenants list
  svp-relay tenant show <id>
  svp-relay domains list
  svp-relay domain add <domain> [--tenant id]
  svp-relay domain remove <domain> [--tenant id]
  svp-relay nginx render [--out path]
  svp-relay ssl issue <domain> [--method certbot|acme] [--email addr]
  svp-relay ssl renew [--method certbot|acme]
  svp-relay config migrate
  svp-relay doctor
  svp-relay help
`);
}
async function main() {
    if (!cmd || cmd === "panel") {
        await runWhiptailPanel((args) => runInstall(args));
        return;
    }
    if (cmd === "help" || cmd === "--help" || cmd === "-h") {
        help();
        return;
    }
    switch (cmd) {
        case "install":
            runInstall(argv.slice(1));
            break;
        case "status":
            console.log(JSON.stringify(statusJson(), null, 2));
            break;
        case "tenants":
            if (sub === "list")
                console.log(tenantsListText());
            else
                help();
            break;
        case "tenant":
            if (sub === "show" && argv[2]) {
                const data = tenantShowJson(argv[2]);
                if (!data) {
                    console.error("tenant not found");
                    process.exit(1);
                }
                console.log(JSON.stringify(data, null, 2));
            }
            else
                help();
            break;
        case "domains":
            if (sub === "list")
                console.log(domainsList().join("\n"));
            else
                help();
            break;
        case "domain":
            if (sub === "add" && argv[2]) {
                const r = domainAdd(argv[2], flag("--tenant"));
                if (!r.ok) {
                    console.error(r.error);
                    process.exit(1);
                }
                console.log(JSON.stringify(r));
            }
            else if (sub === "remove" && argv[2]) {
                const r = domainRemove(argv[2], flag("--tenant"));
                if (!r.ok) {
                    console.error(r.error);
                    process.exit(1);
                }
                console.log(JSON.stringify(r));
            }
            else
                help();
            break;
        case "nginx":
            if (sub === "render") {
                const { renderAllNginx } = await import("./nginx.js");
                const paths = renderAllNginx();
                console.log(JSON.stringify(paths));
            }
            else
                help();
            break;
        case "ssl":
            if (sub === "issue" && argv[2]) {
                const method = (flag("--method") || "certbot");
                const email = flag("--email") || "";
                if (method === "acme")
                    issueSslAcme(argv[2], email);
                else
                    issueSslCertbot(argv[2], email);
                try {
                    const test = spawnSync("nginx", ["-t"], { stdio: "inherit" });
                    if (test.status === 0)
                        spawnSync("systemctl", ["reload", "nginx"], { stdio: "inherit" });
                }
                catch {
                    console.warn("nginx reload skipped (run manually)");
                }
            }
            else if (sub === "renew") {
                renewSsl((flag("--method") || "certbot"));
            }
            else
                help();
            break;
        case "config":
            if (sub === "migrate") {
                console.log(JSON.stringify({ migrated: migrateLegacyConfigIfNeeded() }));
            }
            else
                help();
            break;
        case "doctor":
            console.log(JSON.stringify(runDoctor(), null, 2));
            break;
        default:
            help();
    }
}
main().catch((err) => {
    console.error(err);
    process.exit(1);
});
