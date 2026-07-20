"use client"

import { resolveThemeVariant, themeRegistry } from "@/components/portal/themes/registry"

export function PortalHomeClient({ theme }: { theme?: string }) {
  const variant = resolveThemeVariant(theme)
  const Layout = themeRegistry[variant]
  return <Layout />
}
