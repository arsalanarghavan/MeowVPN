"use client"

import { useTranslations } from "next-intl"

import type { PortalUser } from "@/components/portal/types"
import { formatBytes, usedPercent } from "@/components/portal/lib"

type Props = {
  user: PortalUser
}

function statusText(status: string, t: (key: string) => string): string {
  switch (status.toLowerCase()) {
    case "active":
      return t("statusActive")
    case "disabled":
      return t("statusDisabled")
    case "expired":
      return t("statusExpired")
    case "limited":
      return t("statusLimited")
    case "on_hold":
      return t("statusOnHold")
    default:
      return status
  }
}

export function PgUserCard({ user }: Props) {
  const t = useTranslations("portal")
  const total = user.data_limit
  const used = user.used_traffic
  const remain = total > 0 ? Math.max(0, total - used) : 0
  const pct = usedPercent(used, total)

  return (
    <div className="pg-sub-user card">
      <div className="pg-sub-user__top">
        <strong>{user.username}</strong>
        <span className={`status-pill status-pill--${user.status === "active" ? "active" : "disabled"}`}>
          {statusText(user.status, t)}
        </span>
      </div>
      <div className="pg-sub-metrics">
        <div>
          <span className="muted">{t("used")}</span>
          <strong>{formatBytes(used)}</strong>
        </div>
        <div>
          <span className="muted">{t("total")}</span>
          <strong>{total > 0 ? formatBytes(total) : t("unlimited")}</strong>
        </div>
        <div>
          <span className="muted">{t("remaining")}</span>
          <strong>{total > 0 ? formatBytes(remain) : t("unlimited")}</strong>
        </div>
        <div>
          <span className="muted">{t("usageTitle")}</span>
          <strong>{`${pct}%`}</strong>
        </div>
      </div>
    </div>
  )
}
