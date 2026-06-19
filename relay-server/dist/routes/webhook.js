import { Router } from "express";
import { findMainWebhook, findResellerWebhook } from "../store.js";
import { enqueueForward } from "../services/laravel-forward.js";
import { hostFromUrl, laravelForwardBase, publicBaseForReseller } from "../util/webhook-url.js";
export const webhookRouter = Router();
function pickForwardHeaders(req) {
    const out = {};
    const tg = req.headers["x-telegram-bot-api-secret-token"];
    if (typeof tg === "string" && tg)
        out["x-telegram-bot-api-secret-token"] = tg;
    const svp = req.headers["x-svp-webhook-secret"];
    if (typeof svp === "string" && svp)
        out["x-svp-webhook-secret"] = svp;
    return out;
}
function warnHostMismatch(req, expectedPublicUrl) {
    const reqHost = String(req.headers.host || "")
        .split(":")[0]
        .toLowerCase();
    const expHost = hostFromUrl(expectedPublicUrl.startsWith("http") ? expectedPublicUrl : `https://${expectedPublicUrl}`);
    if (expHost && reqHost && expHost !== reqHost) {
        console.warn(`[relay] webhook Host mismatch: got ${reqHost}, expected ${expHost}`);
    }
}
webhookRouter.post("/webhook/telegram/:secret", (req, res) => {
    const secret = String(req.params.secret || "");
    const match = findMainWebhook(secret);
    if (!match) {
        res.status(403).json({ ok: false });
        return;
    }
    const { tenant } = match;
    const hdr = String(req.headers["x-telegram-bot-api-secret-token"] || "");
    const exp = String(tenant.main.telegram_secret_header || "");
    if (exp && hdr !== exp) {
        res.status(403).json({ ok: false });
        return;
    }
    const base = laravelForwardBase(tenant);
    if (!base) {
        res.status(503).json({ ok: false });
        return;
    }
    warnHostMismatch(req, tenant.default_public_url);
    const body = typeof req.body === "string" ? req.body : JSON.stringify(req.body ?? {});
    const url = `${base}/api/v1/webhook/telegram/${encodeURIComponent(secret)}`;
    enqueueForward(url, body, pickForwardHeaders(req));
    res.status(200).json({ ok: true });
});
webhookRouter.post("/webhook/telegram/reseller/:rid/:secret", (req, res) => {
    const rid = Number(req.params.rid);
    const secret = String(req.params.secret || "");
    const match = findResellerWebhook(rid, secret);
    if (!match) {
        res.status(403).json({ ok: false });
        return;
    }
    const { tenant, prof } = match;
    const hdr = String(req.headers["x-telegram-bot-api-secret-token"] || "");
    const exp = String(prof.telegram_secret_token || "");
    if (exp && hdr !== exp) {
        res.status(403).json({ ok: false });
        return;
    }
    const base = laravelForwardBase(tenant);
    if (!base) {
        res.status(503).json({ ok: false });
        return;
    }
    warnHostMismatch(req, publicBaseForReseller(tenant, prof));
    const body = typeof req.body === "string" ? req.body : JSON.stringify(req.body ?? {});
    const url = `${base}/api/v1/webhook/telegram/reseller/${rid}/${encodeURIComponent(secret)}`;
    enqueueForward(url, body, pickForwardHeaders(req));
    res.status(200).json({ ok: true });
});
