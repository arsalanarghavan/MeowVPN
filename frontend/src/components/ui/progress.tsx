"use client"

import * as React from "react"
import { cn } from "@/lib/utils"

function Progress({ className, value = 0, ...props }: React.ComponentProps<"div"> & { value?: number }) {
  const pct = Math.max(0, Math.min(100, Number(value) || 0))
  return (
    <div
      data-slot="progress"
      className={cn("relative h-2 w-full overflow-hidden rounded-full bg-muted", className)}
      {...props}
    >
      <div className="h-full bg-primary transition-all" style={{ width: `${pct}%` }} />
    </div>
  )
}

export { Progress }
