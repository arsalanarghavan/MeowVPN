import { cn } from "@/lib/utils"

export function Switch({
  className,
  checked,
  disabled,
  onCheckedChange,
  id,
  "aria-label": ariaLabel,
}: {
  className?: string
  checked: boolean
  disabled?: boolean
  onCheckedChange: (next: boolean) => void
  id?: string
  "aria-label"?: string
}) {
  return (
    <button
      id={id}
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={ariaLabel}
      disabled={disabled}
      dir="ltr"
      className={cn(
        "peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50",
        checked ? "bg-primary" : "bg-muted",
        className
      )}
      onClick={() => {
        if (!disabled) onCheckedChange(!checked)
      }}
    >
      <span
        className={cn(
          "pointer-events-none block size-5 rounded-full bg-background shadow-md ring-0 transition-transform",
          checked ? "translate-x-5" : "translate-x-0.5"
        )}
      />
    </button>
  )
}
