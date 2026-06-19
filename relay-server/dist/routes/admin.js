import { Router } from "express";
import { execSync, spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { resolve } from "node:path";
import { requireInternalAuth } from "../auth.js";
import { env } from "../env.js";
import { addDomainToTenant, collectAllDomains, listTenants, migrateLegacyConfigIfNeeded, removeDomainFromTenant, tenantSummary, } from "../store.js";
import { forwardQueueDepth } from "../services/laravel-forward.js";
import { getJob, listJobs, runJob } from "../services/admin-jobs.js";
import { renderAllNginx, defaultSslPaths, sslCertExpiry } from "../cli/nginx.js";
import { runDoctor } from "../cli/commands/status.js";
import { systemdActiveState } from "../cli/commands/service.js";
import { resolveInstallRoot } from "../cli/paths.js";
export const adminRouter = Router();
adminRouter.use(requireInternalAuth);
function requireMaster(req, res) {
    if (!req.relayAuth?.isMaster) {
        res.status(403).json({ ok: false, error: "master_required" });
        return false;
    }
    return true;
}
function sanitizeDomain(raw) {
    return String(raw || "")
        .replace(/^https?:\/\//, "")
        .split("/")[0]
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9.-]/g, "");
}
function sanitizeIpList(ips) {
    return ips.map((ip) => String(ip).trim()).filter((ip) => /^[\d.:a-fA-F]+$/.test(ip));
}
function shell(cmd) {
    try {
        const out = execSync(cmd, { encoding: "utf8", stdio: ["pipe", "pipe", "pipe"] });
        return { ok: true, output: out };
    }
    catch (e) {
        const err = e;
        return { ok: false, output: String(err.stderr || err.stdout || err.message || "failed") };
    }
}
adminRouter.get("/internal/admin/dashboard", async (req, res) => {
    if (!requireMaster(req, res))
        return;
    migrateLegacyConfigIfNeeded();
    const domains = collectAllDomains();
    const sslStatus = domains.map((d) => {
        const p = defaultSslPaths(d);
        return { domain: d, cert: p.cert, exists: existsSync(p.cert), expires: sslCertExpiry(p.cert) };
    });
    let nginxOk = null;
    try {
        nginxOk = spawnSync("nginx", ["-t"], { stdio: "pipe" }).status === 0;
    }
    catch {
        nginxOk = false;
    }
    res.json({
        ok: true,
        version: env.relayVersion,
        uptime_sec: Math.floor(process.uptime()),
        forward_queue_depth: forwardQueueDepth(),
        tenant_count: listTenants().length,
        tenants: listTenants().map(tenantSummary),
        domains,
        systemd: systemdActiveState(),
        nginx_ok: nginxOk,
        ssl: sslStatus,
        install_root: resolveInstallRoot(),
        allowed_laravel_ips: env.allowedLaravelIps,
    });
});
adminRouter.get("/internal/admin/install-info", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const fp = env.masterSecret ? env.masterSecret.slice(0, 8) + "…" : "";
    res.json({
        ok: true,
        secret_fingerprint: fp,
        install_root: resolveInstallRoot(),
        admin_ssl_cert: env.adminSslCertPath,
        nginx_telegram: env.nginxTelegramConfigPath,
        nginx_admin: env.nginxAdminConfigPath,
    });
});
adminRouter.post("/internal/admin/domains/add", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const domain = sanitizeDomain(String(req.body?.domain || ""));
    const tenantId = String(req.body?.tenant_id || listTenants()[0]?.tenant_id || "");
    if (!domain || !tenantId) {
        res.status(400).json({ ok: false, error: "domain_and_tenant_required" });
        return;
    }
    const t = addDomainToTenant(tenantId, domain);
    if (req.body?.render_nginx !== false)
        renderAllNginx();
    res.json({ ok: true, domains: t?.domains || [], tenant_id: tenantId });
});
adminRouter.post("/internal/admin/domains/remove", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const domain = sanitizeDomain(String(req.body?.domain || ""));
    const tenantId = String(req.body?.tenant_id || listTenants()[0]?.tenant_id || "");
    if (!domain || !tenantId) {
        res.status(400).json({ ok: false, error: "domain_and_tenant_required" });
        return;
    }
    const t = removeDomainFromTenant(tenantId, domain);
    if (req.body?.render_nginx !== false)
        renderAllNginx();
    res.json({ ok: true, domains: t?.domains || [] });
});
adminRouter.post("/internal/admin/nginx/render", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const ipSource = Array.isArray(req.body?.ips) ? req.body.ips : undefined;
    const paths = renderAllNginx({
        domains: Array.isArray(req.body?.domains)
            ? req.body.domains.map((d) => sanitizeDomain(String(d))).filter(Boolean)
            : undefined,
        laravelIps: ipSource ? sanitizeIpList(ipSource.map(String)) : undefined,
    });
    res.json({ ok: true, ...paths });
});
adminRouter.post("/internal/admin/nginx/test", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const r = shell("sudo nginx -t 2>&1");
    res.json({ ok: r.ok, output: r.output });
});
adminRouter.post("/internal/admin/nginx/reload", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const test = shell("sudo nginx -t 2>&1");
    if (!test.ok) {
        res.status(400).json({ ok: false, error: "nginx_test_failed", output: test.output });
        return;
    }
    const r = shell("sudo systemctl reload nginx 2>&1");
    res.json({ ok: r.ok, output: r.output });
});
adminRouter.get("/internal/admin/ssl/status", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const domains = collectAllDomains();
    const certs = domains.map((d) => {
        const p = defaultSslPaths(d);
        return { domain: d, ...p, exists: existsSync(p.cert), expires: sslCertExpiry(p.cert) };
    });
    res.json({
        ok: true,
        admin: {
            cert: env.adminSslCertPath,
            key: env.adminSslKeyPath,
            exists: existsSync(env.adminSslCertPath),
            expires: sslCertExpiry(env.adminSslCertPath),
        },
        domains: certs,
    });
});
adminRouter.post("/internal/admin/ssl/issue", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const domain = String(req.body?.domain || "");
    const email = String(req.body?.email || "");
    const method = String(req.body?.method || "certbot");
    if (!domain) {
        res.status(400).json({ ok: false, error: "domain_required" });
        return;
    }
    const runner = resolve(resolveInstallRoot(), "dist/cli/ssl-runner.js");
    const job = runJob("ssl_issue", "sudo", ["node", runner, "issue", domain, method, email]);
    res.json({ ok: true, job_id: job.id, status: job.status });
});
adminRouter.post("/internal/admin/ssl/renew", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const method = String(req.body?.method || "certbot");
    const runner = resolve(resolveInstallRoot(), "dist/cli/ssl-runner.js");
    const job = runJob("ssl_renew", "sudo", ["node", runner, "renew", method]);
    res.json({ ok: true, job_id: job.id, status: job.status });
});
adminRouter.post("/internal/admin/service/restart", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const r = shell("sudo systemctl restart svp-relay 2>&1");
    res.json({ ok: r.ok, output: r.output });
});
adminRouter.get("/internal/admin/logs", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const lines = Math.min(500, Math.max(10, Number(req.query.lines || 100)));
    const r = shell(`journalctl -u svp-relay -n ${lines} --no-pager 2>&1`);
    res.json({ ok: true, lines, output: r.output });
});
adminRouter.get("/internal/admin/doctor", (req, res) => {
    if (!requireMaster(req, res))
        return;
    res.json({ ok: true, checks: runDoctor() });
});
adminRouter.post("/internal/admin/update", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const script = resolve(resolveInstallRoot(), "scripts/update-from-github.sh");
    if (!existsSync(script)) {
        res.status(500).json({ ok: false, error: "update_script_missing" });
        return;
    }
    const job = runJob("update", "sudo", ["bash", script]);
    res.json({ ok: true, job_id: job.id, status: job.status });
});
adminRouter.get("/internal/admin/jobs/:id", (req, res) => {
    if (!requireMaster(req, res))
        return;
    const job = getJob(String(req.params.id));
    if (!job) {
        res.status(404).json({ ok: false, error: "job_not_found" });
        return;
    }
    res.json({ ok: true, job });
});
adminRouter.get("/internal/admin/jobs", (req, res) => {
    if (!requireMaster(req, res))
        return;
    res.json({ ok: true, jobs: listJobs().slice(0, 20) });
});
