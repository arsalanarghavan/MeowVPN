import { useEffect, useState } from "react"

/** Chart stroke/fill tied to theme primary (accent / whitelabel). */
export const CHART_PRIMARY = "var(--primary)"
export const CHART_PRIMARY_FOREGROUND = "var(--primary-foreground)"

const CHART_PRIMARY_FALLBACK = "hsl(262 83% 58%)"

/** Resolve --primary to a concrete color for Recharts/SVG attributes. */
export function resolveChartPrimaryColor(fallback = CHART_PRIMARY_FALLBACK): string {
  if (typeof document === "undefined") return fallback
  const probe = document.createElement("span")
  probe.style.display = "none"
  probe.style.color = "var(--primary)"
  document.documentElement.appendChild(probe)
  const resolved = getComputedStyle(probe).color.trim()
  probe.remove()
  return resolved && resolved !== "rgba(0, 0, 0, 0)" ? resolved : fallback
}

/** Theme-aware primary color safe for SVG stopColor/stroke/fill. */
export function useChartPrimaryColor(): string {
  const [color, setColor] = useState(CHART_PRIMARY_FALLBACK)
  useEffect(() => {
    const sync = () => setColor(resolveChartPrimaryColor())
    sync()
    const obs = new MutationObserver(sync)
    obs.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class", "data-accent", "style"],
    })
    return () => obs.disconnect()
  }, [])
  return color
}

/** Outline buttons on overview that follow accent. */
export const overviewAccentOutlineBtn =
  "border-primary/30 hover:bg-primary/10 hover:text-primary"
