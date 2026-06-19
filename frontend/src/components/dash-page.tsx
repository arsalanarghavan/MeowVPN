"use client"

import type { ReactNode } from "react"

import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

/** Page shell — relies on main scroll `dir`; no nested dir here. */
export function DashPage({
  children,
  className,
  ...rest
}: {
  children: ReactNode
  className?: string
} & React.HTMLAttributes<HTMLDivElement>) {
  const { pageRootClass } = useDashLocale()
  return (
    <div className={cn(pageRootClass(), className)} {...rest}>
      {children}
    </div>
  )
}
