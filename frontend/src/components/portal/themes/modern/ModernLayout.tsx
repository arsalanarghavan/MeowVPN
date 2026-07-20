"use client"

import { useTranslations } from "next-intl"

import { PgAnnounceBanner } from "../pasarguard-shared/PgAnnounceBanner"
import { PgAppsList, PgTrafficSection } from "../pasarguard-shared/PgAppsList"
import { PgLinksSection } from "../pasarguard-shared/PgLinksSection"
import { PgShell } from "../pasarguard-shared/PgShell"
import { PgUserCard } from "../pasarguard-shared/PgUserCard"
import { usePgPortal } from "../pasarguard-shared/usePgPortal"

export function ModernLayout() {
  const t = useTranslations("portal")
  const { user, meta, branding, headers, subUrl, items, apps, serviceId, chart } = usePgPortal()

  if (!user) {
    return (
      <div className="shell">
        <p className="empty">{t("noService")}</p>
      </div>
    )
  }

  const brand = branding.name || user.username

  return (
    <PgShell themeClass="theme-modern" brandName={brand} tagline={branding.tagline} logo={branding.logo}>
      <PgAnnounceBanner message={headers.announce} url={headers["announce-url"]} />
      <div className="pg-v1-grid">
        <PgUserCard user={user} />
        <PgTrafficSection
          usageEndpoint={meta.usage_endpoint}
          authQs={meta.auth_qs}
          serviceId={serviceId}
          snapshot={chart}
        />
      </div>
      <div className="pg-v1-grid">
        <PgLinksSection subUrl={subUrl} items={items} showWgDownload />
      </div>
      <PgAppsList apps={apps} subscriptionUrl={subUrl} />
      {meta.support_url ? (
        <footer className="pg-sub-footer">
          <a href={meta.support_url} target="_blank" rel="noreferrer">
            {t("support")}
          </a>
        </footer>
      ) : null}
    </PgShell>
  )
}
