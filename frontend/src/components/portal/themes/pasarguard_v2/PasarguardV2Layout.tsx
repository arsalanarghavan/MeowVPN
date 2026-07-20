"use client"

import { useTranslations } from "next-intl"

import { PgAnnounceBanner } from "../pasarguard-shared/PgAnnounceBanner"
import { PgAppsList, PgTrafficSection } from "../pasarguard-shared/PgAppsList"
import { PgHeroCard } from "../pasarguard-shared/PgHeroCard"
import { PgLinksSection } from "../pasarguard-shared/PgLinksSection"
import { PgShell } from "../pasarguard-shared/PgShell"
import { usePgPortal } from "../pasarguard-shared/usePgPortal"

export function PasarguardV2Layout() {
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
  const support = meta.support_url || headers["support-url"]

  return (
    <PgShell themeClass="theme-pg-v2" brandName={brand} tagline={branding.tagline} logo={branding.logo}>
      <PgAnnounceBanner message={headers.announce} url={headers["announce-url"]} />
      <PgHeroCard user={user} />

      <div className="pg-v2-grid">
        <PgLinksSection subUrl={subUrl} items={items} showBase64 showWgDownload prominentSub />
        <PgTrafficSection
          usageEndpoint={meta.usage_endpoint}
          authQs={meta.auth_qs}
          serviceId={serviceId}
          snapshot={chart}
        />
      </div>

      <PgAppsList apps={apps} subscriptionUrl={subUrl} />

      {support ? (
        <footer className="pg-sub-footer">
          <a href={support} target="_blank" rel="noreferrer">
            {t("support")}
          </a>
        </footer>
      ) : null}
    </PgShell>
  )
}
