"use client"

import { useCallback, useState } from "react"
import { useTranslation } from "react-i18next"
import type { TFunction } from "i18next"

import { adminMutateErrorText, postAdminMutate, type AdminMutateResult } from "@/lib/dash-admin-mutate"

export function mapSettingsTabMessage(code: string | undefined, t: TFunction): string {
  switch (code) {
    case "saved":
      return t("siteSettings.common.saved")
    case "invalid_tab":
    case "missing_tab":
      return t("siteSettings.common.saveInvalidTab")
    case "no_rest":
      return t("siteSettings.common.saveNoRest")
    default:
      return code && code.trim() ? code : t("siteSettings.common.saveError")
  }
}

function mapMutateError(res: AdminMutateResult, t: TFunction): string {
  const raw = adminMutateErrorText(res, t("siteSettings.common.saveError"))
  return mapSettingsTabMessage(raw, t)
}

export { mapMutateError as formatAdminSaveError }

export function useSiteSettingsSave(onMutateSuccess?: () => void) {
  const { t } = useTranslation()
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
          setError(mapMutateError(res, t))
          return false
        }
        setOkMsg(t("siteSettings.common.saved"))
        onMutateSuccess?.()
        return true
      } catch {
        setError(t("siteSettings.common.saveNetworkError"))
        return false
      } finally {
        setSaving(false)
      }
    },
    [onMutateSuccess, t]
  )

  const saveMutate = useCallback(
    async (op: string, payload: Record<string, unknown>) => {
      setSaving(true)
      setError(null)
      setOkMsg(null)
      try {
        const res = await postAdminMutate(op, payload)
        if (!res.ok) {
          setError(mapMutateError(res, t))
          return false
        }
        setOkMsg(t("siteSettings.common.saved"))
        onMutateSuccess?.()
        return true
      } catch {
        setError(t("siteSettings.common.saveNetworkError"))
        return false
      } finally {
        setSaving(false)
      }
    },
    [onMutateSuccess, t]
  )

  return { saving, error, okMsg, saveSettingsTab, saveMutate, setError, setOkMsg }
}
