"use client"

import type { ComponentProps } from "react"
import { DialogContent, DialogFooter, DialogHeader } from "@/components/ui/dialog"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type DialogContentProps = ComponentProps<typeof DialogContent>

export function DashDialogContent({ className, children, ...props }: DialogContentProps) {
  const { dialogClass } = useDashLocale()
  return (
    <DialogContent className={cn(dialogClass(), className)} {...props}>
      {children}
    </DialogContent>
  )
}

export function DashDialogHeader({ className, ...props }: ComponentProps<typeof DialogHeader>) {
  return <DialogHeader className={cn("text-start", className)} {...props} />
}

export function DashDialogFooter({ className, ...props }: ComponentProps<typeof DialogFooter>) {
  return <DialogFooter className={cn("gap-2", className)} {...props} />
}
