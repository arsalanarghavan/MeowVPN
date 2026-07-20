"use client"

import { GregorianDateTimePicker } from "@/components/dashboard-date-picker/gregorian-datetime-picker"
import { JalaliDateTimePicker } from "@/components/dashboard-date-picker/jalali-datetime-picker"
import { cn } from "@/lib/utils"
import { dashDatePickerCalendar } from "@/lib/dash-locale"
import { useDashLocale } from "@/lib/dash-locale-context"

export { apiDatetimeToMs, msToApiDatetime } from "@/lib/datetime-api"
export { DashboardDatePicker } from "@/components/dashboard-date-picker/dashboard-date-picker"

/** Date+time picker: Jalali (FA) or Gregorian (EN). API values stay Gregorian ISO. */
export function DashboardDateTimePicker({
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
  const { isFa } = useDashLocale()

  const Picker =
    dashDatePickerCalendar(isFa) === "jalali" ? JalaliDateTimePicker : GregorianDateTimePicker
  return (
    <Picker
      value={value}
      onChange={onChange}
      label={label}
      className={cn(className)}
    />
  )
}
