"use client"

export function SiteSettingsSaveFeedback({
  error,
  okMsg,
}: {
  error: string | null
  okMsg: string | null
}) {
  if (!error && !okMsg) return null
  return (
    <>
      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}
      {okMsg && !error ? (
        <p className="text-sm text-emerald-600 dark:text-emerald-400">{okMsg}</p>
      ) : null}
    </>
  )
}
