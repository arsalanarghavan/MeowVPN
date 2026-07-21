"use client"

import Link from "next/link"
import type { ReactNode } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"

export function WalletMethodCard({
  title,
  hint,
  checked,
  onCheckedChange,
  disabled,
  switchId,
  configureLabel,
  onConfigure,
}: {
  title: string
  hint: string
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  disabled?: boolean
  switchId: string
  configureLabel?: string
  onConfigure?: () => void
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
        <div className="min-w-0 flex-1 space-y-1 pe-3">
          <CardTitle className="text-base">{title}</CardTitle>
          <CardDescription className="text-xs leading-snug">{hint}</CardDescription>
        </div>
        <Switch id={switchId} checked={checked} onCheckedChange={onCheckedChange} disabled={disabled} />
        <Label htmlFor={switchId} className="sr-only">
          {title}
        </Label>
      </CardHeader>
      {onConfigure ? (
        <CardContent className="pt-0">
          <Button type="button" variant="outline" size="sm" onClick={onConfigure}>
            {configureLabel ?? "Configure"}
          </Button>
        </CardContent>
      ) : (
        <CardContent className="hidden" />
      )}
    </Card>
  )
}
