"use client"

import { useId } from "react"

import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

function normalizeHex(raw: string): string {
  const value = raw.trim()
  if (!value) return ""
  return value.startsWith("#") ? value : `#${value}`
}

function colorInputValue(value: string, fallback: string): string {
  const hex = normalizeHex(value)
  return /^#[0-9a-fA-F]{6}$/.test(hex) ? hex : fallback
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
  const autoId = useId()
  const { ltrCell } = useDashLocale()
  const id = idProp ?? autoId

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <div className="flex items-center gap-2">
        <Input
          type="color"
          className="h-10 w-14 shrink-0 cursor-pointer p-1"
          value={colorInputValue(value, fallback)}
          onChange={(e) => onChange(e.target.value)}
          aria-label={label}
        />
        <Input
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={fallback}
          dir="ltr"
          className={cn(ltrCell("font-mono"))}
        />
      </div>
    </div>
  )
}
