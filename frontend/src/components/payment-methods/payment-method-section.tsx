"use client"

import type { ReactNode } from "react"

import { cn } from "@/lib/utils"

export function PaymentMethodSection({
  title,
  description,
  children,
  className,
}: {
  title: string
  description?: string
  children: ReactNode
  className?: string
}) {
  return (
    <section className={cn("space-y-4 rounded-xl border border-border/60 bg-card/30 p-4 sm:p-5", className)}>
      <div className="space-y-1">
        <h2 className="text-base font-semibold tracking-tight">{title}</h2>
        {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
      </div>
      {children}
    </section>
  )
}
