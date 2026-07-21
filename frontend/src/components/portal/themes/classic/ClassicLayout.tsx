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

export function ClassicLayout() {
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
  const pillClass =
    user.status.toLowerCase() === "active"
      ? "svp-pill--ok"
      : user.status.toLowerCase() === "expired" || user.status.toLowerCase() === "limited"
        ? "svp-pill--bad"
        : "svp-pill--muted"

  return (
    <div className="shell theme-classic svp-shell">
      <header className="svp-header-brand">
        <div>
          <strong>{brand}</strong>
          {branding.tagline ? <p className="svp-header-tagline muted">{branding.tagline}</p> : null}
        </div>
        <ThemeToggle mode={mode} onChange={setMode} />
      </header>

      <article className="svp-card">
        <header className="svp-card__head">
          <span className="svp-chip">{t("subscriptionLink")}</span>
          <span className="svp-subid">{user.username}</span>
          <span className={`svp-pill ${pillClass}`}>{statusText}</span>
          <span className={statusClass(user.status)} style={{ display: "none" }} aria-hidden>
            {statusText}
          </span>
        </header>

        {subUrl ? (
          <div className="svp-qr">
            <CopyQrRow label={t("protocolSub")} value={subUrl} badge="SUB" />
          </div>
        ) : null}

        <dl className="svp-rows">
          <div className="svp-row">
            <dt>{t("downloadTraffic")}</dt>
            <dd className="ltr">{formatBytes(down)}</dd>
          </div>
          <div className="svp-row">
            <dt>{t("upload")}</dt>
            <dd className="ltr">{formatBytes(up)}</dd>
          </div>
          <div className="svp-row">
            <dt>{t("usedTraffic")}</dt>
            <dd className="ltr">{formatBytes(used)}</dd>
          </div>
          <div className="svp-row">
            <dt>{t("totalQuota")}</dt>
            <dd className="ltr">{total > 0 ? formatBytes(total) : t("unlimited")}</dd>
          </div>
          <div className="svp-row">
            <dt>{t("dataRemaining")}</dt>
            <dd className="ltr">{total > 0 ? formatBytes(remaining) : t("unlimited")}</dd>
          </div>
          <div className="svp-row">
            <dt>{t("expireDate")}</dt>
            <dd className="ltr">{formatPortalDate(user.expire, t("unlimited"))}</dd>
          </div>
          {total > 0 ? (
            <div className="svp-row">
              <dt>{t("usedPercent")}</dt>
              <dd className="ltr">{pct.toLocaleString("fa-IR")}%</dd>
            </div>
          ) : null}
        </dl>

        {items.length > 0 ? (
          <div className="svp-apps">
            <h2>{t("configLinks")}</h2>
            {items.map((item) => (
              <div key={item.key} className="svp-cfg">
                <span className="svp-cfg__tag">{item.label}</span>
                <CopyQrRow label={item.label} value={item.uri} badge={protocolFromUri(item.uri)} />
              </div>
            ))}
          </div>
        ) : null}
      </article>

      {meta.support_url ? (
        <footer className="svp-sub-footer">
          <a href={meta.support_url} target="_blank" rel="noreferrer">
            {t("support")}
          </a>
        </footer>
      ) : null}
    </div>
  )
}
