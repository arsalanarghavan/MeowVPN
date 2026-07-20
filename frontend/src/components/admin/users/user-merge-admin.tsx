"use client"

import { useTranslations } from "next-intl"
import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"

export function UserMergeAdmin({
  onMutateSuccess,
}: {
  onMutateSuccess?: () => void
}) {

  const t = useTranslations("userMergeAdmin")
  const tp = (k: string) => t(`${k}`)
  const [keepId, setKeepId] = useState("")
  const [dropId, setDropId] = useState("")
  const [preview, setPreview] = useState<Record<string, unknown> | null>(null)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
  const [confirm, setConfirm] = useState(false)

  async function runPreview() {
    setBusy(true)
    setErr("")
    setPreview(null)
    try {
      const res = await postAdminMutate("user_merge_preview", {
        keep_id: Number(keepId),
        drop_id: Number(dropId),
      })
      if (!res.ok) {
        setErr(res.message || t("previewError"))
        return
      }
      setPreview((res.data as Record<string, unknown>) || {})
    } finally {
      setBusy(false)
    }
  }

  async function runMerge() {
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("user_merge", {
        keep_id: Number(keepId),
        drop_id: Number(dropId),
        confirm: true,
      })
      if (!res.ok) {
        setErr(res.message || t("mergeError"))
        return
      }
      setPreview(null)
      setConfirm(false)
      setDropId("")
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const keep = preview?.keep as Record<string, unknown> | undefined
  const drop = preview?.drop as Record<string, unknown> | undefined

  return (
    <div className="mx-auto max-w-4xl space-y-6" data-testid="dash-user-merge-panel">
      <div>
        <h2 className="text-lg font-semibold tracking-tight">{t("title")}</h2>
        <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
      </div>

      <Card className="border-primary/15 shadow-sm">
        <CardHeader>
          <CardTitle className="text-base">{t("pickTitle")}</CardTitle>
          <CardDescription>{t("pickHint")}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="keep-id">{t("keepId")}</Label>
            <Input
              id="keep-id"
              dir="ltr"
              className="font-mono"
              inputMode="numeric"
              value={keepId}
              onChange={(e) => setKeepId(e.target.value.replace(/\D/g, ""))}
              placeholder={t("keepIdPlaceholder")}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="drop-id">{t("dropId")}</Label>
            <Input
              id="drop-id"
              dir="ltr"
              className="font-mono"
              inputMode="numeric"
              value={dropId}
              onChange={(e) => setDropId(e.target.value.replace(/\D/g, ""))}
              placeholder={t("dropIdPlaceholder")}
            />
          </div>
          <div className={cn("sm:col-span-2 flex flex-wrap gap-2")}>
            <Button type="button" variant="secondary" disabled={busy} onClick={() => void runPreview()}>
              {t("preview")}
            </Button>
          </div>
        </CardContent>
      </Card>

      {err ? (
        <p className="text-sm text-destructive" role="alert">
          {err}
        </p>
      ) : null}

      {keep && drop ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                {t("cardKeep")}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1 font-mono text-xs">
              <div>id: {String(keep.id)}</div>
              <div>tg: {String(keep.tg_user_id || "—")}</div>
              <div>bale: {String(keep.bale_user_id || "—")}</div>
              <div>@{(keep.username as string) || "—"}</div>
              <div>{t("balance")}: {String(keep.balance)}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-amber-700 dark:text-amber-400">
                {t("cardDrop")}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1 font-mono text-xs">
              <div>id: {String(drop.id)}</div>
              <div>tg: {String(drop.tg_user_id || "—")}</div>
              <div>bale: {String(drop.bale_user_id || "—")}</div>
              <div>@{(drop.username as string) || "—"}</div>
              <div>{t("balance")}: {String(drop.balance)}</div>
            </CardContent>
          </Card>
          <Card className="md:col-span-2 border-dashed">
            <CardContent className="pt-6 space-y-4">
              <p className="text-sm text-muted-foreground">{t("countsHint")}</p>
              <pre className="overflow-x-auto rounded-md bg-muted/50 p-3 text-xs" dir="ltr">
                {JSON.stringify(preview?.drop_related ?? null, null, 2)}
              </pre>
              <label className="flex cursor-pointer items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input accent-primary"
                  checked={confirm}
                  onChange={(e) => setConfirm(e.target.checked)}
                />
                {t("confirmCheck")}
              </label>
              <Button type="button" variant="destructive" disabled={busy || !confirm} onClick={() => void runMerge()}>
                {t("execute")}
              </Button>
            </CardContent>
          </Card>
        </div>
      ) : null}
    </div>
  )
}
