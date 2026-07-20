"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import {
  EllipsisVertical,
  MessageCircle,
  MessagesSquare,
  Pencil,
  Power,
  RefreshCw,
  Send,
  Stethoscope,
  Trash2,
  Webhook,
} from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashboardForceJoinAdmin } from "@/components/dashboard-force-join-admin"
import { DashboardBotDiagnosticsDialog } from "@/components/dashboard-bot-diagnostics-dialog"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function asIdList(v: unknown): number[] {
  if (Array.isArray(v)) return v.map(num).filter((id) => id > 0)
  if (typeof v === "string" && v.trim()) {
    try {
      const parsed = JSON.parse(v) as unknown
      if (Array.isArray(parsed)) return parsed.map(num).filter((id) => id > 0)
    } catch {
      return v
        .split(/[\s,]+/)
        .map(num)
        .filter((id) => id > 0)
    }
  }
  return []
}

function parseTextOverrides(row: DashRecord): Record<string, string> {
  if (row.text_overrides && typeof row.text_overrides === "object" && !Array.isArray(row.text_overrides)) {
    return row.text_overrides as Record<string, string>
  }
  const raw = row.text_overrides_json
  if (typeof raw === "string" && raw.trim()) {
    try {
      const parsed = JSON.parse(raw) as unknown
      if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
        return parsed as Record<string, string>
      }
    } catch {
      return {}
    }
  }
  return {}
}

function hasPlatformToken(row: DashRecord, platform: "telegram" | "bale"): boolean {
  if (platform === "telegram") {
    return bool(row.has_telegram_token) || String(row.telegram_token ?? "").trim() !== ""
  }
  return bool(row.has_bale_token) || String(row.bale_token ?? "").trim() !== ""
}

function resellerPlatformEnabled(row: DashRecord, platform: "telegram" | "bale"): boolean {
  if (platform === "telegram") return bool(row.telegram_enabled)
  return bool(row.bale_enabled)
}

function resellerIdOf(row: DashRecord): number {
  return num(row.reseller_id ?? row.reseller_svp_user_id)
}

export function BotsAdminClient() {
  const t = useTranslations("botsAdmin")
  const { isFa } = useDashLocale()
  const [settings, setSettings] = useState<DashRecord>({})
  const [bots, setBots] = useState<DashRecord[]>([])
  const [mirrors, setMirrors] = useState<DashRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState("")
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)
  const [tokenForm, setTokenForm] = useState<Record<string, string>>({})
  const [adminIdForm, setAdminIdForm] = useState<Record<string, string>>({})
  const [editRow, setEditRow] = useState<DashRecord | null>(null)
  const [editForm, setEditForm] = useState<Record<string, string>>({})
  const [deleteWebhook, setDeleteWebhook] = useState<{ platform: "telegram" | "bale"; resellerId: number } | null>(null)
  const [mirrorDlgOpen, setMirrorDlgOpen] = useState(false)
  const [mirrorDlgRow, setMirrorDlgRow] = useState<DashRecord | null>(null)
  const [mirrorForm, setMirrorForm] = useState({ label: "", telegram_token: "", telegram_secret_token: "" })
  const [mirrorDeleteDlg, setMirrorDeleteDlg] = useState<DashRecord | null>(null)
  const [diag, setDiag] = useState<{ platform: "telegram" | "bale"; resellerId: number; mirrorId?: number } | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setErr(null)
    try {
      const data = await getAdminState("bots", { bots_per_page: 100 })
      const nextSettings = data.settings && typeof data.settings === "object" ? (data.settings as DashRecord) : {}
      setSettings(nextSettings)
      setTokenForm({
        telegram_token: "",
        bale_token: "",
        telegram_secret_header: String(nextSettings.telegram_secret_header ?? ""),
        bale_wallet_provider_token: "",
      })
      setBots(Array.isArray(data.botsList) ? (data.botsList as DashRecord[]) : [])
      setMirrors(Array.isArray(data.telegramMirrorsList) ? (data.telegramMirrorsList as DashRecord[]) : [])
    } catch {
      setErr(t("loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (!editRow) return
    const rid = resellerIdOf(editRow)
    const updated = bots.find((r) => resellerIdOf(r) === rid)
    if (updated) setEditRow(updated)
  }, [bots, editRow])

  const run = useCallback(
    async (op: string, payload: Record<string, unknown> = {}, success = t("saved")) => {
      setBusy(op)
      setErr(null)
      setMsg(null)
      try {
        const res = await postAdminMutate(op, payload)
        if (!res.ok) {
          setErr(res.message || res.reason || t("saveError"))
          return false
        }
        setMsg(success)
        await load()
        return true
      } finally {
        setBusy("")
      }
    },
    [load, t]
  )

  const isBusy = busy !== ""

  const relayFeatureOn = useMemo(() => {
    const f = settings.features
    return !!(f && typeof f === "object" && (f as DashRecord).relay === true)
  }, [settings.features])

  const relayOn =
    relayFeatureOn &&
    (bool(settings.telegram_relay_enabled) || bool(settings.telegram_relay_force)) &&
    String(
      settings.telegram_relay_admin_url ||
        settings.telegram_relay_base_url ||
        settings.telegram_relay_public_url ||
        ""
    ).trim() !== ""

  const showWebhookRate = settings.webhook_rate_limit_per_min !== undefined && settings.webhook_rate_limit_per_min !== null

  const saveTokens = async () => {
    const payload: Record<string, unknown> = {
      tab: "bots",
      telegram_secret_header: tokenForm.telegram_secret_header ?? "",
    }
    for (const key of ["telegram_token", "bale_token", "bale_wallet_provider_token"]) {
      if (tokenForm[key]?.trim()) payload[key] = tokenForm[key]!.trim()
    }
    await run("settings_tab", payload)
  }

  const webhookPayload = (platform: "telegram" | "bale", resellerId: number) =>
    resellerId > 0 ? { platform, bot_id: resellerId } : { platform, bot_id: 0 }

  const platformToggleLabel = (plat: "telegram" | "bale", on: boolean) => {
    if (plat === "telegram") return on ? t("btnDisableTelegram") : t("btnEnableTelegram")
    return on ? t("btnDisableBale") : t("btnEnableBale")
  }

  const togglePlatform = (platform: "telegram" | "bale", resellerId = 0) => {
    const payload: Record<string, unknown> = { platform }
    if (resellerId > 0) payload.reseller_svp_user_id = resellerId
    return run("bot_toggle_platform_enabled", payload)
  }

  const saveAdminId = (platform: "telegram" | "bale", resellerId = 0) => {
    const key = `${platform}:${resellerId}`
    const id = num(adminIdForm[key])
    if (id < 1) return
    const payload: Record<string, unknown> = { platform, admin_id: id }
    if (resellerId > 0) payload.reseller_svp_user_id = resellerId
    void run("bot_admin_id_add", payload)
    setAdminIdForm((current) => ({ ...current, [key]: "" }))
  }

  const removeAdminId = (platform: "telegram" | "bale", id: number, resellerId = 0) => {
    const payload: Record<string, unknown> = { platform, admin_id: id }
    if (resellerId > 0) payload.reseller_svp_user_id = resellerId
    return run("bot_admin_id_remove", payload)
  }

  const openEdit = (row: DashRecord) => {
    const ov = parseTextOverrides(row)
    setEditRow(row)
    setEditForm({
      reseller_svp_user_id: String(resellerIdOf(row)),
      brand_name: String(row.brand_name ?? ""),
      logo_url: String(row.logo_url ?? ""),
      favicon_url: String(row.favicon_url ?? ""),
      theme_primary: String(row.theme_primary ?? ""),
      theme_accent: String(row.theme_accent ?? ""),
      custom_domain: String(row.custom_domain ?? ""),
      telegram_relay_public_url: String(row.telegram_relay_public_url ?? ""),
      telegram_token: "",
      bale_token: "",
      bale_wallet_provider_token: "",
      text_msg_welcome: String(ov["msg.welcome"] ?? ""),
      text_btn_support_contact: String(ov["btn.support.contact"] ?? ""),
      text_btn_support_faq: String(ov["btn.support.faq"] ?? ""),
    })
  }

  const saveReseller = async () => {
    const textOverrides = {
      "msg.welcome": String(editForm.text_msg_welcome ?? ""),
      "btn.support.contact": String(editForm.text_btn_support_contact ?? ""),
      "btn.support.faq": String(editForm.text_btn_support_faq ?? ""),
    }
    const payload: Record<string, unknown> = {
      reseller_svp_user_id: num(editForm.reseller_svp_user_id),
      enabled: editRow?.enabled !== false,
      brand_name: editForm.brand_name,
      logo_url: editForm.logo_url,
      favicon_url: editForm.favicon_url,
      theme_primary: editForm.theme_primary,
      theme_accent: editForm.theme_accent,
      custom_domain: editForm.custom_domain,
      telegram_relay_public_url: String(editForm.telegram_relay_public_url ?? ""),
      text_overrides: textOverrides,
      text_overrides_json: JSON.stringify(textOverrides),
    }
    for (const key of ["telegram_token", "bale_token", "bale_wallet_provider_token"]) {
      if (editForm[key]?.trim()) payload[key] = editForm[key]!.trim()
    }
    if (await run("bot_reseller_save", payload)) setEditRow(null)
  }

  const openMirrorDlg = (row: DashRecord | null) => {
    setMirrorDlgRow(row)
    setMirrorForm({
      label: row?.label ? String(row.label) : "",
      telegram_token: "",
      telegram_secret_token: row?.telegram_secret_token_set ? "" : "",
    })
    setMirrorDlgOpen(true)
  }

  const saveMirror = async () => {
    const payload: Record<string, unknown> = { label: mirrorForm.label.trim() }
    if (mirrorForm.telegram_token.trim()) payload.telegram_token = mirrorForm.telegram_token.trim()
    if (mirrorForm.telegram_secret_token.trim()) payload.telegram_secret_token = mirrorForm.telegram_secret_token.trim()
    if (mirrorDlgRow?.mirror_id) payload.mirror_id = num(mirrorDlgRow.mirror_id)
    if (await run("telegram_mirror_save", payload)) setMirrorDlgOpen(false)
  }

  const mainTelegramOn = bool(settings.telegram_enabled) || bool(settings.telegram_bot_enabled)
  const mainBaleOn = bool(settings.bale_enabled) || bool(settings.bale_bot_enabled)
  const mainTgIds = useMemo(() => asIdList(settings.admin_telegram_ids), [settings])
  const mainBaleIds = useMemo(() => asIdList(settings.admin_bale_ids), [settings])

  const renderIds = (platform: "telegram" | "bale", ids: number[], resellerId = 0) => {
    const key = `${platform}:${resellerId}`
    return (
      <div className="space-y-2 rounded-md border p-3">
        <Label className="text-xs">{platform === "telegram" ? t("adminTelegramIds") : t("adminBaleIds")}</Label>
        <div className="flex flex-wrap gap-1">
          {ids.length ? (
            ids.map((id) => (
              <Badge key={`${key}-${id}`} variant="secondary" className="gap-1">
                <span dir="ltr">{id}</span>
                <button type="button" onClick={() => void removeAdminId(platform, id, resellerId)}>
                  ×
                </button>
              </Badge>
            ))
          ) : (
            <span className="text-xs text-muted-foreground">{t("adminIdEmpty")}</span>
          )}
        </div>
        <div className="flex gap-2">
          <Input
            value={adminIdForm[key] ?? ""}
            onChange={(e) => setAdminIdForm((current) => ({ ...current, [key]: e.target.value }))}
            placeholder={t("adminIdPlaceholder")}
            dir="ltr"
            disabled={isBusy}
          />
          <Button type="button" size="sm" disabled={isBusy} onClick={() => saveAdminId(platform, resellerId)}>
            {t("adminIdAdd")}
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load()}>
          {t("refresh")}
        </Button>
      </div>
      {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}
      {err ? <p className="text-sm text-destructive">{err}</p> : null}
      {msg ? <p className="text-sm text-emerald-600 dark:text-emerald-400">{msg}</p> : null}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("mainBotSectionTitle")}</CardTitle>
          <CardDescription>{t("mainBotSectionDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {relayOn ? (
            <p className="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-muted-foreground">
              {t("relayTelegramBanner")}
            </p>
          ) : null}
          {showWebhookRate ? (
            <p className="text-xs text-muted-foreground">
              {t("webhookRate")}: {formatNumber(num(settings.webhook_rate_limit_per_min), isFa)}
            </p>
          ) : null}
          <div className="grid gap-4 md:grid-cols-2">
            {(["telegram", "bale"] as const).map((platform) => {
              const on = platform === "telegram" ? mainTelegramOn : mainBaleOn
              const username = String(
                platform === "telegram" ? settings.telegram_bot_username ?? "" : settings.bale_bot_username ?? ""
              )
              return (
                <div key={platform} className="space-y-3 rounded-lg border p-3">
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                      <MessageCircle className="size-4" />
                      <p className="text-sm font-medium">
                        {platform === "telegram" ? t("platformTelegram") : t("platformBale")}
                      </p>
                    </div>
                    <Badge variant={on ? "default" : "secondary"}>
                      {on ? t("platformEnabled") : t("platformDisabled")}
                    </Badge>
                  </div>
                  <p className="text-xs text-muted-foreground" dir="ltr">
                    @{username || "-"}
                  </p>
                  {relayOn && platform === "telegram" ? (
                    <p className="text-xs text-muted-foreground" dir="ltr">
                      {t("relayWebhookVia")}:{" "}
                      {String(
                        settings.telegram_relay_public_url || settings.telegram_relay_base_url || "—"
                      )}
                    </p>
                  ) : null}
                  <div className="flex flex-wrap gap-2">
                    <Button
                      type="button"
                      size="sm"
                      variant={on ? "destructive" : "default"}
                      disabled={isBusy}
                      onClick={() => void togglePlatform(platform)}
                    >
                      {platform === "telegram"
                        ? on
                          ? t("btnDisableTelegram")
                          : t("btnEnableTelegram")
                        : on
                          ? t("btnDisableBale")
                          : t("btnEnableBale")}
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={isBusy}
                      onClick={() =>
                        void run(platform === "telegram" ? "bot_test_telegram" : "bot_test_bale", {}, t("testOk"))
                      }
                    >
                      <Send className="size-3.5" />
                      {platform === "telegram" ? t("testTelegramShort") : t("testBaleShort")}
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={isBusy}
                      onClick={() => void run("bot_set_webhook", webhookPayload(platform, 0))}
                    >
                      <Webhook className="size-3.5" />
                      {platform === "telegram" ? t("actionSetWebhookTg") : t("actionSetWebhookBale")}
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={isBusy}
                      onClick={() => setDeleteWebhook({ platform, resellerId: 0 })}
                    >
                      <Trash2 className="size-3.5" />
                      {platform === "telegram" ? t("actionDeleteWebhookTg") : t("actionDeleteWebhookBale")}
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      onClick={() => setDiag({ platform, resellerId: 0 })}
                    >
                      <Stethoscope className="size-3.5" />
                      {platform === "telegram" ? t("diagnosticsTelegram") : t("diagnosticsBale")}
                    </Button>
                  </div>
                </div>
              )
            })}
          </div>

          <div className="grid gap-3 md:grid-cols-2">
            {[
              ["telegram_token", "telegramToken", bool(settings.telegram_token_set)],
              ["bale_token", "baleToken", bool(settings.bale_token_set)],
              ["telegram_secret_header", "telegramSecretHeader", false],
              ["bale_wallet_provider_token", "baleWalletToken", bool(settings.bale_wallet_provider_token_set)],
            ].map(([key, labelKey, configured]) => (
              <div key={String(key)} className="space-y-2">
                <Label>{t(String(labelKey))}</Label>
                <Input
                  type={key === "telegram_secret_header" ? "text" : "password"}
                  value={tokenForm[String(key)] ?? ""}
                  onChange={(e) => setTokenForm((f) => ({ ...f, [String(key)]: e.target.value }))}
                  placeholder={configured ? t("tokenConfigured") : t("placeholderSecret")}
                  dir="ltr"
                />
              </div>
            ))}
          </div>
          <Button type="button" disabled={isBusy} onClick={() => void saveTokens()}>
            {t("saveTokens")}
          </Button>
          <div className="grid gap-3 md:grid-cols-2">
            {renderIds("telegram", mainTgIds)}
            {renderIds("bale", mainBaleIds)}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="space-y-1">
              <CardTitle className="text-base">{t("mirrorBotsSectionTitle")}</CardTitle>
              <CardDescription>{t("mirrorBotsSectionDesc")}</CardDescription>
            </div>
            <Button type="button" size="sm" disabled={isBusy} onClick={() => openMirrorDlg(null)}>
              {t("mirrorAdd")}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {mirrors.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("mirrorEmpty")}</p>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("mirrorLabel")}</TableHead>
                    <TableHead>{t("tgUser")}</TableHead>
                    <TableHead>{t("tokenColTelegram")}</TableHead>
                    <TableHead>{t("platformEnabled")}</TableHead>
                    <TableHead>{t("resellerColActions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {mirrors.map((row) => {
                    const mid = num(row.mirror_id)
                    const un = String(row.telegram_bot_username || "").trim()
                    const enabled = bool(row.enabled)
                    return (
                      <TableRow key={mid}>
                        <TableCell>{String(row.label || "-")}</TableCell>
                        <TableCell dir="ltr">@{un || "-"}</TableCell>
                        <TableCell>
                          <Badge variant={bool(row.has_telegram_token) ? "default" : "outline"}>
                            {bool(row.has_telegram_token) ? "yes" : "-"}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <Badge variant={enabled ? "default" : "secondary"}>
                            {enabled ? t("statusEnabled") : t("statusDisabled")}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={isBusy}
                              onClick={() => openMirrorDlg(row)}
                            >
                              {t("mirrorEdit")}
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              onClick={() => setDiag({ platform: "telegram", resellerId: 0, mirrorId: mid })}
                            >
                              {t("diagnosticsShort")}
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={isBusy}
                              onClick={() => void run("telegram_mirror_toggle", { mirror_id: mid, enabled: !enabled })}
                            >
                              {t("actionToggle")}
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={isBusy}
                              onClick={() => void run("telegram_mirror_test", { mirror_id: mid }, t("testOk"))}
                            >
                              <Send className="size-3.5" />
                              {t("testTelegramShort")}
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={isBusy}
                              onClick={() => void run("telegram_mirror_set_webhook", { mirror_id: mid })}
                            >
                              <Webhook className="size-3.5" />
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={isBusy}
                              onClick={() =>
                                void run("telegram_mirror_delete_webhook", { mirror_id: mid }, t("webhookDeleted"))
                              }
                            >
                              <Trash2 className="size-3.5" />
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="destructive"
                              disabled={isBusy}
                              onClick={() => setMirrorDeleteDlg(row)}
                            >
                              {t("mirrorDelete")}
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("resellerBots")}</CardTitle>
          <CardDescription>{t("resellerBotsDesc")}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>#</TableHead>
                  <TableHead>{t("resellerColReseller")}</TableHead>
                  <TableHead>{t("resellerColBrand")}</TableHead>
                  <TableHead>{t("tokenColTelegram")}</TableHead>
                  <TableHead>{t("tokenColBale")}</TableHead>
                  <TableHead>{t("resellerColStatus")}</TableHead>
                  <TableHead>{t("moreActions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {bots.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center text-muted-foreground">
                      {t("resellerEmpty")}
                    </TableCell>
                  </TableRow>
                ) : (
                  bots.map((row, idx) => {
                    const rid = resellerIdOf(row)
                    return (
                      <TableRow key={`${rid}-${idx}`}>
                        <TableCell dir="ltr">{rid}</TableCell>
                        <TableCell>{String(row.reseller_name ?? "-")}</TableCell>
                        <TableCell>{String(row.brand_name ?? "-")}</TableCell>
                        <TableCell>
                          <Badge variant={hasPlatformToken(row, "telegram") ? "default" : "outline"}>
                            {hasPlatformToken(row, "telegram") ? "✓" : "—"}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <Badge variant={hasPlatformToken(row, "bale") ? "default" : "outline"}>
                            {hasPlatformToken(row, "bale") ? "✓" : "—"}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <Badge variant={row.enabled === false ? "secondary" : "default"}>
                            {row.enabled === false ? t("statusDisabled") : t("statusEnabled")}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <DropdownMenu>
                            <DropdownMenuTrigger disabled={isBusy}>
                              <Button type="button" size="icon" variant="ghost" className="size-8">
                                <EllipsisVertical className="size-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align={isFa ? "start" : "end"}>
                              <DropdownMenuItem onClick={() => openEdit(row)}>
                                <Pencil className="size-4" />
                                {t("actionEdit")}
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                onClick={() => void run("bot_reseller_toggle_enabled", { reseller_svp_user_id: rid })}
                              >
                                <Power className="size-4" />
                                {t("actionToggle")}
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => void togglePlatform("telegram", rid)}>
                                <MessageCircle className="size-4" />
                                {platformToggleLabel("telegram", resellerPlatformEnabled(row, "telegram"))}
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => void togglePlatform("bale", rid)}>
                                <MessagesSquare className="size-4" />
                                {platformToggleLabel("bale", resellerPlatformEnabled(row, "bale"))}
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                disabled={!hasPlatformToken(row, "telegram")}
                                onClick={() =>
                                  void run("bot_test_telegram", { reseller_svp_user_id: rid }, t("testOk"))
                                }
                              >
                                <Send className="size-4" />
                                {t("testTelegramShort")}
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                disabled={!hasPlatformToken(row, "bale")}
                                onClick={() => void run("bot_test_bale", { reseller_svp_user_id: rid }, t("testOk"))}
                              >
                                <MessagesSquare className="size-4" />
                                {t("testBaleShort")}
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => setDiag({ platform: "telegram", resellerId: rid })}>
                                <Stethoscope className="size-4" />
                                {t("diagnosticsTelegram")}
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => setDiag({ platform: "bale", resellerId: rid })}>
                                <Stethoscope className="size-4" />
                                {t("diagnosticsBale")}
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                onClick={() => void run("bot_set_webhook", webhookPayload("telegram", rid))}
                              >
                                <Webhook className="size-4" />
                                {t("actionSetWebhookTg")}
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                onClick={() => void run("bot_set_webhook", webhookPayload("bale", rid))}
                              >
                                <Webhook className="size-4" />
                                {t("actionSetWebhookBale")}
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => setDeleteWebhook({ platform: "telegram", resellerId: rid })}>
                                <Trash2 className="size-4" />
                                {t("actionDeleteWebhookTg")}
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => setDeleteWebhook({ platform: "bale", resellerId: rid })}>
                                <Trash2 className="size-4" />
                                {t("actionDeleteWebhookBale")}
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                onClick={() => void run("bot_reseller_secret_rotate", { reseller_svp_user_id: rid })}
                              >
                                <RefreshCw className="size-4" />
                                {t("actionRotateSecret")}
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                variant="destructive"
                                onClick={() => void run("bot_reseller_delete", { reseller_svp_user_id: rid })}
                              >
                                <Trash2 className="size-4" />
                                {t("actionDelete")}
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </TableCell>
                      </TableRow>
                    )
                  })
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <DashboardForceJoinAdmin settings={settings} onMutateSuccess={() => void load()} />

      <Dialog open={mirrorDlgOpen} onOpenChange={setMirrorDlgOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{mirrorDlgRow ? t("mirrorEdit") : t("mirrorAdd")}</DialogTitle>
            <DialogDescription>{t("mirrorBotsSectionDesc")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="space-y-2">
              <Label>{t("mirrorLabel")}</Label>
              <Input
                value={mirrorForm.label}
                onChange={(e) => setMirrorForm((f) => ({ ...f, label: e.target.value }))}
                disabled={isBusy}
              />
            </div>
            <Input
              type="password"
              placeholder={mirrorDlgRow?.has_telegram_token ? t("tokenConfigured") : t("telegramToken")}
              value={mirrorForm.telegram_token}
              onChange={(e) => setMirrorForm((f) => ({ ...f, telegram_token: e.target.value }))}
              disabled={isBusy}
              dir="ltr"
            />
            <Input
              type="password"
              placeholder={t("telegramSecretHeader")}
              value={mirrorForm.telegram_secret_token}
              onChange={(e) => setMirrorForm((f) => ({ ...f, telegram_secret_token: e.target.value }))}
              disabled={isBusy}
              dir="ltr"
            />
            {mirrorDlgRow?.webhook_telegram_url ? (
              <>
                <p className="text-xs text-muted-foreground break-all" dir="ltr">
                  {String(mirrorDlgRow.webhook_telegram_url)}
                </p>
                <p className="text-xs text-muted-foreground">{t("mirrorWebhookBrowserHint")}</p>
              </>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setMirrorDlgOpen(false)}>
              {t("adminIdCancel")}
            </Button>
            <Button type="button" disabled={isBusy} onClick={() => void saveMirror()}>
              {t("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={mirrorDeleteDlg !== null} onOpenChange={(open) => !open && setMirrorDeleteDlg(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t("mirrorDelete")}</DialogTitle>
            <DialogDescription>{t("mirrorDeleteConfirm")}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setMirrorDeleteDlg(null)}>
              {t("adminIdCancel")}
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={isBusy}
              onClick={() => {
                const mid = num(mirrorDeleteDlg?.mirror_id)
                void run("telegram_mirror_delete", { mirror_id: mid }).then((ok) => {
                  if (ok) setMirrorDeleteDlg(null)
                })
              }}
            >
              {t("mirrorDelete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editRow !== null} onOpenChange={(open) => !open && setEditRow(null)}>
        <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t("resellerDialogTitle")}</DialogTitle>
            <DialogDescription className="text-xs">{t("resellerWebhookAutoHint")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="space-y-1">
              <Label className="text-xs">{t("resellerPlaceholderId")}</Label>
              <Input
                dir="ltr"
                value={editForm.reseller_svp_user_id ?? ""}
                readOnly
                disabled
                className="h-9 font-mono"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t("resellerPlaceholderBrand")}</Label>
              <Input
                value={editForm.brand_name ?? ""}
                onChange={(e) => setEditForm((f) => ({ ...f, brand_name: e.target.value }))}
                disabled={isBusy}
                className="h-9"
              />
            </div>
            <p className="text-xs text-muted-foreground">{t("configNamingMovedHint")}</p>
            {(["logo_url", "favicon_url", "custom_domain"] as const).map((key) => (
              <Input
                key={key}
                placeholder={t(
                  key === "logo_url"
                    ? "brandingLogoUrl"
                    : key === "favicon_url"
                      ? "brandingFaviconUrl"
                      : "brandingCustomDomain"
                )}
                value={editForm[key] ?? ""}
                onChange={(e) => setEditForm((f) => ({ ...f, [key]: e.target.value }))}
                dir="ltr"
                disabled={isBusy}
              />
            ))}
            <div className="grid grid-cols-2 gap-2">
              <Input
                placeholder={t("brandingThemePrimary")}
                value={editForm.theme_primary ?? ""}
                onChange={(e) => setEditForm((f) => ({ ...f, theme_primary: e.target.value }))}
                dir="ltr"
                disabled={isBusy}
              />
              <Input
                placeholder={t("brandingThemeAccent")}
                value={editForm.theme_accent ?? ""}
                onChange={(e) => setEditForm((f) => ({ ...f, theme_accent: e.target.value }))}
                dir="ltr"
                disabled={isBusy}
              />
            </div>
            {relayOn ? (
              <Input
                placeholder={t("relayPublicUrlReseller")}
                value={editForm.telegram_relay_public_url ?? ""}
                onChange={(e) => setEditForm((f) => ({ ...f, telegram_relay_public_url: e.target.value }))}
                dir="ltr"
                disabled={isBusy}
              />
            ) : null}
            <Input
              type="password"
              autoComplete="off"
              placeholder={
                editRow && hasPlatformToken(editRow, "telegram") && !editForm.telegram_token
                  ? t("tokenConfigured")
                  : t("dlgPhTelegramToken")
              }
              value={editForm.telegram_token ?? ""}
              onChange={(e) => setEditForm((f) => ({ ...f, telegram_token: e.target.value }))}
              disabled={isBusy}
            />
            <Input
              type="password"
              autoComplete="off"
              placeholder={
                editRow && hasPlatformToken(editRow, "bale") && !editForm.bale_token
                  ? t("tokenConfigured")
                  : t("dlgPhBaleToken")
              }
              value={editForm.bale_token ?? ""}
              onChange={(e) => setEditForm((f) => ({ ...f, bale_token: e.target.value }))}
              disabled={isBusy}
            />
            <Input
              type="password"
              autoComplete="off"
              placeholder={t("dlgPhBaleWallet")}
              value={editForm.bale_wallet_provider_token ?? ""}
              onChange={(e) => setEditForm((f) => ({ ...f, bale_wallet_provider_token: e.target.value }))}
              disabled={isBusy}
            />
            <div className="space-y-1">
              <Label className="text-xs">{t("textWelcomeOverride")}</Label>
              <Textarea
                value={editForm.text_msg_welcome ?? ""}
                onChange={(e) => setEditForm((f) => ({ ...f, text_msg_welcome: e.target.value }))}
                disabled={isBusy}
                rows={4}
                className="text-sm"
              />
              <p className="text-[11px] text-muted-foreground">{t("textWelcomeHint")}</p>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t("textSupportContactOverride")}</Label>
              <Input
                value={editForm.text_btn_support_contact ?? ""}
                onChange={(e) => setEditForm((f) => ({ ...f, text_btn_support_contact: e.target.value }))}
                disabled={isBusy}
                className="h-9"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t("textSupportFaqOverride")}</Label>
              <Input
                value={editForm.text_btn_support_faq ?? ""}
                onChange={(e) => setEditForm((f) => ({ ...f, text_btn_support_faq: e.target.value }))}
                disabled={isBusy}
                className="h-9"
              />
            </div>
            {editRow ? (
              <>
                {renderIds("telegram", asIdList(editRow.admin_telegram_ids), resellerIdOf(editRow))}
                {renderIds("bale", asIdList(editRow.admin_bale_ids), resellerIdOf(editRow))}
              </>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setEditRow(null)} disabled={isBusy}>
              {t("adminIdCancel")}
            </Button>
            <Button type="button" disabled={isBusy} onClick={() => void saveReseller()}>
              {t("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={deleteWebhook !== null} onOpenChange={(open) => !open && setDeleteWebhook(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {deleteWebhook?.platform === "bale" ? t("actionDeleteWebhookBale") : t("actionDeleteWebhookTg")}
            </DialogTitle>
            <DialogDescription>{t("confirmDeleteWebhook")}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDeleteWebhook(null)}>
              {t("adminIdCancel")}
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={isBusy}
              onClick={() => {
                if (!deleteWebhook) return
                void run(
                  "bot_delete_webhook",
                  webhookPayload(deleteWebhook.platform, deleteWebhook.resellerId),
                  t("webhookDeleted")
                ).then((ok) => {
                  if (ok) setDeleteWebhook(null)
                })
              }}
            >
              {deleteWebhook?.platform === "bale" ? t("actionDeleteWebhookBale") : t("actionDeleteWebhookTg")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <DashboardBotDiagnosticsDialog
        open={diag !== null}
        platform={diag?.platform ?? "telegram"}
        resellerId={diag?.resellerId ?? 0}
        mirrorId={diag?.mirrorId ?? 0}
        onClose={() => setDiag(null)}
      />
    </div>
  )
}
