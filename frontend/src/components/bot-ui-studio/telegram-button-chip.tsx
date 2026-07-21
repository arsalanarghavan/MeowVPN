"use client"

import { cn } from "@/lib/utils"
import type { UiButtonStyle } from "./types"
import { TELEGRAM_BUTTON_CLASS } from "./utils"

export function TelegramButtonPreview({
  label,
  style = "",
  className,
}: {
  label: string
  style?: UiButtonStyle
  className?: string
}) {
  return (
    <div
      className={cn(
        "flex w-full items-center justify-center rounded-md px-3 py-2 text-center text-sm font-medium shadow-sm",
        TELEGRAM_BUTTON_CLASS[style ?? ""],
        className
      )}
    >
      <span className="truncate">{label}</span>
    </div>
  )
}
