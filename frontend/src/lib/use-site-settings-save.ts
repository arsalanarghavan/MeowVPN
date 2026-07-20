"use client"

import { useCallback, useState } from "react"
import { useTranslations } from "next-intl"
import { adminMutateErrorText, postAdminMutate, type AdminMutateResult } from "@/lib/dash-admin-mutate"

function mapSettingsTabMessage(code: string | undefined, t: (k: string) => string): string {
  switch (code) {
    case "saved":
      return t("saved")
    case "invalid_tab":
    case "missing_tab":
      return t("saveInvalidTab")
    case "no_rest":
      return t("saveNoRest")
    default:
      return code && code.trim() ? code : t("saveError")
  }
}

export function useSiteSettingsSave(onMutateSuccess?: () => void) {
  const t = useTranslations("siteSettings.common")
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [okMsg, setOkMsg] = useState<string | null>(null)

  const saveSettingsTab = useCallback(
    async (tab: string, payload: Record<string, unknown>) => {
      setSaving(true)
      setError(null)
      setOkMsg(null)
      try {
        const res = await postAdminMutate("settings_tab", { tab, ...payload })
        if (!res.ok) {
          setError(mapSettingsTabMessage(adminMutateErrorText(res, t("saveError")), t))
          return false
        }
        setOkMsg(t("saved"))
        onMutateSuccess?.()
        return true
      } catch {
        setError(t("saveNetworkError"))
        return false
      } finally {
        setSaving(false)
      }
    },
    [onMutateSuccess, t]
  )

  return { saving, error, okMsg, saveSettingsTab, setError, setOkMsg }
}

export type { AdminMutateResult }
