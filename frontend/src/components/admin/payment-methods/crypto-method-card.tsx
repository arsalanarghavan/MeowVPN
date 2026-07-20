"use client"

import type { ReactNode } from "react"
import { ExternalLink } from "lucide-react"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"

export function CryptoMethodCard({
  title,
  hint,
  checked,
  onCheckedChange,
  disabled,
  switchId,
  statusLabel,
  statusVariant = "secondary",
  settingsUrl,
  settingsLinkLabel,
  children,
}: {
  title: string
  hint: string
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  disabled?: boolean
  switchId: string
  statusLabel?: string
  statusVariant?: "secondary" | "outline" | "destructive"
  settingsUrl?: string
  settingsLinkLabel?: string
  children?: ReactNode
}) {
  return (
    <Card className={cn("overflow-hidden")}>
      <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-3">
        <div className="min-w-0 flex-1 space-y-2 pe-3">
          <CardTitle className="text-base">{title}</CardTitle>
          <CardDescription className="text-xs leading-snug">{hint}</CardDescription>
          {statusLabel ? (
            <Badge variant={statusVariant} className="text-[10px] font-normal">
              {statusLabel}
            </Badge>
          ) : null}
          {settingsUrl && settingsLinkLabel ? (
            <a
              href={settingsUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex h-auto items-center p-0 text-xs text-primary underline-offset-4 hover:underline"
            >
              {settingsLinkLabel}
              <ExternalLink className="ms-1 inline size-3" aria-hidden />
            </a>
          ) : null}
        </div>
        <Switch
          id={switchId}
          checked={checked}
          onCheckedChange={onCheckedChange}
          disabled={disabled}
        />
        <Label htmlFor={switchId} className="sr-only">
          {title}
        </Label>
      </CardHeader>
      {children ? <CardContent className="space-y-3 border-t pt-4">{children}</CardContent> : null}
    </Card>
  )
}
