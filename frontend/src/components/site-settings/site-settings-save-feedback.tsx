"use client"

/** Inline save feedback for site-settings tabs. */
export function SiteSettingsSaveFeedback({
  error,
  okMsg,
}: {
  error: string | null
  okMsg: string | null
}) {
  if (!error && !okMsg) return null
  return (
    <div className="space-y-1 text-sm">
      {error ? <p className="text-destructive" role="alert">{error}</p> : null}
      {okMsg ? <p className="text-emerald-600 dark:text-emerald-400">{okMsg}</p> : null}
    </div>
  )
}
