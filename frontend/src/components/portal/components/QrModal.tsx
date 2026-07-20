import { QRCodeSVG } from "qrcode.react"
import { t } from "@/components/portal/lib"

type Props = {
  value: string
  onClose: () => void
}

export function QrModal({ value, onClose }: Props) {
  const tooLong = value.length > 2800

  return (
    <div className="qr-modal-backdrop" onClick={onClose} role="presentation">
      <div className="qr-modal" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true">
        <div className="qr-modal__head">
          <h3>{t("qrTitle")}</h3>
          <button type="button" className="icon-btn" onClick={onClose} aria-label={t("closeQr")}>
            ×
          </button>
        </div>
        <div className="qr-modal__body">
          {tooLong ? <p className="muted">Link is too long for QR code</p> : <QRCodeSVG value={value} size={220} level="M" includeMargin />}
        </div>
      </div>
    </div>
  )
}
