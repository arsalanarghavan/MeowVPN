"use client"

import { useId } from "react"

import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

function normalizeHex(raw: string): string {
  const t = raw.trim()
  if (!t) return ""
  return t.startsWith("#") ? t : `#${t}`
}

function hexForColorInput(value: string, fallback: string): string {
  const v = normalizeHex(value)
  if (/^#[0-9a-fA-F]{6}$/.test(v)) return v
  return fallback
}

export function ColorHexField({
  id: idProp,
  label,
  value,
  onChange,
  fallback = "#2563eb",
}: {
  id?: string
  label: string
  value: string
  onChange: (hex: string) => void
  fallback?: string
}) {
  const { ltrCell } = useDashLocale()
  const autoId = useId()
  const id = idProp ?? autoId
  const pickerValue = hexForColorInput(value, fallback)

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <div className={cn("flex items-center gap-2")}>
        <Input
          type="color"
          className="h-10 w-14 shrink-0 cursor-pointer p-1"
          value={pickerValue}
          onChange={(e) => onChange(e.target.value)}
          aria-label={label}
        />
        <Input
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={fallback}
          dir="ltr"
          className={ltrCell("font-mono")}
        />
      </div>
    </div>
  )
}
