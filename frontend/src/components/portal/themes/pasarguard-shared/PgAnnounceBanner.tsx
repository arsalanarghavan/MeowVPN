"use client"

import { useTranslations } from "next-intl"

type Props = {
  message?: string
  url?: string
}

export function PgAnnounceBanner({ message, url }: Props) {
  const t = useTranslations("portal")
  const text = (message ?? "").trim()

  if (!text && !url) return null

  return (
    <section className="card pg-announce">
      <div className="pg-announce__icon" aria-hidden>
        🔔
      </div>
      <div className="pg-announce__body">
        <strong>{t("announcement")}</strong>
        {text ? <p>{text}</p> : null}
        {url ? (
          <a href={url} target="_blank" rel="noreferrer" className="pg-announce__link">
            {t("viewAnnouncement")}
          </a>
        ) : null}
      </div>
    </section>
  )
}
