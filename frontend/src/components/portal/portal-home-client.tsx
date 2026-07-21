"use client"

import { useLayoutEffect } from "react"
import { resolveThemeVariant, themeRegistry } from "@/components/portal/themes/registry"
import {
  setPortalBootstrap,
  type PortalInitialData,
} from "@/components/portal/types"

export function PortalHomeClient({
  theme,
  initialData,
}: {
  theme?: string
  initialData?: PortalInitialData | null
}) {
  if (initialData) {
    setPortalBootstrap(initialData)
  }

  useLayoutEffect(() => {
    if (initialData) {
      setPortalBootstrap(initialData)
    }
  }, [initialData])

  const variant = resolveThemeVariant(theme ?? initialData?.meta?.theme)
  const Layout = themeRegistry[variant]
  return <Layout />
}
