"use client"

import { useMemo, useState } from "react"
import { format } from "date-fns"
import { CalendarIcon } from "lucide-react"
import { useTranslation } from "react-i18next"

import { useDashDatePicker } from "@/components/dashboard-date-picker/use-dash-date-picker"
import { PersianCalendar } from "@/components/dashboard-date-picker/persian-calendar"
import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { Label } from "@/components/ui/label"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { dateOnlyMs } from "@/lib/datetime-api"
import { dashDatePickerCalendar } from "@/lib/dash-locale"
import { useDashLocale } from "@/lib/dash-locale-context"

const triggerClass =
  "w-full justify-start text-start font-normal data-[empty=true]:text-muted-foreground"

function isoDateToMs(iso: string): number {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso.trim())
  if (!m) return 0
  return Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]), 12, 0, 0)
}

function msToIsoDate(ms: number): string {
  if (!Number.isFinite(ms) || ms < 1) return ""
  const d = new Date(ms)
  const pad = (n: number) => String(n).padStart(2, "0")
  return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`
}

function JalaliDateOnlyPicker({
  value,
  onChange,
  label,
  className,
}: {
  value: string
  onChange: (isoDate: string) => void
  label?: string
  className?: string
}) {
  const { t } = useTranslation()
  const tl = (k: string) => t(`discountsAdmin.${k}`)
  const { dir, rootClass } = useDashDatePicker(className)
  const ms = isoDateToMs(value)
  const [open, setOpen] = useState(false)
  const selected = useMemo(() => (ms > 0 ? new Date(ms) : undefined), [ms])
  const display =
    ms > 0
      ? new Intl.DateTimeFormat("fa-IR", {
          calendar: "persian",
          year: "numeric",
          month: "long",
          day: "numeric",
        }).format(new Date(ms))
      : tl("pickDatetime")

  return (
    <div className={rootClass} dir={dir}>
      {label ? <Label>{label}</Label> : null}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
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
              onChange(msToIsoDate(dateOnlyMs(date.getTime())))
              setOpen(false)
            }}
          />
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

function GregorianDateOnlyPicker({
  value,
  onChange,
  label,
  className,
}: {
  value: string
  onChange: (isoDate: string) => void
  label?: string
  className?: string
}) {
  const { t } = useTranslation()
  const tl = (k: string) => t(`discountsAdmin.${k}`)
  const { dir, rootClass } = useDashDatePicker(className)
  const ms = isoDateToMs(value)
  const [open, setOpen] = useState(false)
  const selected = useMemo(() => (ms > 0 ? new Date(ms) : undefined), [ms])
  const display = selected ? format(selected, "PPP") : tl("pickDatetime")

  return (
    <div className={rootClass} dir={dir}>
      {label ? <Label>{label}</Label> : null}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
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
          <Calendar
            mode="single"
            selected={selected}
            defaultMonth={selected}
            onSelect={(date) => {
              if (!date) return
              onChange(msToIsoDate(dateOnlyMs(date.getTime())))
              setOpen(false)
            }}
          />
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

/** Date-only picker: Jalali (FA) or Gregorian (EN) from dashboard locale. Values are ISO `YYYY-MM-DD`. */
export function DashboardDatePicker({
  value,
  onChange,
  label,
  className,
}: {
  value: string
  onChange: (isoDate: string) => void
  label?: string
  className?: string
  id?: string
}) {
  const { isFa } = useDashLocale()
  const Picker = dashDatePickerCalendar(isFa) === "jalali" ? JalaliDateOnlyPicker : GregorianDateOnlyPicker
  return <Picker value={value} onChange={onChange} label={label} className={className} />
}
