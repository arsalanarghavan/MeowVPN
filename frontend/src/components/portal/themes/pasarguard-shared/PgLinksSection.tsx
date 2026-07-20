"use client"

import { useTranslations } from "next-intl"

import { protocolFromUri } from "@/components/portal/lib"
import { CopyQrRow } from "./CopyQrRow"

type LinkItem = { uri: string; label: string; key: string }

type Props = {
  subUrl: string
  items: LinkItem[]
  showBase64?: boolean
  showWgDownload?: boolean
  prominentSub?: boolean
}

export function PgLinksSection({
  subUrl,
  items,
  showBase64 = false,
  showWgDownload = true,
  prominentSub = false,
}: Props) {
  const t = useTranslations("portal")

  return (
    <>
      {subUrl ? (
        <section className={`card pg-sub-section ${prominentSub ? "pg-sub-section--prominent" : ""}`}>
          <h2>{t("subscriptionUrl")}</h2>
          {prominentSub ? (
            <p className="pg-prominent-url ltr" dir="ltr">
              {subUrl}
            </p>
          ) : null}
          <CopyQrRow label={t("subscriptionUrl")} value={subUrl} badge="SUB" showBase64={showBase64} />
        </section>
      ) : null}

      {items.length > 0 ? (
        <section className="card pg-sub-section">
          <h2>{t("configs")}</h2>
          {items.map((item) => (
            <CopyQrRow
              key={item.key}
              label={item.label}
              value={item.uri}
              badge={protocolFromUri(item.uri)}
              showBase64={showBase64}
              showWgDownload={showWgDownload}
            />
          ))}
        </section>
      ) : null}
    </>
  )
}
