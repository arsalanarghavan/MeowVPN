import { useCallback } from "react"
import { useTranslation } from "react-i18next"

/**
 * Stable translation helper for admin pages (avoids inline `tp` breaking useCallback/load deps).
 */
export function useAdminTp(prefix: string) {
  const { t } = useTranslation()
  return useCallback(
    (k: string, opts?: Record<string, string | number>) => t(`${prefix}.${k}`, opts),
    [t, prefix],
  )
}
