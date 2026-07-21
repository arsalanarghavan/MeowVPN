"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import { BOT_PLATFORMS } from "@/config/bot-platforms"
import { mainEnabledPlatforms } from "@/lib/enabled-platforms"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
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
  const t = useTranslations("forceJoinAdmin")
  const tBots = useTranslations("botsAdmin")
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

  const [cacheTtlSec, setCacheTtlSec] = useState(() => String(Math.max(30, num(s.force_join_cache_ttl_sec) || 180)))
  const [negativeCacheTtlSec, setNegativeCacheTtlSec] = useState(() =>
    String(Math.max(10, num(s.force_join_negative_cache_ttl_sec) || 45))
  )
  useEffect(() => {
    setCacheTtlSec(String(Math.max(30, num(s.force_join_cache_ttl_sec) || 180)))
    setNegativeCacheTtlSec(String(Math.max(10, num(s.force_join_negative_cache_ttl_sec) || 45)))
  }, [s.force_join_cache_ttl_sec, s.force_join_negative_cache_ttl_sec])

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
        force_join_cache_ttl_sec: Math.max(30, Math.min(3600, num(cacheTtlSec) || 180)),
        force_join_negative_cache_ttl_sec: Math.max(10, Math.min(600, num(negativeCacheTtlSec) || 45)),
      })
      if (!res.ok) {
        setError(res.message || t("saveError"))
        return
      }
      setOkMsg(t("saved"))
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [cacheTtlSec, forms, negativeCacheTtlSec, onMutateSuccess, t])

  const onPublish = useCallback(
    async (platform: PlatformId) => {
      setPublishing(platform)
      setError(null)
      setOkMsg(null)
      try {
        const res = await postAdminMutate("force_join_publish", { platform })
        if (!res.ok) {
          setError(res.message || t("publishError"))
          return
        }
        setOkMsg(t("publishOk"))
        onMutateSuccess?.()
      } finally {
        setPublishing("")
      }
    },
    [onMutateSuccess, t]
  )

  const platformTitle = (platform: PlatformId) =>
    platform === "telegram" ? tBots("platformTelegram") : tBots("platformBale")

  const platformCard = (platform: PlatformId, title: string) => {
    const f = forms[platform]
    return (
      <Card key={platform}>
        <CardHeader className="pb-3">
          <CardTitle className="text-base">{title}</CardTitle>
          <CardDescription className="text-xs">{t("cardDesc")}</CardDescription>
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
            {t("enabled")}
          </label>
          <div className="space-y-2">
            <Label>{t("chatId")}</Label>
            <Input
              type="number"
              dir="ltr"
              value={f.chat_id}
              disabled={busy}
              placeholder="-100…"
              onChange={(e) => setPlatform(platform, { chat_id: e.target.value })}
            />
            <p className="text-xs text-muted-foreground">{t("chatIdHint")}</p>
          </div>
          <div className="space-y-2">
            <Label>{t("username")}</Label>
            <Input
              dir="ltr"
              value={f.username}
              disabled={busy}
              placeholder="@channel"
              onChange={(e) => setPlatform(platform, { username: e.target.value })}
            />
          </div>
          <div className="space-y-2">
            <Label>{t("inviteLink")}</Label>
            <Input
              dir="ltr"
              value={f.invite_link}
              disabled={busy}
              placeholder="https://…"
              onChange={(e) => setPlatform(platform, { invite_link: e.target.value })}
            />
            <p className="text-xs text-muted-foreground">{t("inviteLinkHint")}</p>
          </div>
          <div className="space-y-2">
            <Label>{t("promptText")}</Label>
            <Textarea
              rows={4}
              value={f.prompt_text}
              disabled={busy}
              onChange={(e) => setPlatform(platform, { prompt_text: e.target.value })}
            />
            <p className="text-xs text-muted-foreground">{t("promptTextHint")}</p>
          </div>
          <div className="space-y-2 border-t border-border pt-3">
            <Label>{t("announceText")}</Label>
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
              {publishing === platform ? t("publishing") : t("publishPin")}
            </Button>
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="space-y-4">
      <div>
        <h3 className="text-base font-medium">{t("sectionTitle")}</h3>
        <p className="text-sm text-muted-foreground">{t("sectionDesc")}</p>
      </div>
      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}
      {okMsg && !error ? <p className="text-sm text-emerald-600 dark:text-emerald-400">{okMsg}</p> : null}
      <div className="grid gap-4 lg:grid-cols-2">
        {visiblePlatforms.map((plat) => platformCard(plat.id, platformTitle(plat.id)))}
      </div>
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-base">{t("cacheTitle")}</CardTitle>
          <CardDescription className="text-xs">{t("cacheDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>{t("cacheTtlSec")}</Label>
            <Input
              type="number"
              dir="ltr"
              min={30}
              max={3600}
              value={cacheTtlSec}
              disabled={busy}
              onChange={(e) => setCacheTtlSec(e.target.value)}
            />
            <p className="text-xs text-muted-foreground">{t("cacheTtlHint")}</p>
          </div>
          <div className="space-y-2">
            <Label>{t("negativeCacheTtlSec")}</Label>
            <Input
              type="number"
              dir="ltr"
              min={10}
              max={600}
              value={negativeCacheTtlSec}
              disabled={busy}
              onChange={(e) => setNegativeCacheTtlSec(e.target.value)}
            />
            <p className="text-xs text-muted-foreground">{t("negativeCacheTtlHint")}</p>
          </div>
        </CardContent>
      </Card>
      <div className={cn("flex flex-wrap gap-2")}>
        <Button type="button" size="sm" disabled={busy} onClick={() => void onSave()}>
          {saving ? t("saving") : t("save")}
        </Button>
      </div>
    </div>
  )
}
