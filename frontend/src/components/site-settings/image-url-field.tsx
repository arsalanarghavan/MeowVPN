"use client"

import { useCallback, useId, useRef, useState } from "react"
import { useTranslations } from "next-intl"

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
  const autoId = useId()
  const fileRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const { ltrCell } = useDashLocale()
  const t = useTranslations("siteSettings.whitelabel")
  const id = idProp ?? autoId

  const onPickFile = useCallback(
    async (files: FileList | null) => {
      const file = files?.item(0)
      if (!file) return
      setUploading(true)
      try {
        const res = await postDashboardMediaUpload(file)
        if (!res.ok) {
          onUploadError?.(res.message || t("uploadError"))
          return
        }
        onChange(res.url)
      } finally {
        setUploading(false)
        if (fileRef.current) fileRef.current.value = ""
      }
    },
    [onChange, onUploadError, t]
  )

  const preview = value.trim()

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <div className="flex flex-wrap items-start gap-2">
        {preview ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={preview}
            alt={t("imagePreview")}
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
            className={cn(ltrCell("font-mono"))}
          />
          <div className="flex gap-2">
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
              {uploading ? t("uploading") : t("upload")}
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
