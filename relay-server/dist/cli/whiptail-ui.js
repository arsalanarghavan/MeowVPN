import { spawnSync } from "node:child_process";
import { execSync } from "node:child_process";
import { existsSync } from "node:fs";
import { renderAllNginx, defaultSslPaths } from "./nginx.js";
import { issueSslCertbot, renewSsl } from "./ssl.js";
import { formatDoctor, formatStatusSnapshot, getStatusSnapshot, runDoctor } from "./commands/status.js";
import { domainAdd, domainRemove, domainsListText } from "./commands/domains.js";
import { followLogs, serviceRestart, serviceStart, serviceStatus, serviceStop, tailLogs } from "./commands/service.js";
import { tenantsListText, tenantShowText, tenantChoices } from "./commands/tenants.js";
import { laravelSetupGuide } from "./commands/laravel-guide.js";
const BACKTITLE = "SimpleVPBot Relay";
const PURPLE_THEME = `
screen_color = (COLOR_PAIR((7)), COLOR_PAIR((0)))
dialog_color = (COLOR_PAIR((7)), COLOR_PAIR((5)))
title_color = (COLOR_PAIR((7)), COLOR_PAIR((5)))
button_active_color = (COLOR_PAIR((15)), COLOR_PAIR((5)))
button_inactive_color = (COLOR_PAIR((7)), COLOR_PAIR((4)))
`;
function hasWhiptail() {
    return spawnSync("which", ["whiptail"], { stdio: "pipe" }).status === 0;
}
function wtArgs() {
    process.env.NEWTHEME = PURPLE_THEME;
    return ["--backtitle", BACKTITLE, "--notags"];
}
function menu(title, items) {
    if (!hasWhiptail())
        return items[0]?.[0] ?? null;
    const args = ["whiptail", ...wtArgs(), "--title", title, "--menu", "Choose:", "18", "70", "10", ...items.flat()];
    const r = spawnSync(args[0], args.slice(1), { encoding: "utf8", stdio: ["inherit", "pipe", "pipe"] });
    if (r.status !== 0)
        return null;
    return String(r.stdout).trim();
}
function msgbox(text) {
    if (!hasWhiptail()) {
        console.log(text);
        return;
    }
    spawnSync("whiptail", [...wtArgs(), "--title", BACKTITLE, "--msgbox", text, "20", "70"], { stdio: "inherit" });
}
function inputbox(prompt, defaultVal = "") {
    if (!hasWhiptail())
        return defaultVal || null;
    const r = spawnSync("whiptail", [...wtArgs(), "--title", BACKTITLE, "--inputbox", prompt, "10", "70", defaultVal], { encoding: "utf8", stdio: ["inherit", "pipe", "pipe"] });
    if (r.status !== 0)
        return null;
    return String(r.stdout).trim();
}
function yesno(text) {
    if (!hasWhiptail())
        return false;
    return spawnSync("whiptail", [...wtArgs(), "--title", BACKTITLE, "--yesno", text, "10", "70"], { stdio: "inherit" }).status === 0;
}
async function dashMenu() {
    const snap = await getStatusSnapshot();
    msgbox(formatStatusSnapshot(snap));
}
function domainsMenu() {
    const c = menu("Domains", [
        ["list", "List domains"],
        ["add", "Add domain"],
        ["remove", "Remove domain"],
        ["back", "Back"],
    ]);
    if (!c || c === "back")
        return;
    if (c === "list")
        msgbox(domainsListText());
    else if (c === "add") {
        const d = inputbox("Domain hostname:");
        if (d) {
            const r = domainAdd(d);
            msgbox(r.ok ? JSON.stringify(r) : String(r.error));
        }
    }
    else if (c === "remove") {
        const d = inputbox("Domain to remove:");
        if (d && yesno(`Remove ${d}?`)) {
            const r = domainRemove(d);
            msgbox(JSON.stringify(r));
        }
    }
}
function sslMenu() {
    const c = menu("SSL", [
        ["issue", "Issue certificate"],
        ["renew", "Renew"],
        ["paths", "Cert paths"],
        ["back", "Back"],
    ]);
    if (!c || c === "back")
        return;
    if (c === "renew") {
        try {
            renewSsl("certbot");
        }
        catch (e) {
            msgbox(String(e));
        }
    }
    else if (c === "paths") {
        const d = inputbox("Domain:");
        if (d) {
            const p = defaultSslPaths(d);
            msgbox(`Cert: ${p.cert}\nKey: ${p.key}\nExists: ${existsSync(p.cert)}`);
        }
    }
    else if (c === "issue") {
        const d = inputbox("Domain:");
        const e = inputbox("Email (optional):") || "";
        if (d) {
            try {
                issueSslCertbot(d, e);
                renderAllNginx();
                execSync("sudo nginx -t && sudo systemctl reload nginx", { stdio: "inherit", shell: "/bin/bash" });
            }
            catch (err) {
                msgbox(String(err));
            }
        }
    }
}
function nginxMenu() {
    const c = menu("Nginx", [
        ["render", "Render all configs"],
        ["test", "nginx -t"],
        ["reload", "Reload nginx"],
        ["back", "Back"],
    ]);
    if (!c || c === "back")
        return;
    if (c === "render") {
        const p = renderAllNginx();
        msgbox(`Telegram: ${p.telegram}\nAdmin: ${p.admin}`);
    }
    else if (c === "test") {
        try {
            execSync("sudo nginx -t", { stdio: "inherit", shell: "/bin/bash" });
        }
        catch (e) {
            msgbox(String(e));
        }
    }
    else if (c === "reload") {
        try {
            execSync("sudo nginx -t && sudo systemctl reload nginx", { stdio: "inherit", shell: "/bin/bash" });
        }
        catch (e) {
            msgbox(String(e));
        }
    }
}
function serviceMenu() {
    const c = menu("Service", [
        ["status", "Status"],
        ["start", "Start"],
        ["stop", "Stop"],
        ["restart", "Restart"],
        ["back", "Back"],
    ]);
    if (!c || c === "back")
        return;
    try {
        if (c === "status")
            serviceStatus();
        else if (c === "start")
            serviceStart();
        else if (c === "stop")
            serviceStop();
        else if (c === "restart")
            serviceRestart();
    }
    catch (e) {
        msgbox(String(e));
    }
}
function tenantsMenu() {
    const c = menu("Tenants", [
        ["list", "List"],
        ["show", "Show details"],
        ["back", "Back"],
    ]);
    if (!c || c === "back")
        return;
    if (c === "list")
        msgbox(tenantsListText());
    else if (c === "show") {
        const choices = tenantChoices();
        if (!choices.length) {
            msgbox("No tenants");
            return;
        }
        const id = menu("Pick tenant", choices.map((x) => [x.value, x.name]));
        if (id)
            msgbox(tenantShowText(id) || "not found");
    }
}
function logsMenu() {
    const c = menu("Logs", [
        ["tail", "Tail 100"],
        ["follow", "Follow"],
        ["back", "Back"],
    ]);
    if (!c || c === "back")
        return;
    if (c === "tail")
        tailLogs(100);
    else if (c === "follow")
        followLogs();
}
export async function runWhiptailPanel(runInstall) {
    if (!hasWhiptail() && !process.stdin.isTTY) {
        console.log("svp-relay: no TTY — use subcommands or svp-relay help");
        return;
    }
    for (;;) {
        const choice = menu("Main Menu", [
            ["dashboard", "Dashboard"],
            ["service", "Service"],
            ["tenants", "Tenants"],
            ["domains", "Domains"],
            ["ssl", "SSL"],
            ["nginx", "Nginx"],
            ["laravel", "Laravel setup"],
            ["logs", "Logs"],
            ["doctor", "Doctor"],
            ["install", "Install / update"],
            ["quit", "Exit"],
        ]);
        if (!choice || choice === "quit")
            return;
        if (choice === "dashboard")
            await dashMenu();
        else if (choice === "service")
            serviceMenu();
        else if (choice === "tenants")
            tenantsMenu();
        else if (choice === "domains")
            domainsMenu();
        else if (choice === "ssl")
            sslMenu();
        else if (choice === "nginx")
            nginxMenu();
        else if (choice === "laravel")
            msgbox(laravelSetupGuide(yesno("Reveal master secret?")));
        else if (choice === "logs")
            logsMenu();
        else if (choice === "doctor")
            msgbox(formatDoctor(runDoctor()));
        else if (choice === "install" && yesno("Run install.sh?"))
            runInstall([]);
    }
}
