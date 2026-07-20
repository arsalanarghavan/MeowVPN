"use client"

import { useState } from "react"
import { useTranslations } from "next-intl"
import { QrModal } from "@/components/portal/components/QrModal"
import { copyText } from "@/components/portal/lib"

type Props = {
  label: string
  value: string
  badge?: string
  showBase64?: boolean
  showWgDownload?: boolean
}

function CopyIcon() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <rect x="9" y="9" width="13" height="13" rx="2" />
      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
    </svg>
  )
}

function QrIcon() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <rect x="3" y="3" width="7" height="7" />
      <rect x="14" y="3" width="7" height="7" />
      <rect x="3" y="14" width="7" height="7" />
      <path d="M14 14h3v3h-3zM17 17h3v3h-3zM14 20h3" />
    </svg>
  )
}

export function CopyQrRow({ label, value, badge, showBase64, showWgDownload }: Props) {
  const t = useTranslations("portal")
  const [copied, setCopied] = useState(false)
  const [qrOpen, setQrOpen] = useState(false)

  const onCopy = async () => {
    const ok = await copyText(value)
    if (!ok) return
    setCopied(true)
    window.setTimeout(() => setCopied(false), 1500)
  }

  const onCopyBase64 = async () => {
    try {
      const b64 = btoa(unescape(encodeURIComponent(value)))
      const ok = await copyText(b64)
      if (!ok) return
      setCopied(true)
      window.setTimeout(() => setCopied(false), 1500)
    } catch {
      /* ignore */
    }
  }

  return (
    <div className="copy-qr-row">
      <div className="copy-qr-meta">
        {badge ? <span className="badge">{badge}</span> : null}
        <span className="label">{label}</span>
      </div>
      <div className="copy-qr-actions">
        <button type="button" className="icon-btn" onClick={() => void onCopy()} title={t("copyLink")}>
          <CopyIcon />
          <span className="sr-only">{copied ? t("copied") : t("copyLink")}</span>
        </button>
        {showBase64 ? (
          <button type="button" className="icon-btn" onClick={() => void onCopyBase64()} title={t("copyBase64")}>
            B64
          </button>
        ) : null}
        {showWgDownload ? (
          <a
            className="icon-btn"
            href={`data:text/plain;charset=utf-8,${encodeURIComponent(value)}`}
            download="wg.conf"
          >
            WG
          </a>
        ) : null}
        <button type="button" className="icon-btn" onClick={() => setQrOpen(true)} title={t("qrTitle")}>
          <QrIcon />
        </button>
      </div>
      {qrOpen ? <QrModal value={value} onClose={() => setQrOpen(false)} /> : null}
    </div>
  )
}
