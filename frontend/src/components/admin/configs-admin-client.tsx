"use client"

import { useTranslations } from "next-intl"
import { ConfigsAdminCore } from "@/components/admin/configs/configs-admin-core"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"

type DashRecord = Record<string, unknown>

function rows(v: unknown): DashRecord[] {
  return Array.isArray(v) ? (v.filter((x) => x && typeof x === "object") as DashRecord[]) : []
}

export function ConfigsAdminClient() {
  const t = useTranslations("configsAdmin")
  const tInbound = useTranslations("inboundLinkAdmin")
  const { data, loading, error, reload } = useAdminTabState("configs")

  const panels = rows(data?.panels ?? data?.panelRows ?? data?.xui_panels)
  const plans = rows(data?.plans ?? data?.planRows)

  if (loading && !data) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error && !data) {
    return <p className="text-sm text-destructive">{error}</p>
  }

  return (
    <ConfigsAdminCore
      panels={panels}
      plans={plans}
      configsActive
      onMutateSuccess={() => void reload()}
    />
  )
}
