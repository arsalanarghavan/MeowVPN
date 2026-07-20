"use client"

import type { ReactNode } from "react"

import { dashActionsClass, dashPageHeaderClass } from "@/lib/dash-locale"
import { cn } from "@/lib/utils"

export function DashboardPageHeader({
  title,
  description,
  actions,
  className,
  titleClassName,
}: {
  title: ReactNode
  description?: ReactNode
  actions?: ReactNode
  className?: string
  titleClassName?: string
}) {
  return (
    <div className={cn(dashPageHeaderClass(), className)}>
      <div className={cn("min-w-0 space-y-1", titleClassName)}>
        {typeof title === "string" ? (
          <h2 className="text-lg font-medium">{title}</h2>
        ) : (
          title
        )}
        {description != null && description !== "" ? (
          typeof description === "string" ? (
            <p className="text-sm text-muted-foreground">{description}</p>
          ) : (
            description
          )
        ) : null}
      </div>
      {actions ? <div className={dashActionsClass()}>{actions}</div> : null}
    </div>
  )
}
