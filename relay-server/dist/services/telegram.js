import { env } from "../env.js";
export async function telegramCall(token, method, params = {}, timeoutMs = 25000) {
    const url = `${env.telegramApiBase}/bot${encodeURIComponent(token)}/${method}`;
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
        const res = await fetch(url, {
            method: "POST",
            headers: { "content-type": "application/json" },
            body: JSON.stringify(params),
            signal: ctrl.signal,
        });
        return (await res.json());
    }
    finally {
        clearTimeout(t);
    }
}
export async function proxyBotMethod(token, methodPath, reqBody, contentType) {
    const safeToken = encodeURIComponent(token);
    const path = methodPath.replace(/^\/+/, "");
    const url = `${env.telegramApiBase}/bot${safeToken}/${path}`;
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), 60000);
    try {
        const res = await fetch(url, {
            method: "POST",
            headers: { "content-type": contentType || "application/json" },
            body: typeof reqBody === "string" ? reqBody : new Uint8Array(reqBody),
            signal: ctrl.signal,
        });
        const text = await res.text();
        return new Response(text, {
            status: res.status,
            headers: { "content-type": res.headers.get("content-type") || "application/json" },
        });
    }
    finally {
        clearTimeout(t);
    }
}
