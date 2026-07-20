"use client"

import { useMemo } from "react"
import { useTranslations } from "next-intl"

import { ThemeToggle } from "@/components/portal/components/ThemeToggle"
import { useTheme } from "@/components/portal/hooks/useTheme"
import { formatBytes, labelFromUri, protocolFromUri, statusClass, usedPercent } from "@/components/portal/lib"
import { resolveTrafficStats } from "@/components/portal/lib/traffic-stats"
import { formatPortalDate } from "@/components/portal/lib/dateFormat"
import { getInitialData } from "@/components/portal/types"
import { CopyQrRow } from "../pasarguard-shared/CopyQrRow"

export function XuiLayout() {
  const t = useTranslations("portal")
  const data = getInitialData()
  const user = data.user
  const meta = data.meta ?? {}
  const branding = meta.branding ?? {}
  const links = data.links ?? []
  const linkItems = data.link_items ?? data.cards?.[0]?.link_items ?? []
  const subUrl = meta.subscription_url ?? ""
  const { mode, setMode } = useTheme()

  const items = useMemo(() => {
    if (linkItems.length > 0) {
      return linkItems.map((item, i) => ({
        uri: item.uri,
        label: item.label || labelFromUri(item.uri),
        key: `item-${i}`,
      }))
    }
    return links.map((uri, i) => ({
      uri,
      label: labelFromUri(uri),
      key: `link-${i}`,
    }))
  }, [linkItems, links])

  if (!user) {
    return (
      <div className="shell">
        <p className="empty">{t("noService")}</p>
      </div>
    )
  }

  const stats = resolveTrafficStats(user, data.chart)
  const { total, used, down, up, remaining } = stats
  const pct = usedPercent(used, total)
  const brand = branding.name || user.username
  const statusText = (() => {
    switch (user.status.toLowerCase()) {
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
        return user.status
    }
  })()

  return (
    <div className="shell theme-xui">
      <header className="xui-header">
        <div>
          <h1 className="xui-title">{brand}</h1>
          {branding.tagline ? <p className="xui-sub muted">{branding.tagline}</p> : null}
        </div>
        <ThemeToggle mode={mode} onChange={setMode} />
      </header>

      <div className="xui-status-row">
        <span className={statusClass(user.status)}>{statusText}</span>
        <span className="muted">{user.username}</span>
      </div>

      <div className="xui-stats-grid">
        <div className="xui-stat-card">
          <span className="xui-stat-label">{t("downloadTraffic")}</span>
          <strong>{formatBytes(down)}</strong>
        </div>
        <div className="xui-stat-card">
          <span className="xui-stat-label">{t("upload")}</span>
          <strong>{formatBytes(up)}</strong>
        </div>
        <div className="xui-stat-card">
          <span className="xui-stat-label">{t("totalQuota")}</span>
          <strong>{total > 0 ? formatBytes(total) : t("unlimited")}</strong>
        </div>
        <div className="xui-stat-card">
          <span className="xui-stat-label">{t("dataRemaining")}</span>
          <strong>{total > 0 ? formatBytes(remaining) : t("unlimited")}</strong>
        </div>
        <div className="xui-stat-card">
          <span className="xui-stat-label">{t("usedTraffic")}</span>
          <strong>{formatBytes(used)}</strong>
        </div>
        <div className="xui-stat-card">
          <span className="xui-stat-label">{t("expireDate")}</span>
          <strong>{formatPortalDate(user.expire, t("unlimited"))}</strong>
        </div>
      </div>

      {total > 0 ? (
        <div className="xui-progress">
          <div className="xui-progress__bar" style={{ width: `${pct}%` }} />
          <span className="xui-progress__label">
            {pct.toLocaleString("fa-IR")}% {t("usedPercent")}
          </span>
        </div>
      ) : null}

      {subUrl ? (
        <section className="xui-section card">
          <h2>{t("subscriptionLink")}</h2>
          <CopyQrRow label={t("protocolSub")} value={subUrl} badge="SUB" />
        </section>
      ) : null}

      {items.length > 0 ? (
        <section className="xui-section card">
          <h2>{t("configLinks")}</h2>
          {items.map((item) => (
            <CopyQrRow
              key={item.key}
              label={item.label}
              value={item.uri}
              badge={protocolFromUri(item.uri)}
            />
          ))}
        </section>
      ) : null}

      {meta.support_url ? (
        <footer className="xui-footer">
          <a href={meta.support_url} target="_blank" rel="noreferrer" className="support-link">
            {t("support")}
          </a>
        </footer>
      ) : null}
    </div>
  )
}
