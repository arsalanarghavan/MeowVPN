"use client"

import { useDashLocale } from "@/lib/dash-locale-context"
import {
  dashDatePickerCalendar,
  dashDatePickerRootClass,
} from "@/lib/dash-locale"

export function useDashDatePicker(className?: string) {
  const { isFa, dir } = useDashLocale()
  return {
    isFa,
    dir,
    calendar: dashDatePickerCalendar(isFa),
    rootClass: dashDatePickerRootClass(className),
  }
}
