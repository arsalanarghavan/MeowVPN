"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import {
  EllipsisVertical,
  MessagesSquare,
  Pencil,
  Power,
  RefreshCw,
  Send,
  Stethoscope,
  Trash2,
  Webhook,
} from "lucide-react"

import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { AdminIdChips } from "@/components/dashboard-bots-admin-ids"
import { DashboardBotDiagnosticsDialog } from "@/components/dashboard-bot-diagnostics-dialog"

const RESELLER_BOTS_TABLE_COLS = ["6%", "18%", "16%", "10%", "10%", "12%", "6%"]
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DashboardForceJoinAdmin } from "@/components/dashboard-force-join-admin"
import { BaleLogo } from "@/components/icons/bale-logo"
import { TelegramLogo } from "@/components/icons/telegram-logo"
import { DashPage } from "@/components/dash-page"
import { BOT_PLATFORMS, type BotPlatformForm, type BotPlatformId } from "@/config/bot-platforms"
import { mainPlatformEnabled, resellerPlatformEnabled } from "@/lib/enabled-platforms"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { DataPagination } from "@/components/data-pagination"
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
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

type DashRecord = Record<string, unknown>
type BotRow = {
  reseller_id?: number
  reseller_name?: string
  reseller_status?: string
  brand_name?: string
  logo_url?: string
  favicon_url?: string
  theme_primary?: string
  theme_accent?: string
  custom_domain?: string
  telegram_relay_public_url?: string
  config_label_override?: string
  config_label_prefix?: string
  enabled?: boolean
  telegram_enabled?: boolean
  bale_enabled?: boolean
  has_telegram_token?: boolean
  has_bale_token?: boolean
  telegram_secret_token_set?: boolean
  webhook_telegram_url?: string
  webhook_bale_url?: string
  admin_telegram_ids?: number[]
  admin_bale_ids?: number[]
  text_overrides?: Record<string, string>
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function asIdList(v: unknown): number[] {
  if (!Array.isArray(v)) return []
  return v.map((x) => num(x)).filter((x) => x > 0)
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function isSetFlag(s: DashRecord, key: string): boolean {
  const v = s[key]
  return v === true || v === 1 || v === "1"
}

function PlatformTokenCell({
  configured,
  platform,
  configuredLabel,
  emptyLabel,
}: {
  configured: boolean
  platform: "telegram" | "bale"
  configuredLabel: string
  emptyLabel: string
}) {
  return (
    <div className="flex items-center justify-start gap-1.5 text-start">
      {platform === "telegram" ? (
        <TelegramLogo className="size-4" />
      ) : (
        <BaleLogo className="size-4" />
      )}
      <Badge variant={configured ? "default" : "outline"} className="text-xs font-normal">
        {configured ? configuredLabel : emptyLabel}
      </Badge>
    </div>
  )
}

export type BotsAdminVariant = "site" | "reseller_admin" | "reseller_self"

export function DashboardBotsAdmin({
  settings,
  botsList = [],
  botsPagination,
  variant = "site",
  onPageChange,
  onPerPageChange,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
/** site = main bot only; reseller_admin = reseller bot table; reseller_self = own reseller bot. */
  variant?: BotsAdminVariant
  botsList?: BotRow[]
  botsPagination?: PaginationMeta | null
  onPageChange?: (p: number) => void
  onPerPageChange?: (n: number) => void
  onMutateSuccess?: () => void
}) {
  const { isFa } = useDashLocale()

  const resellerSelfServe = variant === "reseller_self"
  const showMainBot = variant === "site"
  const showResellerTable = variant === "reseller_admin" || variant === "reseller_self"
  const { t } = useTranslation()
  const tp = (k: string) => t(`botsAdmin.${k}`)
  const s = settings ?? {}

  const initial = useMemo(
    () =>
      ({
        telegram_token: "",
        bale_token: "",
        telegram_secret_header: String(s.telegram_secret_header ?? ""),
        bale_wallet_provider_token: "",
      }) satisfies BotPlatformForm,
    [s.telegram_secret_header]
  )

  const tokenConfigured = useMemo(
    () => ({
      telegram_token: isSetFlag(s, "telegram_token_set"),
      bale_token: isSetFlag(s, "bale_token_set"),
      bale_wallet_provider_token: isSetFlag(s, "bale_wallet_provider_token_set"),
    }),
    [s]
  )

  const [form, setForm] = useState<BotPlatformForm>(initial)
  useEffect(() => {
    setForm(initial)
  }, [initial])

  const mainTgIds = asIdList(s.admin_telegram_ids)
  const mainBaleIds = asIdList(s.admin_bale_ids)

  const [saving, setSaving] = useState(false)
  const [busyAction, setBusyAction] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [okMsg, setOkMsg] = useState<string | null>(null)
  const [dlgOpen, setDlgOpen] = useState(false)
  const [dlgRow, setDlgRow] = useState<BotRow | null>(null)
  const [dlgForm, setDlgForm] = useState<Record<string, string>>({})

  useEffect(() => {
    if (!dlgOpen || !dlgRow) return
    const updated = botsList.find((r) => num(r.reseller_id) === num(dlgRow.reseller_id))
    if (updated) setDlgRow(updated)
  }, [botsList, dlgOpen, dlgRow])
  const [deleteHookDlg, setDeleteHookDlg] = useState<{
    platform: "telegram" | "bale"
    botId: number
  } | null>(null)
  const [diagOpen, setDiagOpen] = useState<{ platform: "telegram" | "bale"; resellerId: number } | null>(
    null
  )

  const busy = busyAction !== "" || saving

  const runBotAction = useCallback(
    async (op: string, payload: Record<string, unknown>): Promise<boolean> => {
      setBusyAction(op)
      setError(null)
      setOkMsg(null)
      try {
        const res = await postAdminMutate(op, payload)
        if (!res.ok) {
          setError(res.message || tp("saveError"))
          return false
        }
        if (op === "bot_test_telegram" || op === "bot_test_bale") {
          setOkMsg(tp("testOk"))
        } else if (op === "bot_delete_webhook" || op === "reseller_bot_webhook_delete") {
          setOkMsg(tp("webhookDeleted"))
        } else {
          setOkMsg(tp("saved"))
        }
        onMutateSuccess?.()
        return true
      } finally {
        setBusyAction("")
      }
    },
    [onMutateSuccess, tp]
  )

  const onSaveTokens = useCallback(async () => {
    setSaving(true)
    setError(null)
    setOkMsg(null)
    try {
      const payload: Record<string, unknown> = {
        tab: "bots",
        telegram_secret_header: form.telegram_secret_header,
      }
      if (form.telegram_token.trim() !== "") {
        payload.telegram_token = form.telegram_token.trim()
      }
      if (form.bale_token.trim() !== "") {
        payload.bale_token = form.bale_token.trim()
      }
      if (form.bale_wallet_provider_token.trim() !== "") {
        payload.bale_wallet_provider_token = form.bale_wallet_provider_token.trim()
      }
      const res = await postAdminMutate("settings_tab", payload)
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      setForm((f) => ({
        ...f,
        telegram_token: "",
        bale_token: "",
        bale_wallet_provider_token: "",
      }))
      setOkMsg(tp("saved"))
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, onMutateSuccess, tp])

  const tgUser = String(s.telegram_bot_username ?? "")
  const baleUser = String(s.bale_bot_username ?? "")
  const relayFeatureOn = (() => {
    const f = s.features
    return !!(f && typeof f === "object" && (f as Record<string, unknown>).relay === true)
  })()
  const relayOn =
    relayFeatureOn &&
    (bool(s.telegram_relay_enabled) || bool(s.telegram_relay_force)) &&
    String(s.telegram_relay_admin_url || s.telegram_relay_base_url || s.telegram_relay_public_url || "").trim() !== ""

  const platformOn = (plat: BotPlatformId) => mainPlatformEnabled(s, plat)
  const resellerPlatformOn = (row: BotRow, plat: BotPlatformId) =>
    resellerPlatformEnabled(row as Record<string, unknown>, plat)

  const togglePlatform = (plat: BotPlatformId, resellerId = 0) => {
    const payload: Record<string, unknown> = { platform: plat }
    if (resellerId > 0) payload.reseller_svp_user_id = resellerId
    return runBotAction("bot_toggle_platform_enabled", payload)
  }

  const platformToggleLabel = (plat: BotPlatformId, on: boolean) => {
    if (plat === "telegram") return on ? tp("btnDisableTelegram") : tp("btnEnableTelegram")
    return on ? tp("btnDisableBale") : tp("btnEnableBale")
  }

  const openResellerDlg = (row: BotRow) => {
    const ov = row.text_overrides ?? {}
    setDlgRow(row)
    setDlgForm({
      reseller_svp_user_id: String(row.reseller_id ?? 0),
      brand_name: String(row.brand_name ?? ""),
      logo_url: String(row.logo_url ?? ""),
      favicon_url: String(row.favicon_url ?? ""),
      theme_primary: String(row.theme_primary ?? ""),
      theme_accent: String(row.theme_accent ?? ""),
      custom_domain: String(row.custom_domain ?? ""),
      telegram_relay_public_url: String(row.telegram_relay_public_url ?? ""),
      config_label_override: String(row.config_label_override ?? ""),
      config_label_prefix: String(row.config_label_prefix ?? ""),
      telegram_token: "",
      bale_token: "",
      bale_wallet_provider_token: "",
      text_msg_welcome: String(ov["msg.welcome"] ?? ""),
      text_btn_support_contact: String(ov["btn.support.contact"] ?? ""),
      text_btn_support_faq: String(ov["btn.support.faq"] ?? ""),
    })
    setDlgOpen(true)
  }

  const fieldInput = (
    key: keyof BotPlatformForm,
    labelKey: string,
    type: "text" | "password" = "password",
    configured = false
  ) => (
    <div className="space-y-1.5" key={key}>
      <Label htmlFor={key} className="text-xs">
        {tp(labelKey)}
      </Label>
      <Input
        id={key}
        type={type}
        autoComplete="off"
        className="h-9"
        value={form[key]}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.value }))}
        placeholder={
          type === "password"
            ? configured && !form[key]
              ? tp("tokenConfigured")
              : tp("placeholderSecret")
            : ""
        }
        disabled={busy}
      />
    </div>
  )

  const buildResellerSavePayload = (): Record<string, unknown> => {
    const payload: Record<string, unknown> = {
      reseller_svp_user_id: Number(dlgForm.reseller_svp_user_id || "0"),
      enabled: dlgRow?.enabled !== false,
      brand_name: dlgForm.brand_name,
      logo_url: dlgForm.logo_url,
      favicon_url: dlgForm.favicon_url,
      theme_primary: dlgForm.theme_primary,
      theme_accent: dlgForm.theme_accent,
      custom_domain: dlgForm.custom_domain,
      config_label_override: String(dlgForm.config_label_override ?? ""),
      config_label_prefix: String(dlgForm.config_label_prefix ?? ""),
      text_overrides: {
        "msg.welcome": String(dlgForm.text_msg_welcome ?? ""),
        "btn.support.contact": String(dlgForm.text_btn_support_contact ?? ""),
        "btn.support.faq": String(dlgForm.text_btn_support_faq ?? ""),
      },
    }
    if (dlgForm.telegram_token?.trim()) {
      payload.telegram_token = dlgForm.telegram_token.trim()
    }
    if (dlgForm.bale_token?.trim()) {
      payload.bale_token = dlgForm.bale_token.trim()
    }
    if (dlgForm.bale_wallet_provider_token?.trim()) {
      payload.bale_wallet_provider_token = dlgForm.bale_wallet_provider_token.trim()
    }
    return payload
  }

  const webhookPayload = (platform: "telegram" | "bale", botId: number) =>
    resellerSelfServe && botId < 1
      ? { platform }
      : botId < 1
        ? { platform, bot_id: 0 }
        : { platform, bot_id: botId }

  const setWebhookOp = (botId: number) =>
    resellerSelfServe && botId < 1 ? "reseller_bot_webhook_set" : "bot_set_webhook"

  const deleteWebhookOp = (botId: number) =>
    resellerSelfServe && botId < 1 ? "reseller_bot_webhook_delete" : "bot_delete_webhook"

  const pageTitle = showResellerTable && !showMainBot ? tp("resellerBots") : tp("title")
  const pageDesc = showResellerTable && !showMainBot ? tp("resellerBotsDesc") : tp("subtitle")
  const soleResellerRow = resellerSelfServe && botsList.length === 1 ? botsList[0] : null

  return (
    <DashPage>
      <DashboardPageHeader title={pageTitle} description={pageDesc} />
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

      {showMainBot ? (
        <Card>
          <CardHeader className="pb-3">
            <div>
              <CardTitle className="text-base">{tp("mainBotSectionTitle")}</CardTitle>
              <CardDescription className="text-xs">{tp("webhookSecretHint")}</CardDescription>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {relayOn ? (
              <p className="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-muted-foreground">
                {tp("relayTelegramBanner")}
              </p>
            ) : null}
            <p className="text-xs text-muted-foreground">
              {tp("webhookRate")}: {formatNumber(num(s.webhook_rate_limit_per_min), isFa)}
            </p>
            <div className="grid gap-4 md:grid-cols-2">
              {BOT_PLATFORMS.map((plat) => {
                const on = platformOn(plat.id)
                const username = plat.id === "telegram" ? tgUser : baleUser
                return (
                  <div key={plat.id} className="space-y-3 rounded-lg border border-border/60 p-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div className="flex items-center gap-2">
                        {plat.id === "telegram" ? (
                          <TelegramLogo className="size-5" />
                        ) : (
                          <BaleLogo className="size-5" />
                        )}
                        <p className="text-sm font-medium">{t(plat.titleKey)}</p>
                      </div>
                      <Badge variant={on ? "default" : "secondary"}>
                        {on ? tp("platformEnabled") : tp("platformDisabled")}
                      </Badge>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      {t(plat.summaryUsernameKey)}: {username ? `@${username}` : "—"}
                    </p>
                    {relayOn && plat.id === "telegram" ? (
                      <p className="text-xs text-muted-foreground" dir="ltr">
                        {tp("relayWebhookVia")}: {String(s.telegram_relay_public_url || s.telegram_relay_base_url || "—")}
                      </p>
                    ) : null}
                    <div className={cn("flex flex-wrap gap-2")}>
                      <Button
                        type="button"
                        size="sm"
                        variant={on ? "destructive" : "default"}
                        className="gap-1.5"
                        disabled={busy}
                        onClick={() => void togglePlatform(plat.id)}
                      >
                        <Power className="size-3.5" />
                        {platformToggleLabel(plat.id, on)}
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="gap-1.5"
                        disabled={busy}
                        onClick={() =>
                          void runBotAction(
                            plat.id === "telegram" ? "bot_test_telegram" : "bot_test_bale",
                            {}
                          )
                        }
                      >
                        {plat.id === "telegram" ? (
                          <Send className="size-3.5" />
                        ) : (
                          <MessagesSquare className="size-3.5" />
                        )}
                        {plat.id === "telegram" ? tp("testTelegramShort") : tp("testBaleShort")}
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="gap-1.5"
                        disabled={busy}
                        onClick={() => setDiagOpen({ platform: plat.id, resellerId: 0 })}
                      >
                        <Stethoscope className="size-3.5" />
                        {tp("diagnosticsShort")}
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        className="gap-1.5"
                        disabled={busy}
                        onClick={() =>
                          void runBotAction("bot_set_webhook", { platform: plat.id, bot_id: 0 })
                        }
                      >
                        <Webhook className="size-3.5" />
                        {plat.id === "telegram" ? tp("actionSetWebhookTg") : tp("actionSetWebhookBale")}
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="gap-1.5"
                        disabled={busy}
                        onClick={() => setDeleteHookDlg({ platform: plat.id, botId: 0 })}
                      >
                        <Trash2 className="size-3.5" />
                        {plat.id === "telegram" ? tp("actionDeleteWebhookTg") : tp("actionDeleteWebhookBale")}
                      </Button>
                    </div>
                    <AdminIdChips
                      platform={plat.id}
                      label={plat.id === "telegram" ? tp("adminTelegramIds") : tp("adminBaleIds")}
                      ids={plat.id === "telegram" ? mainTgIds : mainBaleIds}
                      busy={busy}
                      tp={tp}
                      onChanged={() => onMutateSuccess?.()}
                      onError={setError}
                    />
                    {plat.fieldKeys.map((fk) => {
                      const labelMap: Partial<Record<keyof BotPlatformForm, string>> = {
                        telegram_token: "telegramToken",
                        bale_token: "baleToken",
                        telegram_secret_header: "telegramSecretHeader",
                        bale_wallet_provider_token: "baleWalletToken",
                      }
                      const lk = labelMap[fk] ?? String(fk)
                      const inputType = fk === "telegram_secret_header" ? "text" : "password"
                      const configured =
                        fk === "telegram_token"
                          ? tokenConfigured.telegram_token
                          : fk === "bale_token"
                            ? tokenConfigured.bale_token
                            : fk === "bale_wallet_provider_token"
                              ? tokenConfigured.bale_wallet_provider_token
                              : false
                      return fieldInput(fk, lk, inputType, configured)
                    })}
                  </div>
                )
              })}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-3">
              <div>
                <p className="text-sm font-medium">{tp("saveTokens")}</p>
                <p className="text-xs text-muted-foreground">{tp("saveTokensDesc")}</p>
              </div>
              <Button type="button" size="sm" disabled={busy} onClick={() => void onSaveTokens()}>
                {tp("saveTokens")}
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {showMainBot ? (
        <Card>
          <CardContent className="pt-6">
            <DashboardForceJoinAdmin
              settings={s}
              onMutateSuccess={onMutateSuccess}
            />
          </CardContent>
        </Card>
      ) : null}

      {showResellerTable ? (
      <Card>
        <CardContent className="space-y-3 pt-6">
          {soleResellerRow ? (
            <div className="space-y-4 rounded-lg border border-border/60 p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1 text-start">
                  <p className="text-sm font-medium">{soleResellerRow.brand_name || soleResellerRow.reseller_name || "—"}</p>
                  <p className="text-xs text-muted-foreground" dir="ltr">
                    #{num(soleResellerRow.reseller_id)}
                  </p>
                </div>
                <Badge variant={soleResellerRow.enabled ? "default" : "secondary"}>
                  {soleResellerRow.enabled ? tp("statusEnabled") : tp("statusDisabled")}
                </Badge>
              </div>
              <div className="flex flex-wrap gap-4">
                <PlatformTokenCell
                  platform="telegram"
                  configured={Boolean(soleResellerRow.has_telegram_token)}
                  configuredLabel={tp("tokenColTelegram")}
                  emptyLabel="—"
                />
                <PlatformTokenCell
                  platform="bale"
                  configured={Boolean(soleResellerRow.has_bale_token)}
                  configuredLabel={tp("tokenColBale")}
                  emptyLabel="—"
                />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => openResellerDlg(soleResellerRow)}>
                  <Pencil className="size-4" />
                  {tp("actionEdit")}
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="gap-1.5"
                  disabled={busy}
                  onClick={() =>
                    setDiagOpen({ platform: "telegram", resellerId: num(soleResellerRow.reseller_id) })
                  }
                >
                  <Stethoscope className="size-3.5" />
                  {tp("diagnosticsTelegram")}
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="gap-1.5"
                  disabled={busy}
                  onClick={() =>
                    setDiagOpen({ platform: "bale", resellerId: num(soleResellerRow.reseller_id) })
                  }
                >
                  <Stethoscope className="size-3.5" />
                  {tp("diagnosticsBale")}
                </Button>
              </div>
            </div>
          ) : botsList.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("resellerBotsDesc")}</p>
          ) : (
          <DashTableShell
        minWidth="40rem" colWidths={RESELLER_BOTS_TABLE_COLS}>
            <thead>
              <tr className="bg-muted/40 text-xs">
                <DashTh>#</DashTh>
                <DashTh>{tp("resellerColReseller")}</DashTh>
                <DashTh>{tp("resellerColBrand")}</DashTh>
                <DashTh>
                  <span className="inline-flex items-center gap-1">
                    <TelegramLogo className="size-3.5" />
                    {tp("colTgShort")}
                  </span>
                </DashTh>
                <DashTh>
                  <span className="inline-flex items-center gap-1">
                    <BaleLogo className="size-3.5" />
                    {tp("colBaleShort")}
                  </span>
                </DashTh>
                <DashTh>{tp("resellerColStatus")}</DashTh>
                <DashTh>{tp("moreActions")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {botsList.map((row, idx) => {
                const rid = num(row.reseller_id)
                return (
                  <tr key={`${rid}-${idx}`}>
                    <DashTd dir="ltr" className="font-mono text-xs">
                      {rid}
                    </DashTd>
                    <DashTd className="truncate">{row.reseller_name || "—"}</DashTd>
                    <DashTd className="truncate">{row.brand_name || "—"}</DashTd>
                    <DashTd>
                      <PlatformTokenCell
                        platform="telegram"
                        configured={Boolean(row.has_telegram_token)}
                        configuredLabel="✓"
                        emptyLabel="—"
                      />
                    </DashTd>
                    <DashTd>
                      <PlatformTokenCell
                        platform="bale"
                        configured={Boolean(row.has_bale_token)}
                        configuredLabel="✓"
                        emptyLabel="—"
                      />
                    </DashTd>
                    <DashTd>
                      <Badge variant={row.enabled ? "default" : "secondary"} className="text-xs">
                        {row.enabled ? tp("statusEnabled") : tp("statusDisabled")}
                      </Badge>
                    </DashTd>
                    <DashTd>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button type="button" size="icon" variant="ghost" className="size-8" disabled={busy}>
                              <EllipsisVertical className="size-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align={isFa ? "start" : "end"}>
                            <DropdownMenuItem onClick={() => openResellerDlg(row)}>
                              <Pencil className="size-4" />
                              {tp("actionEdit")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() =>
                                void runBotAction("bot_reseller_toggle_enabled", { reseller_svp_user_id: rid })
                              }
                            >
                              <Power className="size-4" />
                              {tp("actionToggle")}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => void togglePlatform("telegram", rid)}>
                              <TelegramLogo className="size-4" />
                              {platformToggleLabel("telegram", resellerPlatformOn(row, "telegram"))}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => void togglePlatform("bale", rid)}>
                              <BaleLogo className="size-4" />
                              {platformToggleLabel("bale", resellerPlatformOn(row, "bale"))}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              disabled={!row.has_telegram_token}
                              onClick={() =>
                                void runBotAction("bot_test_telegram", { reseller_svp_user_id: rid })
                              }
                            >
                              <Send className="size-4" />
                              {tp("testTelegramShort")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              disabled={!row.has_bale_token}
                              onClick={() => void runBotAction("bot_test_bale", { reseller_svp_user_id: rid })}
                            >
                              <MessagesSquare className="size-4" />
                              {tp("testBaleShort")}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setDiagOpen({ platform: "telegram", resellerId: rid })}>
                              <Stethoscope className="size-4" />
                              {tp("diagnosticsTelegram")}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setDiagOpen({ platform: "bale", resellerId: rid })}>
                              <Stethoscope className="size-4" />
                              {tp("diagnosticsBale")}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() =>
                                void runBotAction(setWebhookOp(rid), webhookPayload("telegram", rid))
                              }
                            >
                              <Webhook className="size-4" />
                              {tp("actionSetWebhookTg")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() =>
                                void runBotAction(setWebhookOp(rid), webhookPayload("bale", rid))
                              }
                            >
                              <Webhook className="size-4" />
                              {tp("actionSetWebhookBale")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => setDeleteHookDlg({ platform: "telegram", botId: rid })}
                            >
                              <Trash2 className="size-4" />
                              {tp("actionDeleteWebhookTg")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => setDeleteHookDlg({ platform: "bale", botId: rid })}
                            >
                              <Trash2 className="size-4" />
                              {tp("actionDeleteWebhookBale")}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() =>
                                void runBotAction("bot_reseller_secret_rotate", { reseller_svp_user_id: rid })
                              }
                            >
                              <RefreshCw className="size-4" />
                              {tp("actionRotateSecret")}
                            </DropdownMenuItem>
                            {!resellerSelfServe ? (
                              <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                onClick={() =>
                                  void runBotAction("bot_reseller_delete", { reseller_svp_user_id: rid })
                                }
                              >
                                <Trash2 className="size-4" />
                                {tp("actionDelete")}
                              </DropdownMenuItem>
                            ) : null}
                          </DropdownMenuContent>
                        </DropdownMenu>
                    </DashTd>
                  </tr>
                )
              })}
            </tbody>
          </DashTableShell>
          )}
          <DataPagination
            meta={botsPagination ?? null}
            onPageChange={(p) => onPageChange?.(p)}
            onPerPageChange={(n) => onPerPageChange?.(n)}
            perPageOptions={[25, 50, 100, 150, 200]}
          />
        </CardContent>
      </Card>
      ) : null}

      <DashboardBotDiagnosticsDialog
        open={diagOpen !== null}
        platform={diagOpen?.platform ?? "telegram"}
        resellerId={diagOpen?.resellerId ?? 0}
        onClose={() => setDiagOpen(null)}
      />

      <Dialog open={deleteHookDlg !== null} onOpenChange={(o) => !o && setDeleteHookDlg(null)}>
        <DashDialogContent className={cn("sm:max-w-md")}>
          <DashDialogHeader>
            <DialogTitle>
              {deleteHookDlg?.platform === "bale" ? tp("actionDeleteWebhookBale") : tp("actionDeleteWebhookTg")}
            </DialogTitle>
            <DialogDescription>{tp("confirmDeleteWebhook")}</DialogDescription>
          </DashDialogHeader>
          <DashDialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setDeleteHookDlg(null)} disabled={busy}>
              {tp("adminIdCancel")}
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={busy}
              onClick={() => {
                if (!deleteHookDlg) return
                const { platform, botId } = deleteHookDlg
                void runBotAction(deleteWebhookOp(botId), webhookPayload(platform, botId)).then((ok) => {
                  if (ok) setDeleteHookDlg(null)
                })
              }}
            >
              {deleteHookDlg?.platform === "bale" ? tp("actionDeleteWebhookBale") : tp("actionDeleteWebhookTg")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={dlgOpen} onOpenChange={setDlgOpen}>
        <DashDialogContent className={cn("sm:max-w-lg")}>
          <DashDialogHeader>
            <DialogTitle>{tp("resellerDialogTitle")}</DialogTitle>
            <DialogDescription className="text-xs">{tp("resellerWebhookAutoHint")}</DialogDescription>
          </DashDialogHeader>
          <div className="grid gap-3">
            <div className="space-y-1">
              <Label className="text-xs">{tp("resellerPlaceholderId")}</Label>
              <Input dir="ltr" value={dlgForm.reseller_svp_user_id ?? ""} readOnly disabled className="h-9 font-mono" />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{tp("resellerPlaceholderBrand")}</Label>
              <Input
                value={dlgForm.brand_name ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, brand_name: e.target.value }))}
                disabled={busy}
                className="h-9"
              />
            </div>
            <p className="text-xs text-muted-foreground">{tp("configNamingMovedHint")}</p>
            <Input
              placeholder={tp("brandingLogoUrl")}
              value={dlgForm.logo_url ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, logo_url: e.target.value }))}
              dir="ltr"
              disabled={busy}
            />
            <Input
              placeholder={tp("brandingFaviconUrl")}
              value={dlgForm.favicon_url ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, favicon_url: e.target.value }))}
              dir="ltr"
              disabled={busy}
            />
            <div className="grid grid-cols-2 gap-2">
              <Input
                placeholder={tp("brandingThemePrimary")}
                value={dlgForm.theme_primary ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, theme_primary: e.target.value }))}
                dir="ltr"
                disabled={busy}
              />
              <Input
                placeholder={tp("brandingThemeAccent")}
                value={dlgForm.theme_accent ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, theme_accent: e.target.value }))}
                dir="ltr"
                disabled={busy}
              />
            </div>
            <Input
              placeholder={tp("brandingCustomDomain")}
              value={dlgForm.custom_domain ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, custom_domain: e.target.value }))}
              dir="ltr"
              disabled={busy}
            />
            {relayOn ? (
              <Input
                placeholder={tp("relayPublicUrlReseller")}
                value={dlgForm.telegram_relay_public_url ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, telegram_relay_public_url: e.target.value }))}
                dir="ltr"
                disabled={busy}
              />
            ) : null}
            <Input
              placeholder={
                dlgRow?.has_telegram_token && !dlgForm.telegram_token
                  ? tp("tokenConfigured")
                  : tp("dlgPhTelegramToken")
              }
              value={dlgForm.telegram_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, telegram_token: e.target.value }))}
              type="password"
              autoComplete="off"
              disabled={busy}
            />
            <Input
              placeholder={
                dlgRow?.has_bale_token && !dlgForm.bale_token
                  ? tp("tokenConfigured")
                  : tp("dlgPhBaleToken")
              }
              value={dlgForm.bale_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, bale_token: e.target.value }))}
              type="password"
              autoComplete="off"
              disabled={busy}
            />
            <Input
              placeholder={tp("dlgPhBaleWallet")}
              value={dlgForm.bale_wallet_provider_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, bale_wallet_provider_token: e.target.value }))}
              type="password"
              autoComplete="off"
              disabled={busy}
            />
            <div className="space-y-1">
              <Label className="text-xs">{tp("textWelcomeOverride")}</Label>
              <Textarea
                value={dlgForm.text_msg_welcome ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, text_msg_welcome: e.target.value }))}
                disabled={busy}
                rows={4}
                className="text-sm"
              />
              <p className="text-[11px] text-muted-foreground">{tp("textWelcomeHint")}</p>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{tp("textSupportContactOverride")}</Label>
              <Input
                value={dlgForm.text_btn_support_contact ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, text_btn_support_contact: e.target.value }))}
                disabled={busy}
                className="h-9"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{tp("textSupportFaqOverride")}</Label>
              <Input
                value={dlgForm.text_btn_support_faq ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, text_btn_support_faq: e.target.value }))}
                disabled={busy}
                className="h-9"
              />
            </div>
            {dlgRow ? (
              <>
                <AdminIdChips
                  platform="telegram"
                  label={tp("adminTelegramIds")}
                  ids={dlgRow.admin_telegram_ids ?? []}
                  resellerId={num(dlgRow.reseller_id)}
                  busy={busy}
                  tp={tp}
                  onChanged={() => onMutateSuccess?.()}
                  onError={setError}
                />
                <AdminIdChips
                  platform="bale"
                  label={tp("adminBaleIds")}
                  ids={dlgRow.admin_bale_ids ?? []}
                  resellerId={num(dlgRow.reseller_id)}
                  busy={busy}
                  tp={tp}
                  onChanged={() => onMutateSuccess?.()}
                  onError={setError}
                />
              </>
            ) : null}
          </div>
          <DashDialogFooter className={cn("gap-2")}>
            <Button variant="outline" onClick={() => setDlgOpen(false)} disabled={busy}>
              {tp("adminIdCancel")}
            </Button>
            <Button
              disabled={busy}
              onClick={() => {
                void runBotAction("bot_reseller_save", buildResellerSavePayload()).then((ok) => {
                  if (ok) setDlgOpen(false)
                })
              }}
            >
              {tp("save")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </DashPage>
  )
}
