"use client"

import type { ComponentProps } from "react"

import { SheetContent } from "@/components/ui/sheet"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type SheetContentProps = ComponentProps<typeof SheetContent>

export function DashSheetContent({
  className,
  side,
  children,
  ...props
}: SheetContentProps) {
  const { sheetSide, dialogClass } = useDashLocale()
  return (
    <SheetContent
      side={side ?? sheetSide}
      className={cn(dialogClass(), className)}
      {...props}
    >
      {children}
    </SheetContent>
  )
}
