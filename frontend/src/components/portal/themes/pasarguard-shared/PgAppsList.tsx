"use client"

import { useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"

import { UsageChart } from "@/components/portal/components/UsageChart"
import type { AppClient, PortalCard } from "@/components/portal/types"

const PG_PLATFORMS = ["ios", "android", "windows", "linux"] as const

type Props = {
  apps: AppClient[]
  subscriptionUrl: string
}

export function PgAppsList({ apps, subscriptionUrl }: Props) {
  const t = useTranslations("portal")
  const locale = useLocale()
  const [platform, setPlatform] = useState<(typeof PG_PLATFORMS)[number]>("android")
  const available = useMemo(() => {
    const set = new Set(apps.map((app) => app.platform.toLowerCase()))
    return PG_PLATFORMS.filter((p) => set.has(p))
  }, [apps])
  const activePlatform = available.includes(platform) ? platform : available[0] ?? "android"
  const filtered = apps.filter((app) => app.platform.toLowerCase() === activePlatform)

  if (!apps.length) return null

  return (
    <section className="card pg-apps">
      <h2 className="pg-apps__title">
        <span aria-hidden>📱</span> {t("appsTitle")}
      </h2>
      {available.length > 1 ? (
        <div className="pg-apps__tabs" role="tablist">
          {available.map((p) => (
            <button
              key={p}
              type="button"
              role="tab"
              className={activePlatform === p ? "pg-apps__tab active" : "pg-apps__tab"}
              onClick={() => setPlatform(p)}
            >
              {p.toUpperCase()}
            </button>
          ))}
        </div>
      ) : null}
      <ul className="pg-apps__list">
        {filtered.map((app) => {
          const desc = app.description[locale] ?? app.description.en ?? app.name
          const dl = app.download_links[0]?.url ?? ""
          const importUrl = app.import_url
          return (
            <li key={`${app.platform}-${app.name}`} className="pg-apps__item">
              <div className="pg-apps__item-head">
                {app.icon_url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={app.icon_url} alt="" className="pg-apps__icon" />
                ) : null}
                <div>
                  <strong>
                    {app.name}
                    {app.recommended ? <span className="pg-apps__badge">{t("recommended")}</span> : null}
                  </strong>
                  <p className="muted">{desc}</p>
                </div>
              </div>
              <div className="pg-apps__actions">
                {importUrl ? (
                  <a className="btn btn-sm btn-primary" href={importUrl}>
                    {t("import")}
                  </a>
                ) : null}
                {dl ? (
                  <a className="btn btn-sm" href={dl} target="_blank" rel="noreferrer">
                    {t("download")}
                  </a>
                ) : null}
                {!importUrl && subscriptionUrl ? (
                  <span className="muted pg-apps__hint">{t("subscriptionUrl")}</span>
                ) : null}
              </div>
            </li>
          )
        })}
      </ul>
    </section>
  )
}

type ChartProps = {
  usageEndpoint?: string
  authQs?: string
  serviceId?: number
  snapshot?: PortalCard["chart"] | null
}

export function PgTrafficSection({ usageEndpoint, authQs, serviceId, snapshot }: ChartProps) {
  return <UsageChart usageEndpoint={usageEndpoint} authQs={authQs} serviceId={serviceId} snapshot={snapshot} />
}
