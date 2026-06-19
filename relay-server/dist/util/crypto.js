import { createHash, randomUUID, timingSafeEqual } from "node:crypto";
export function secretFingerprint(secret) {
    return createHash("sha256").update(secret, "utf8").digest("hex");
}
export function safeEq(a, b) {
    if (!a || !b)
        return false;
    const ab = Buffer.from(a);
    const bb = Buffer.from(b);
    if (ab.length !== bb.length)
        return false;
    return timingSafeEqual(ab, bb);
}
export function newTenantId() {
    return randomUUID();
}
