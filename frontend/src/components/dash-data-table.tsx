"use client"

import type { ReactNode } from "react"
import { dashTableCellClass, dashTableHeadClass } from "@/lib/dash-locale"
import { cn } from "@/lib/utils"

export function DashTableShell({
  minWidth,
  colWidths,
  children,
  className,
}: {
  isFa?: boolean
  minWidth?: string
  colWidths: string[]
  children: ReactNode
  className?: string
}) {
  return (
    <div className={cn("w-full max-w-full overflow-x-auto rounded-md border border-border", className)}>
      <table
        className={cn(
          "w-full table-fixed border-collapse text-sm text-start",
          "[&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border"
        )}
        style={minWidth ? { minWidth } : undefined}
      >
        <colgroup>
          {colWidths.map((w, i) => (
            <col key={i} style={{ width: w }} />
          ))}
        </colgroup>
        {children}
      </table>
    </div>
  )
}

export function DashTh({
  children,
  className,
  numeric,
  title,
}: {
  children?: ReactNode
  className?: string
  numeric?: boolean
  title?: string
}) {
  return (
    <th className={cn("p-2 font-medium", dashTableHeadClass(), dashTableCellClass({ numeric }), className)} title={title}>
      {children}
    </th>
  )
}

export function DashTd({
  children,
  className,
  dir,
  colSpan,
  numeric,
}: {
  children?: ReactNode
  className?: string
  dir?: "ltr" | "rtl"
  colSpan?: number
  numeric?: boolean
}) {
  return (
    <td dir={dir} colSpan={colSpan} className={cn("p-2", dashTableCellClass({ numeric }), className)}>
      {children}
    </td>
  )
}
