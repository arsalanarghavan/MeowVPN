"use client"

import { Plus } from "lucide-react"

import { Card } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"

export function AddCardTile({
  label,
  onClick,
  disabled = false,
  comingSoonLabel,
}: {
  label: string
  onClick?: () => void
  disabled?: boolean
  comingSoonLabel?: string
}) {
  const inner = (
    <>
      <Plus className="size-8 text-muted-foreground" aria-hidden />
      <span className="text-sm font-medium text-muted-foreground">{label}</span>
      {disabled && comingSoonLabel ? (
        <Badge variant="secondary" className="text-[10px]">
          {comingSoonLabel}
        </Badge>
      ) : null}
    </>
  )

  if (disabled) {
    return (
      <Card
        className={cn(
          "flex min-h-[9rem] flex-col items-center justify-center gap-2 border-dashed bg-muted/20 opacity-70"
        )}
        aria-disabled
      >
        {inner}
      </Card>
    )
  }

  return (
    <button
      type="button"
      onClick={onClick}
      className="min-h-[9rem] w-full rounded-xl border border-dashed border-border/80 bg-muted/10 transition-colors hover:border-primary/40 hover:bg-muted/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      <span className="flex h-full flex-col items-center justify-center gap-2 px-4 py-6">{inner}</span>
    </button>
  )
}
