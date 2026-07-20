"use client"

import * as React from "react"
import { useLocale } from "next-intl"
import { format } from "date-fns"
import { Calendar as CalendarIcon } from "lucide-react"
import { DayPicker } from "react-day-picker"
import { DayPicker as PersianDayPicker } from "@daypicker/persian"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { toPersianDigits } from "@/lib/jalali"

type Props = {
  value?: Date
  onChange?: (date: Date | undefined) => void
  className?: string
  placeholder?: string
}

/** Locale-aware date picker: Jalali+Persian digits for fa, Gregorian for en. Uses official shadcn Calendar when available. */
export function LocaleDatePicker({ value, onChange, className, placeholder }: Props) {
  const locale = useLocale()
  const isFa = locale === "fa"

  const label = React.useMemo(() => {
    if (!value) return placeholder ?? ""
    if (isFa) {
      try {
        const fmt = new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
          year: "numeric",
          month: "long",
          day: "numeric",
        }).format(value)
        return toPersianDigits(fmt)
      } catch {
        return toPersianDigits(format(value, "yyyy-MM-dd"))
      }
    }
    return format(value, "PPP")
  }, [value, isFa, placeholder])

  return (
    <Popover>
      <PopoverTrigger
        render={
          <Button
            variant="outline"
            className={cn(
              "w-full justify-start text-start font-normal",
              !value && "text-muted-foreground",
              className
            )}
          />
        }
      >
        <CalendarIcon data-icon="inline-start" />
        {label || placeholder}
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        {isFa ? (
          <PersianDayPicker
            mode="single"
            selected={value}
            onSelect={onChange}
            className="p-3"
          />
        ) : (
          <Calendar mode="single" selected={value} onSelect={onChange} />
        )}
      </PopoverContent>
    </Popover>
  )
}

// Re-export DayPicker for advanced usage
export { DayPicker, PersianDayPicker }
