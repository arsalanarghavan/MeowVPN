"use client"

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
}: {
  title: string
  hint: string
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  disabled?: boolean
  switchId: string
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
        <div className="min-w-0 flex-1 space-y-1 pe-3">
          <CardTitle className="text-base">{title}</CardTitle>
          <CardDescription className="text-xs leading-snug">{hint}</CardDescription>
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
      <CardContent className="hidden" />
    </Card>
  )
}
