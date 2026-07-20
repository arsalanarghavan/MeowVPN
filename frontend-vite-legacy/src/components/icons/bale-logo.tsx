import baleSvg from "@/assets/brands/bale.svg"
import { cn } from "@/lib/utils"

export function BaleLogo({ className }: { className?: string }) {
  return (
    <img
      src={baleSvg}
      alt=""
      className={cn("size-4 shrink-0 dark:invert", className)}
      aria-hidden
    />
  )
}
