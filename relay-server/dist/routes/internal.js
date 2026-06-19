import { Router } from "express";
import { requireInternalAuth, requireTenant } from "../auth.js";
import { collectAllDomains, listTenants, syncTenantDomains, tenantSummary, upsertTenantFromPayload, } from "../store.js";
import { forwardQueueDepth } from "../services/laravel-forward.js";
import { telegramCall } from "../services/telegram.js";
import { mainWebhookUrl, resellerWebhookUrl } from "../util/webhook-url.js";
export const internalRouter = Router();
internalRouter.use(requireInternalAuth);
function activeTenant(req) {
    return req.relayAuth?.tenant || null;
}
internalRouter.get("/internal/health", (req, res) => {
    const tenant = activeTenant(req);
    res.json({
        ok: true,
        uptime_sec: Math.floor(process.uptime()),
        forward_queue_depth: forwardQueueDepth(),
        tenant_id: tenant?.tenant_id || null,
        config_version: tenant?.config_version || "",
        laravel_base_url: tenant?.laravel_base_url || "",
        relay_public_url: tenant?.default_public_url || "",
        updated_at: tenant?.updated_at || null,
    });
});
internalRouter.get("/internal/status", (req, res) => {
    const tenant = activeTenant(req);
    if (req.relayAuth?.isMaster) {
        res.json({
            ok: true,
            uptime_sec: Math.floor(process.uptime()),
            forward_queue_depth: forwardQueueDepth(),
            tenant_count: listTenants().length,
            registered_domains: collectAllDomains(),
            tenants: listTenants().map(tenantSummary),
        });
        return;
    }
    if (!tenant) {
        res.status(400).json({ ok: false, error: "tenant_context_required" });
        return;
    }
    res.json({
        ok: true,
        uptime_sec: Math.floor(process.uptime()),
        forward_queue_depth: forwardQueueDepth(),
        tenant_id: tenant.tenant_id,
        config_version: tenant.config_version,
        laravel_base_url: tenant.laravel_base_url,
        default_public_url: tenant.default_public_url,
        domains: tenant.domains,
        updated_at: tenant.updated_at || null,
        reseller_count: tenant.resellers.length,
    });
});
internalRouter.get("/internal/domains", (req, res) => {
    const tenant = activeTenant(req);
    if (req.relayAuth?.isMaster) {
        res.json({ ok: true, domains: collectAllDomains(), tenants: listTenants().map((t) => ({ tenant_id: t.tenant_id, domains: t.domains })) });
        return;
    }
    if (!tenant) {
        res.status(400).json({ ok: false, error: "tenant_context_required" });
        return;
    }
    res.json({ ok: true, domains: tenant.domains, tenant_id: tenant.tenant_id });
});
internalRouter.post("/internal/domains/sync", (req, res) => {
    if (!requireTenant(req, res))
        return;
    const tenant = activeTenant(req);
    const raw = req.body?.domains;
    const domains = Array.isArray(raw) ? raw.map((d) => String(d)) : [];
    const updated = syncTenantDomains(tenant.tenant_id, domains);
    res.json({ ok: true, domains: updated?.domains || [], tenant_id: tenant.tenant_id });
});
internalRouter.post("/internal/config", (req, res) => {
    const secret = req.relayAuth?.secret || "";
    const body = req.body;
    if (!body || typeof body !== "object" || !body.main) {
        res.status(400).json({ ok: false, error: "invalid_config" });
        return;
    }
    const tenant = upsertTenantFromPayload(secret, body);
    res.json({
        ok: true,
        tenant_id: tenant.tenant_id,
        config_version: tenant.config_version,
        domains: tenant.domains,
    });
});
internalRouter.post("/internal/set-webhook", async (req, res) => {
    if (!requireTenant(req, res))
        return;
    const cfg = activeTenant(req);
    const scope = String(req.body?.scope || "main");
    const rid = Number(req.body?.reseller_svp_user_id || 0);
    const drop = Boolean(req.body?.drop_pending_updates ?? true);
    let token = "";
    let url = "";
    let secretToken = "";
    if (scope === "reseller" && rid > 0) {
        const prof = cfg.resellers.find((r) => r.reseller_svp_user_id === rid);
        if (!prof) {
            res.status(404).json({ ok: false, error: "reseller_not_found" });
            return;
        }
        token = prof.telegram_token;
        url = resellerWebhookUrl(cfg, rid);
        secretToken = prof.telegram_secret_token || "";
    }
    else {
        token = cfg.main.telegram_token;
        url = mainWebhookUrl(cfg);
        secretToken = cfg.main.telegram_secret_header || "";
    }
    if (!token || !url) {
        res.status(400).json({ ok: false, error: "missing_token_or_url" });
        return;
    }
    const params = {
        url,
        allowed_updates: ["message", "callback_query"],
        drop_pending_updates: drop,
    };
    if (secretToken)
        params.secret_token = secretToken;
    const tg = await telegramCall(token, "setWebhook", params);
    res.json({ ok: Boolean(tg.ok), url, response: tg });
});
internalRouter.post("/internal/delete-webhook", async (req, res) => {
    if (!requireTenant(req, res))
        return;
    const cfg = activeTenant(req);
    const scope = String(req.body?.scope || "main");
    const rid = Number(req.body?.reseller_svp_user_id || 0);
    let token = "";
    if (scope === "reseller" && rid > 0) {
        const prof = cfg.resellers.find((r) => r.reseller_svp_user_id === rid);
        if (!prof) {
            res.status(404).json({ ok: false, error: "reseller_not_found" });
            return;
        }
        token = prof.telegram_token;
    }
    else {
        token = cfg.main.telegram_token;
    }
    if (!token) {
        res.status(400).json({ ok: false, error: "missing_token" });
        return;
    }
    const tg = await telegramCall(token, "deleteWebhook", { drop_pending_updates: true });
    res.json({ ok: Boolean(tg.ok), response: tg });
});
internalRouter.post("/internal/diagnostics", async (req, res) => {
    if (!requireTenant(req, res))
        return;
    const cfg = activeTenant(req);
    const scope = String(req.body?.scope || "main");
    const rid = Number(req.body?.reseller_svp_user_id || 0);
    let token = "";
    let expectedUrl = "";
    if (scope === "reseller" && rid > 0) {
        const prof = cfg.resellers.find((r) => r.reseller_svp_user_id === rid);
        if (!prof) {
            res.status(404).json({ ok: false, error: "reseller_not_found" });
            return;
        }
        token = prof.telegram_token;
        expectedUrl = resellerWebhookUrl(cfg, rid);
    }
    else {
        token = cfg.main.telegram_token;
        expectedUrl = mainWebhookUrl(cfg);
    }
    if (!token) {
        res.status(400).json({ ok: false, error: "missing_token" });
        return;
    }
    const me = await telegramCall(token, "getMe");
    const wh = await telegramCall(token, "getWebhookInfo");
    const registered = String(wh.result?.url || "");
    const pending = Number(wh.result?.pending_update_count || 0);
    const lastError = String(wh.result?.last_error_message || "");
    res.json({
        ok: true,
        get_me: me.result || null,
        webhook_info: wh.result || null,
        registered_webhook_url: registered,
        expected_webhook_url: expectedUrl,
        webhook_url_match: registered !== "" && expectedUrl !== "" && registered === expectedUrl,
        pending_update_count: pending,
        last_error_message: lastError,
        relay_health: {
            forward_queue_depth: forwardQueueDepth(),
            laravel_base_url: cfg.laravel_base_url,
            domains: cfg.domains,
        },
    });
});
