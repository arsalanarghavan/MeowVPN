import type { TFunction } from "i18next"

/** Broadcast row status (svp_broadcasts.status) for dashboard labels. */
export function broadcastRowStatusLabel(st: string, t: TFunction): string {
  const key = `broadcastAdmin.broadcastStatus_${st}`
  const tr = t(key)
  if (tr !== key) return tr
  if (st === "sent") return t("broadcastAdmin.qs_sent")
  if (st === "pending") return t("broadcastAdmin.qs_pending")
  if (st === "sending") return t("broadcastAdmin.qs_sending")
  return st || "—"
}
