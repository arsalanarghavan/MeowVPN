"use client"

import { useCallback, useId, useRef, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { postDashboardMediaUpload } from "@/lib/dash-admin-upload"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

export function ImageUrlField({
  id: idProp,
  label,
  value,
  onChange,
  placeholder = "https://",
  onUploadError,
}: {
  id?: string
  label: string
  value: string
  onChange: (url: string) => void
  placeholder?: string
  onUploadError?: (message: string) => void
}) {
  const { ltrCell } = useDashLocale()
  const { t } = useTranslation()
  const tp = (k: string) => t(`siteSettings.whitelabel.${k}`)
  const autoId = useId()
  const id = idProp ?? autoId
  const fileRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)

  const onPickFile = useCallback(
    async (files: FileList | null) => {
      const f = files?.item(0)
      if (!f) return
      setUploading(true)
      try {
        const r = await postDashboardMediaUpload(f)
        if (!r.ok) {
          onUploadError?.(r.message || tp("uploadError"))
          return
        }
        onChange(r.url)
      } finally {
        setUploading(false)
        if (fileRef.current) fileRef.current.value = ""
      }
    },
    [onChange, onUploadError, tp]
  )

  const preview = value.trim()

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <div className={cn("flex flex-wrap items-start gap-2")}>
        {preview ? (
          <img
            src={preview}
            alt={tp("imagePreview")}
            className="size-12 shrink-0 rounded-md border object-cover"
          />
        ) : null}
        <div className="min-w-0 flex-1 space-y-2">
          <Input
            id={id}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            dir="ltr"
            className={ltrCell("font-mono")}
          />
          <div className={cn("flex gap-2")}>
            <input
              ref={fileRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={(e) => void onPickFile(e.target.files)}
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={uploading}
              onClick={() => fileRef.current?.click()}
            >
              {uploading ? tp("uploading") : tp("upload")}
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
