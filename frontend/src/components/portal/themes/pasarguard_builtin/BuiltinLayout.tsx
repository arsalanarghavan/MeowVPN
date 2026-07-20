"use client"

import { useTranslations } from "next-intl"

import { formatBytes } from "@/components/portal/lib"
import { PgAnnounceBanner } from "../pasarguard-shared/PgAnnounceBanner"
import { PgAppsList } from "../pasarguard-shared/PgAppsList"
import { PgLinksSection } from "../pasarguard-shared/PgLinksSection"
import { PgShell } from "../pasarguard-shared/PgShell"
import { PgUserCard } from "../pasarguard-shared/PgUserCard"
import { usePgPortal } from "../pasarguard-shared/usePgPortal"

export function PasarguardBuiltinLayout() {
  const t = useTranslations("portal")
  const { user, meta, branding, headers, subUrl, items, apps, chart } = usePgPortal()

  if (!user) {
    return (
      <div className="shell">
        <p className="empty">{t("noService")}</p>
      </div>
    )
  }

  const brand = branding.name || user.username
  const support = meta.support_url || headers["support-url"]
  const used = chart?.used ?? user.used_traffic
  const total = chart?.total ?? user.data_limit
  const remaining = total > 0 ? Math.max(0, total - used) : 0

  return (
    <PgShell themeClass="theme-pg-builtin" brandName={brand} tagline={branding.tagline} logo={branding.logo}>
      <div className="pg-builtin-container card">
        <h1 className="pg-builtin-title">{t("subscriptionInfo")}</h1>
        <PgAnnounceBanner message={headers.announce} url={headers["announce-url"]} />

        <div className="pg-builtin-user">
          <PgUserCard user={user} />
          <div className="pg-builtin-info-row">
            <span className="pg-builtin-info-label">{t("total")}:</span>
            <span>{total > 0 ? formatBytes(total) : t("unlimited")}</span>
          </div>
          <div className="pg-builtin-info-row">
            <span className="pg-builtin-info-label">{t("used")}:</span>
            <span>{formatBytes(used)}</span>
          </div>
          <div className="pg-builtin-info-row">
            <span className="pg-builtin-info-label">{t("remaining")}:</span>
            <span>{total > 0 ? formatBytes(remaining) : t("unlimited")}</span>
          </div>
        </div>

        <section className="pg-builtin-links">
          <h2>{t("subscriptionLinks")}</h2>
          <PgLinksSection subUrl={subUrl} items={items} showWgDownload />
        </section>

        <section className="pg-builtin-apps">
          <PgAppsList apps={apps} subscriptionUrl={subUrl} />
        </section>
      </div>

      {support ? (
        <footer className="pg-sub-footer">
          <a href={support} target="_blank" rel="noreferrer">
            {t("support")}
          </a>
        </footer>
      ) : null}

      {brand ? <p className="pg-builtin-brand muted">{brand}</p> : null}
    </PgShell>
  )
}
