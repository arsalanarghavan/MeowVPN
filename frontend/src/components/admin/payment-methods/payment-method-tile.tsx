"use client"

import { EllipsisVerticalIcon } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { useDashLocale } from "@/lib/dash-locale-context"

export function PaymentMethodTile({
  title,
  hint,
  checked,
  onCheckedChange,
  disabled = false,
  switchId,
  statusLabel,
  statusVariant = "secondary",
  configureLabel,
  docsUrl,
  docsLabel,
  onConfigure,
  showConfigure = true,
}: {
  title: string
  hint: string
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  disabled?: boolean
  switchId: string
  statusLabel?: string
  statusVariant?: "secondary" | "outline" | "destructive"
  configureLabel: string
  docsUrl?: string
  docsLabel?: string
  onConfigure?: () => void
  showConfigure?: boolean
}) {
  const { isFa } = useDashLocale()
  const hasMenu = (showConfigure && onConfigure) || (docsUrl && docsLabel)

  return (
    <Card className="min-h-[9rem]">
      <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
        <div className="min-w-0 flex-1 space-y-1 pe-3">
          <CardTitle className="text-base">{title}</CardTitle>
          <CardDescription className="text-xs leading-snug">{hint}</CardDescription>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <Switch
            id={switchId}
            checked={checked}
            onCheckedChange={onCheckedChange}
            disabled={disabled}
          />
          <Label htmlFor={switchId} className="sr-only">
            {title}
          </Label>
          {hasMenu ? (
            <DropdownMenu>
              <DropdownMenuTrigger className="inline-flex size-8 items-center justify-center rounded-lg hover:bg-muted">
                <EllipsisVerticalIcon className="size-4" />
              </DropdownMenuTrigger>
              <DropdownMenuContent align={isFa ? "start" : "end"}>
                {showConfigure && onConfigure ? (
                  <DropdownMenuItem onClick={onConfigure}>{configureLabel}</DropdownMenuItem>
                ) : null}
                {docsUrl && docsLabel ? (
                  <DropdownMenuItem
                    onClick={() => window.open(docsUrl, "_blank", "noopener,noreferrer")}
                  >
                    {docsLabel}
                  </DropdownMenuItem>
                ) : null}
              </DropdownMenuContent>
            </DropdownMenu>
          ) : null}
        </div>
      </CardHeader>
      {statusLabel ? (
        <CardContent className="pt-0">
          <Badge variant={statusVariant} className="text-[10px] font-normal">
            {statusLabel}
          </Badge>
        </CardContent>
      ) : null}
    </Card>
  )
}
