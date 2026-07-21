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

export function PgHeroCard({ user }: Props) {
  const t = useTranslations("portal")
  const hideQuota = (user.quota_hidden_from_user ?? 0) === 1
  const pct = hideQuota ? 0 : usedPercent(user.used_traffic, user.data_limit)
  const total = hideQuota ? 0 : user.data_limit
  const used = user.used_traffic
  const remain = total > 0 ? Math.max(0, total - used) : 0

  return (
    <section className="card pg-v2-hero">
      <div className="pg-v2-hero__top">
        <div>
          <p className="muted pg-v2-hero__label">{t("dashboardTitle")}</p>
          <h2 className="pg-v2-hero__user">{user.username}</h2>
        </div>
        <span className={`status-pill status-pill--${user.status === "active" ? "active" : "disabled"}`}>
          {statusText(user.status, t)}
        </span>
      </div>

      <div className="pg-v2-hero__body">
        <div className="pg-v2-ring" aria-hidden>
          <div className="pg-v2-ring__label">
            <strong>{total > 0 ? `${pct}%` : "∞"}</strong>
            <span className="muted">{t("used")}</span>
          </div>
        </div>

        <div className="pg-v2-hero__stats">
          <div>
            <span className="muted">{t("total")}</span>
            <strong>{total > 0 ? formatBytes(total) : t("unlimited")}</strong>
          </div>
          <div>
            <span className="muted">{t("used")}</span>
            <strong>{formatBytes(used)}</strong>
          </div>
          <div>
            <span className="muted">{t("remaining")}</span>
            <strong>{total > 0 ? formatBytes(remain) : t("unlimited")}</strong>
          </div>
          <div>
            <span className="muted">{t("lifetimeTraffic")}</span>
            <strong>{formatBytes(user.lifetime_used_traffic || used)}</strong>
          </div>
        </div>
      </div>
    </section>
  )
}
