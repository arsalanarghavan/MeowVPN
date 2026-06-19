import { Router } from "express";
import { proxyBotMethod } from "../services/telegram.js";
export const botProxyRouter = Router();
botProxyRouter.post(/^\/bot([^/]+)\/(.+)$/, async (req, res) => {
    const m = req.path.match(/^\/bot([^/]+)\/(.+)$/);
    if (!m) {
        res.status(404).json({ ok: false, description: "not_found" });
        return;
    }
    const token = decodeURIComponent(m[1]);
    const rest = m[2];
    const raw = req.body;
    const body = Buffer.isBuffer(raw) ? raw : typeof raw === "string" ? Buffer.from(raw) : Buffer.from(JSON.stringify(raw ?? {}));
    const ct = String(req.headers["content-type"] || "application/json");
    try {
        const upstream = await proxyBotMethod(token, rest, body, ct);
        const text = await upstream.text();
        res.status(upstream.status);
        const uct = upstream.headers.get("content-type");
        if (uct)
            res.setHeader("content-type", uct);
        res.send(text);
    }
    catch {
        res.status(502).json({ ok: false, description: "upstream_error" });
    }
});
