import { collectAllDomains, listTenants } from "../../store.js";
import { env } from "../../env.js";
import { resolveInstallRoot } from "../paths.js";
export function laravelSetupGuide(showSecret) {
    const domains = collectAllDomains();
    const primary = domains[0] || "relay.example.com";
    const relayUrl = `https://${primary.replace(/^https?:\/\//, "").split("/")[0]}`;
    const tenant = listTenants()[0];
    const lines = [
        "── Laravel relay setup ──",
        "",
        "1. Dashboard → Site settings → Telegram relay",
        "2. Enable relay and set Relay public URL:",
        `   ${relayUrl}`,
        "3. Set Shared secret to the master secret below (must match VPS .env)",
    ];
    if (showSecret && env.masterSecret) {
        lines.push("", "RELAY_MASTER_SECRET:", env.masterSecret);
    }
    else if (env.masterSecret) {
        lines.push("", "RELAY_MASTER_SECRET: (hidden — confirm to reveal in panel)");
    }
    else {
        lines.push("", "RELAY_MASTER_SECRET: (not set in .env)");
    }
    lines.push("", "4. Save settings, then click **Sync config** (stores tenant_id on Laravel)", "5. Click **Sync domains** on Laravel, then add domains on VPS if needed", "6. Click **Register webhook via relay** for main bot", "7. Resellers: optional per-bot relay URL → sync → set webhook", "", `Install dir: ${resolveInstallRoot()}`, `Tenant on relay: ${tenant?.tenant_id || "(none — sync from Laravel first)"}`, `Laravel URL: ${tenant?.laravel_base_url || "n/a"}`, `Webhook path example: ${relayUrl}/webhook/telegram/<secret>`);
    return lines.join("\n");
}
