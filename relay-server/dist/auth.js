import { findTenantBySecret } from "./store.js";
import { safeEq } from "./util/crypto.js";
import { env } from "./env.js";
export function clientIp(req) {
    const xf = req.headers["x-forwarded-for"];
    if (typeof xf === "string" && xf.trim())
        return xf.split(",")[0].trim();
    return req.socket.remoteAddress || "";
}
export function resolveRelayAuth(secret) {
    if (!secret)
        return null;
    const tenant = findTenantBySecret(secret);
    if (tenant)
        return { tenant, isMaster: false, secret };
    if (env.masterSecret && safeEq(secret, env.masterSecret)) {
        return { tenant: null, isMaster: true, secret };
    }
    return null;
}
export function requireInternalAuth(req, res, next) {
    const hdr = String(req.headers["x-svp-relay-secret"] || "");
    const auth = resolveRelayAuth(hdr);
    if (!auth) {
        res.status(403).json({ ok: false, error: "forbidden" });
        return;
    }
    if (env.allowedLaravelIps.length > 0 && !auth.isMaster) {
        const ip = clientIp(req);
        if (!env.allowedLaravelIps.includes(ip)) {
            res.status(403).json({ ok: false, error: "ip_not_allowed" });
            return;
        }
    }
    req.relayAuth = auth;
    next();
}
export function requireTenant(req, res) {
    if (!req.relayAuth?.tenant) {
        res.status(400).json({ ok: false, error: "tenant_context_required" });
        return false;
    }
    return true;
}
