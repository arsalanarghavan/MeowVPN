"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { BOT_PLATFORMS } from "@/config/bot-platforms"
import { mainEnabledPlatforms } from "@/lib/enabled-platforms"
import { DashPage } from "@/components/dash-page"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

type PlatformId = "telegram" | "bale"

type PlatformForm = {
  enabled: boolean
  chat_id: string
  username: string
  invite_link: string
  prompt_text: string
  announce_text: string
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function prefixFor(platform: PlatformId): string {
  return platform === "telegram" ? "force_join_telegram" : "force_join_bale"
}

function formFromSettings(s: DashRecord, platform: PlatformId): PlatformForm {
  const p = prefixFor(platform)
  return {
    enabled: bool(s[`${p}_enabled`]),
    chat_id: String(num(s[`${p}_chat_id`]) || ""),
    username: String(s[`${p}_username`] ?? ""),
    invite_link: String(s[`${p}_invite_link`] ?? ""),
    prompt_text: String(s[`${p}_prompt_text`] ?? ""),
    announce_text: String(s[`${p}_announce_text`] ?? ""),
  }
}

function payloadFromForm(platform: PlatformId, f: PlatformForm): Record<string, unknown> {
  const p = prefixFor(platform)
  return {
    [`${p}_enabled`]: +!!f.enabled,
    [`${p}_chat_id`]: num(f.chat_id),
    [`${p}_username`]: f.username.trim(),
    [`${p}_invite_link`]: f.invite_link.trim(),
    [`${p}_prompt_text`]: f.prompt_text,
    [`${p}_announce_text`]: f.announce_text,
  }
}

export function DashboardForceJoinAdmin({
  settings,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  onMutateSuccess?: () => void
}) {

  const { t } = useTranslation()
  const tp = (k: string) => t(`forceJoinAdmin.${k}`)
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      telegram: formFromSettings(s, "telegram"),
      bale: formFromSettings(s, "bale"),
    }),
    [s]
  )

  const [forms, setForms] = useState(initial)
  useEffect(() => {
    setForms(initial)
  }, [initial])

  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState<PlatformId | "">("")
  const [error, setError] = useState<string | null>(null)
  const [okMsg, setOkMsg] = useState<string | null>(null)

  const visiblePlatforms = useMemo(() => {
    const enabled = new Set(mainEnabledPlatforms(s))
    return BOT_PLATFORMS.filter((p) => enabled.has(p.id))
  }, [s])

  const busy = saving || publishing !== ""

  const setPlatform = (platform: PlatformId, patch: Partial<PlatformForm>) => {
    setForms((prev) => ({
      ...prev,
      [platform]: { ...prev[platform], ...patch },
    }))
  }

  const onSave = useCallback(async () => {
    setSaving(true)
    setError(null)
    setOkMsg(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "force_join",
        ...payloadFromForm("telegram", forms.telegram),
        ...payloadFromForm("bale", forms.bale),
      })
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      setOkMsg(tp("saved"))
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [forms, onMutateSuccess, tp])

  const onPublish = useCallback(
    async (platform: PlatformId) => {
      setPublishing(platform)
      setError(null)
      setOkMsg(null)
      try {
        const res = await postAdminMutate("force_join_publish", { platform })
        if (!res.ok) {
          setError(res.message || tp("publishError"))
          return
        }
        setOkMsg(tp("publishOk"))
        onMutateSuccess?.()
      } finally {
        setPublishing("")
      }
    },
    [onMutateSuccess, tp]
  )

  const platformCard = (platform: PlatformId, title: string) => {
    const f = forms[platform]
    return (
      <Card key={platform}>
        <CardHeader className="pb-3">
          <CardTitle className="text-base">{title}</CardTitle>
          <CardDescription className="text-xs">{tp("cardDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <label className={cn("flex items-center gap-2 text-sm")}>
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={f.enabled}
              disabled={busy}
              onChange={(e) => setPlatform(platform, { enabled: e.target.checked })}
            />
            {tp("enabled")}
          </label>
          <div className="space-y-2">
            <Label>{tp("chatId")}</Label>
            <Input
              type="number"
              dir="ltr"
              value={f.chat_id}
              disabled={busy}
              placeholder="-100…"
              onChange={(e) => setPlatform(platform, { chat_id: e.target.value })}
            />
            <p className="text-xs text-muted-foreground">{tp("chatIdHint")}</p>
          </div>
          <div className="space-y-2">
            <Label>{tp("username")}</Label>
            <Input
              dir="ltr"
              value={f.username}
              disabled={busy}
              placeholder="@channel"
              onChange={(e) => setPlatform(platform, { username: e.target.value })}
            />
          </div>
          <div className="space-y-2">
            <Label>{tp("inviteLink")}</Label>
            <Input
              dir="ltr"
              value={f.invite_link}
              disabled={busy}
              placeholder="https://…"
              onChange={(e) => setPlatform(platform, { invite_link: e.target.value })}
            />
            <p className="text-xs text-muted-foreground">{tp("inviteLinkHint")}</p>
          </div>
          <div className="space-y-2">
            <Label>{tp("promptText")}</Label>
            <Textarea
              rows={4}
              value={f.prompt_text}
              disabled={busy}
              onChange={(e) => setPlatform(platform, { prompt_text: e.target.value })}
            />
            <p className="text-xs text-muted-foreground">{tp("promptTextHint")}</p>
          </div>
          <div className="space-y-2 border-t border-border pt-3">
            <Label>{tp("announceText")}</Label>
            <Textarea
              rows={4}
              value={f.announce_text}
              disabled={busy}
              onChange={(e) => setPlatform(platform, { announce_text: e.target.value })}
            />
            <Button
              type="button"
              size="sm"
              variant="secondary"
              disabled={busy}
              onClick={() => void onPublish(platform)}
            >
              {publishing === platform ? tp("publishing") : tp("publishPin")}
            </Button>
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <DashPage className={"space-y-4"}>
      <div>
        <h3 className="text-base font-medium">{tp("sectionTitle")}</h3>
        <p className="text-sm text-muted-foreground">{tp("sectionDesc")}</p>
      </div>
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
      <div className="grid gap-4 lg:grid-cols-2">
        {visiblePlatforms.map((plat) =>
          platformCard(plat.id, t(plat.titleKey))
        )}
      </div>
      <div className={cn("flex flex-wrap gap-2")}>
        <Button type="button" size="sm" disabled={busy} onClick={() => void onSave()}>
          {saving ? tp("saving") : tp("save")}
        </Button>
      </div>
    </DashPage>
  )
}
