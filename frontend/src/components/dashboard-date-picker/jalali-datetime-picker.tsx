"use client"

import { useId, useMemo, useState } from "react"
import { CalendarIcon } from "lucide-react"
import { useLocale, useTranslations } from "next-intl"

import { useDashDatePicker } from "@/components/dashboard-date-picker/use-dash-date-picker"
import { PersianCalendar } from "@/components/dashboard-date-picker/persian-calendar"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import {
  apiDatetimeToMs,
  applyTimeToMs,
  dateOnlyMs,
  msToApiDatetime,
  msToTimeValue,
} from "@/lib/datetime-api"

const triggerClass =
  "w-full justify-start text-start font-normal data-[empty=true]:text-muted-foreground"

function formatJalaliDisplay(ms: number): string {
  if (!Number.isFinite(ms) || ms < 1) return ""
  const d = new Date(ms)
  const datePart = new Intl.DateTimeFormat("fa-IR", {
    calendar: "persian",
    year: "numeric",
    month: "long",
    day: "numeric",
  }).format(d)
  return `${datePart} — ${msToTimeValue(ms)}`
}

export function JalaliDateTimePicker({
  value,
  onChange,
  label,
  className,
}: {
  value: string
  onChange: (apiValue: string) => void
  label?: string
  className?: string
}) {
  const locale = useLocale()
  const isFa = locale === "fa"
  const tDisc = useTranslations("discountsAdmin")
  const tl = (k: string) => tDisc(k)
  const { dir, rootClass } = useDashDatePicker(className)
  const timeId = useId()
  const ms = apiDatetimeToMs(value)
  const [open, setOpen] = useState(false)
  const selected = useMemo(() => (ms > 0 ? new Date(ms) : undefined), [ms])
  const timeValue = ms > 0 ? msToTimeValue(ms) : "00:00"
  const display = ms > 0 ? formatJalaliDisplay(ms) : tl("pickDatetime")

  const commit = (nextMs: number) => {
    onChange(nextMs > 0 ? msToApiDatetime(nextMs) : "")
  }

  return (
    <div className={rootClass} dir={dir}>
      {label ? <Label>{label}</Label> : null}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger>
          <Button
            type="button"
            variant="outline"
            data-empty={!selected}
            className={triggerClass}
          >
            <CalendarIcon className="size-4 shrink-0 opacity-70" />
            <span className="truncate">{display}</span>
          </Button>
        </PopoverTrigger>
        <PopoverContent className="z-[120] w-auto p-0" align="start" dir={dir}>
          <PersianCalendar
            mode="single"
            selected={selected}
            defaultMonth={selected}
            captionLayout="dropdown"
            onSelect={(date) => {
              if (!date) return
              const base = dateOnlyMs(date.getTime())
              commit(applyTimeToMs(base, timeValue))
            }}
          />
          <div className="border-t p-3">
            <Label htmlFor={timeId} className="text-xs text-muted-foreground">
              {tl("pickTime")}
            </Label>
            <Input
              id={timeId}
              type="time"
              value={timeValue}
              className="mt-1 appearance-none bg-background [&::-webkit-calendar-picker-indicator]:hidden [&::-webkit-calendar-picker-indicator]:appearance-none"
              onChange={(e) => {
                const base = ms > 0 ? dateOnlyMs(ms) : dateOnlyMs(Date.now())
                commit(applyTimeToMs(base, e.target.value))
              }}
            />
          </div>
        </PopoverContent>
      </Popover>
      {value ? (
        <button
          type="button"
          className="text-xs text-muted-foreground underline-offset-2 hover:underline"
          onClick={() => onChange("")}
        >
          {tl("clearDatetime")}
        </button>
      ) : null}
    </div>
  )
}
