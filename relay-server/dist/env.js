import { existsSync } from "node:fs";
import { resolve } from "node:path";
import { loadInstallEnv, resolveInstallRoot } from "./cli/paths.js";
loadInstallEnv();
function workDir() {
    if (process.env.DATA_DIR)
        return resolveInstallRoot();
    const root = resolveInstallRoot();
    if (existsSync(resolve(root, "package.json")) || existsSync(resolve(root, ".env"))) {
        return root;
    }
    return process.cwd();
}
const dataDir = resolve(process.env.DATA_DIR || joinRel(workDir(), "data"));
function parseIpList(raw) {
    return raw
        .split(",")
        .map((s) => s.trim())
        .filter((s) => /^[\d.:a-fA-F]+$/.test(s));
}
export const env = {
    port: Number(process.env.PORT || 8787),
    bindHost: String(process.env.BIND_HOST || "127.0.0.1").trim() || "127.0.0.1",
    /** Per-tenant secrets are stored in tenant files; this is master/legacy fallback. */
    sharedSecret: String(process.env.RELAY_SHARED_SECRET || "").trim(),
    masterSecret: String(process.env.RELAY_MASTER_SECRET || process.env.RELAY_SHARED_SECRET || "").trim(),
    dataDir,
    configPath: resolve(process.env.CONFIG_PATH || joinRel(dataDir, "config.json")),
    tenantsDir: resolve(process.env.TENANTS_DIR || joinRel(dataDir, "tenants")),
    allowedLaravelIps: parseIpList(String(process.env.ALLOWED_LARAVEL_IPS || "")),
    telegramApiBase: String(process.env.TELEGRAM_API_BASE || "https://api.telegram.org").replace(/\/$/, ""),
    forwardMaxRetries: Math.max(1, Number(process.env.FORWARD_MAX_RETRIES || 3)),
    forwardTimeoutMs: Math.max(3000, Number(process.env.FORWARD_TIMEOUT_MS || 25000)),
    nginxConfigPath: String(process.env.NGINX_CONFIG_PATH || "/etc/nginx/sites-available/svp-relay-telegram.conf"),
    nginxTelegramConfigPath: String(process.env.NGINX_TELEGRAM_CONFIG_PATH || "/etc/nginx/sites-available/svp-relay-telegram.conf"),
    nginxAdminConfigPath: String(process.env.NGINX_ADMIN_CONFIG_PATH || "/etc/nginx/sites-available/svp-relay-admin.conf"),
    adminSslCertPath: String(process.env.ADMIN_SSL_CERT || "/etc/svp-relay/ssl/admin-ip.crt"),
    adminSslKeyPath: String(process.env.ADMIN_SSL_KEY || "/etc/svp-relay/ssl/admin-ip.key"),
    relayVersion: String(process.env.RELAY_VERSION || "1.1.0"),
};
function joinRel(base, rel) {
    return resolve(base, rel);
}
