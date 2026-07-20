"use client"

import type { ReactNode } from "react"

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useDashLocaleOptional } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

export const DASH_SELECT_EMPTY = "__dash_empty"

export type DashSelectOption = {
  value: string
  label: ReactNode
  disabled?: boolean
}

export type DashSelectProps = {
  value: string
  onValueChange: (value: string) => void
  options: DashSelectOption[]
  placeholder?: string
  /** Maps empty string to an internal sentinel for Radix compatibility. */
  allowEmpty?: boolean
  disabled?: boolean
  id?: string
  size?: "sm" | "default"
  /** Override document direction for LTR islands (URLs, codes). */
  dir?: "rtl" | "ltr"
  triggerClassName?: string
  contentClassName?: string
}

export function DashSelect({
  value,
  onValueChange,
  options,
  placeholder,
  allowEmpty = false,
  disabled,
  id,
  size = "default",
  dir: dirOverride,
  triggerClassName,
  contentClassName,
}: DashSelectProps) {
  const { dir: localeDir } = useDashLocaleOptional()
  const contentDir = dirOverride ?? localeDir

  const radixValue = allowEmpty && value === "" ? DASH_SELECT_EMPTY : value

  const handleValueChange = (next: string) => {
    if (allowEmpty && next === DASH_SELECT_EMPTY) {
      onValueChange("")
      return
    }
    onValueChange(next)
  }

  const items = allowEmpty
    ? [{ value: DASH_SELECT_EMPTY, label: placeholder ?? "—", disabled: false }, ...options]
    : options

  return (
    <Select value={radixValue} onValueChange={handleValueChange} disabled={disabled}>
      <SelectTrigger
        id={id}
        size={size}
        dir={dirOverride}
        className={cn("w-full text-start", triggerClassName)}
      >
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent
        dir={contentDir}
        position="popper"
        sideOffset={4}
        align="start"
        className={cn("text-start", contentClassName)}
      >
        {items.map((opt) => (
          <SelectItem key={opt.value} value={opt.value} disabled={opt.disabled}>
            {opt.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
