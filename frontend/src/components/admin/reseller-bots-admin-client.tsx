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
  Trash2,
  Webhook,
} from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
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
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DataPagination } from "@/components/data-pagination"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import { useDashLocale } from "@/lib/dash-locale-context"

type DashRecord = Record<string, unknown>
type BotRow = DashRecord

const RESELLER_BOTS_TABLE_COLS = ["6%", "18%", "16%", "10%", "10%", "12%", "6%"]

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function asIdList(v: unknown): number[] {
  return Array.isArray(v) ? v.map(num).filter((id) => id > 0) : []
}

function resellerPlatformEnabled(row: BotRow, platform: "telegram" | "bale"): boolean {
  if (platform === "telegram") return bool(row.telegram_enabled)
  return bool(row.bale_enabled)
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
      <MessageCircle className="size-4" aria-hidden={platform !== "telegram"} />
      <Badge variant={configured ? "default" : "outline"} className="text-xs font-normal">
        {configured ? configuredLabel : emptyLabel}
      </Badge>
    </div>
  )
}

export function ResellerBotsAdminClient() {
  const t = useTranslations("botsAdmin")
  const { isFa } = useDashLocale()
  const { data, loading, reload, isReseller, setPage, setPer, pickPagination, rows } =
    useAdminTabState("reseller_bots")

  const botsList = rows(data.botsList)
  const botsPagination = pickPagination("botsList")
  const resellerSelfServe = isReseller

  const [busyAction, setBusyAction] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [okMsg, setOkMsg] = useState<string | null>(null)
  const [dlgOpen, setDlgOpen] = useState(false)
  const [dlgRow, setDlgRow] = useState<BotRow | null>(null)
  const [dlgForm, setDlgForm] = useState<Record<string, string>>({})
  const [adminIdForm, setAdminIdForm] = useState<Record<string, string>>({})
  const [deleteHookDlg, setDeleteHookDlg] = useState<{ platform: "telegram" | "bale"; botId: number } | null>(null)

  const busy = busyAction !== ""

  useEffect(() => {
    if (!dlgOpen || !dlgRow) return
    const updated = botsList.find((r) => num(r.reseller_id) === num(dlgRow.reseller_id))
    if (updated) setDlgRow(updated)
  }, [botsList, dlgOpen, dlgRow])

  const runBotAction = useCallback(
    async (op: string, payload: Record<string, unknown>): Promise<boolean> => {
      setBusyAction(op)
      setError(null)
      setOkMsg(null)
      try {
        const res = await postAdminMutate(op, payload)
        if (!res.ok) {
          setError(res.message || t("saveError"))
          return false
        }
        if (op === "bot_test_telegram" || op === "bot_test_bale") {
          setOkMsg(t("testOk"))
        } else if (op === "bot_delete_webhook" || op === "reseller_bot_webhook_delete") {
          setOkMsg(t("webhookDeleted"))
        } else {
          setOkMsg(t("saved"))
        }
        reload()
        return true
      } finally {
        setBusyAction("")
      }
    },
    [reload, t]
  )

  const platformToggleLabel = (plat: "telegram" | "bale", on: boolean) => {
    if (plat === "telegram") return on ? t("btnDisableTelegram") : t("btnEnableTelegram")
    return on ? t("btnDisableBale") : t("btnEnableBale")
  }

  const togglePlatform = (plat: "telegram" | "bale", resellerId = 0) => {
    const payload: Record<string, unknown> = { platform: plat }
    if (resellerId > 0) payload.reseller_svp_user_id = resellerId
    return runBotAction("bot_toggle_platform_enabled", payload)
  }

  const webhookPayload = (platform: "telegram" | "bale", botId: number) =>
    resellerSelfServe && botId < 1 ? { platform } : botId < 1 ? { platform, bot_id: 0 } : { platform, bot_id: botId }

  const setWebhookOp = (botId: number) =>
    resellerSelfServe && botId < 1 ? "reseller_bot_webhook_set" : "bot_set_webhook"

  const deleteWebhookOp = (botId: number) =>
    resellerSelfServe && botId < 1 ? "reseller_bot_webhook_delete" : "bot_delete_webhook"

  const openResellerDlg = (row: BotRow) => {
    const ov =
      row.text_overrides && typeof row.text_overrides === "object"
        ? (row.text_overrides as Record<string, string>)
        : {}
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
      telegram_token: "",
      bale_token: "",
      bale_wallet_provider_token: "",
      text_msg_welcome: String(ov["msg.welcome"] ?? ""),
      text_btn_support_contact: String(ov["btn.support.contact"] ?? ""),
      text_btn_support_faq: String(ov["btn.support.faq"] ?? ""),
    })
    setDlgOpen(true)
  }

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
      text_overrides: {
        "msg.welcome": String(dlgForm.text_msg_welcome ?? ""),
        "btn.support.contact": String(dlgForm.text_btn_support_contact ?? ""),
        "btn.support.faq": String(dlgForm.text_btn_support_faq ?? ""),
      },
    }
    if (dlgForm.telegram_token?.trim()) payload.telegram_token = dlgForm.telegram_token.trim()
    if (dlgForm.bale_token?.trim()) payload.bale_token = dlgForm.bale_token.trim()
    if (dlgForm.bale_wallet_provider_token?.trim()) {
      payload.bale_wallet_provider_token = dlgForm.bale_wallet_provider_token.trim()
    }
    return payload
  }

  const saveAdminId = (platform: "telegram" | "bale", resellerId: number) => {
    const key = `${platform}:${resellerId}`
    const id = num(adminIdForm[key])
    if (id < 1) return
    const payload: Record<string, unknown> = { platform, admin_id: id }
    if (resellerId > 0) payload.reseller_svp_user_id = resellerId
    void runBotAction("bot_admin_id_add", payload)
    setAdminIdForm((current) => ({ ...current, [key]: "" }))
  }

  const removeAdminId = (platform: "telegram" | "bale", id: number, resellerId: number) => {
    const payload: Record<string, unknown> = { platform, admin_id: id }
    if (resellerId > 0) payload.reseller_svp_user_id = resellerId
    return runBotAction("bot_admin_id_remove", payload)
  }

  const renderAdminIds = (platform: "telegram" | "bale", ids: number[], resellerId: number) => {
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
            className="h-8"
          />
          <Button type="button" size="sm" disabled={busy} onClick={() => saveAdminId(platform, resellerId)}>
            {t("adminIdAdd")}
          </Button>
        </div>
      </div>
    )
  }

  const enabledCount = useMemo(() => botsList.filter((r) => bool(r.enabled)).length, [botsList])
  const soleResellerRow = resellerSelfServe && botsList.length === 1 ? botsList[0] : null

  return (
    <DashPage>
      <DashboardPageHeader title={t("resellerBots")} description={t("resellerBotsDesc")} />

      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}
      {okMsg && !error ? <p className="text-sm text-emerald-600 dark:text-emerald-400">{okMsg}</p> : null}

      <div className="grid gap-3 sm:grid-cols-2">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t("resellerBots")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(botsList.length, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t("platformEnabled")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(enabledCount, isFa)}</CardTitle>
          </CardHeader>
        </Card>
      </div>

      <Card>
        <CardContent className="space-y-3 pt-6">
          {loading ? <p className="text-sm text-muted-foreground">{t("loadError")}</p> : null}

          {soleResellerRow ? (
            <div className="space-y-4 rounded-lg border border-border/60 p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1 text-start">
                  <p className="text-sm font-medium">
                    {String(soleResellerRow.brand_name ?? soleResellerRow.reseller_name ?? "—")}
                  </p>
                  <p className="text-xs text-muted-foreground" dir="ltr">
                    #{num(soleResellerRow.reseller_id)}
                  </p>
                </div>
                <Badge variant={bool(soleResellerRow.enabled) ? "default" : "secondary"}>
                  {bool(soleResellerRow.enabled) ? t("statusEnabled") : t("statusDisabled")}
                </Badge>
              </div>
              <div className="flex flex-wrap gap-4">
                <PlatformTokenCell
                  platform="telegram"
                  configured={Boolean(soleResellerRow.has_telegram_token)}
                  configuredLabel={t("tokenColTelegram")}
                  emptyLabel="—"
                />
                <PlatformTokenCell
                  platform="bale"
                  configured={Boolean(soleResellerRow.has_bale_token)}
                  configuredLabel={t("tokenColBale")}
                  emptyLabel="—"
                />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => openResellerDlg(soleResellerRow)}>
                  <Pencil className="size-4" />
                  {t("actionEdit")}
                </Button>
              </div>
            </div>
          ) : botsList.length === 0 && !loading ? (
            <p className="text-sm text-muted-foreground">{t("mirrorEmpty")}</p>
          ) : (
            <DashTableShell minWidth="40rem" colWidths={RESELLER_BOTS_TABLE_COLS}>
              <thead>
                <tr className="bg-muted/40 text-xs">
                  <DashTh>#</DashTh>
                  <DashTh>{t("resellerColReseller")}</DashTh>
                  <DashTh>{t("resellerColBrand")}</DashTh>
                  <DashTh>{t("colTgShort")}</DashTh>
                  <DashTh>{t("colBaleShort")}</DashTh>
                  <DashTh>{t("resellerColStatus")}</DashTh>
                  <DashTh>{t("moreActions")}</DashTh>
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
                      <DashTd className="truncate">{String(row.reseller_name ?? "—")}</DashTd>
                      <DashTd className="truncate">{String(row.brand_name ?? "—")}</DashTd>
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
                        <Badge variant={bool(row.enabled) ? "default" : "secondary"} className="text-xs">
                          {bool(row.enabled) ? t("statusEnabled") : t("statusDisabled")}
                        </Badge>
                      </DashTd>
                      <DashTd>
                        <DropdownMenu>
                          <DropdownMenuTrigger disabled={busy}>
                            <Button type="button" size="icon" variant="ghost" className="size-8">
                              <EllipsisVertical className="size-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align={isFa ? "start" : "end"}>
                            <DropdownMenuItem onClick={() => openResellerDlg(row)}>
                              <Pencil className="size-4" />
                              {t("actionEdit")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => void runBotAction("bot_reseller_toggle_enabled", { reseller_svp_user_id: rid })}
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
                              disabled={!row.has_telegram_token}
                              onClick={() => void runBotAction("bot_test_telegram", { reseller_svp_user_id: rid })}
                            >
                              <Send className="size-4" />
                              {t("testTelegramShort")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              disabled={!row.has_bale_token}
                              onClick={() => void runBotAction("bot_test_bale", { reseller_svp_user_id: rid })}
                            >
                              <MessagesSquare className="size-4" />
                              {t("testBaleShort")}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() => void runBotAction(setWebhookOp(rid), webhookPayload("telegram", rid))}
                            >
                              <Webhook className="size-4" />
                              {t("actionSetWebhookTg")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => void runBotAction(setWebhookOp(rid), webhookPayload("bale", rid))}
                            >
                              <Webhook className="size-4" />
                              {t("actionSetWebhookBale")}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setDeleteHookDlg({ platform: "telegram", botId: rid })}>
                              <Trash2 className="size-4" />
                              {t("actionDeleteWebhookTg")}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setDeleteHookDlg({ platform: "bale", botId: rid })}>
                              <Trash2 className="size-4" />
                              {t("actionDeleteWebhookBale")}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() => void runBotAction("bot_reseller_secret_rotate", { reseller_svp_user_id: rid })}
                            >
                              <RefreshCw className="size-4" />
                              {t("actionRotateSecret")}
                            </DropdownMenuItem>
                            {!resellerSelfServe ? (
                              <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                onClick={() => void runBotAction("bot_reseller_delete", { reseller_svp_user_id: rid })}
                              >
                                <Trash2 className="size-4" />
                                {t("actionDelete")}
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
            meta={botsPagination}
            onPageChange={(p) => setPage("botsList", p)}
            onPerPageChange={(n) => setPer("botsList", n)}
            perPageOptions={[25, 50, 100, 150, 200]}
          />
        </CardContent>
      </Card>

      <Dialog open={deleteHookDlg !== null} onOpenChange={(o) => !o && setDeleteHookDlg(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {deleteHookDlg?.platform === "bale" ? t("actionDeleteWebhookBale") : t("actionDeleteWebhookTg")}
            </DialogTitle>
            <DialogDescription>{t("confirmDeleteWebhook")}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDeleteHookDlg(null)} disabled={busy}>
              {t("adminIdCancel")}
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
              {deleteHookDlg?.platform === "bale" ? t("actionDeleteWebhookBale") : t("actionDeleteWebhookTg")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={dlgOpen} onOpenChange={setDlgOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{t("resellerDialogTitle")}</DialogTitle>
            <DialogDescription className="text-xs">{t("resellerWebhookAutoHint")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="space-y-1">
              <Label className="text-xs">{t("resellerPlaceholderId")}</Label>
              <Input dir="ltr" value={dlgForm.reseller_svp_user_id ?? ""} readOnly disabled className="h-9 font-mono" />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t("resellerPlaceholderBrand")}</Label>
              <Input
                value={dlgForm.brand_name ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, brand_name: e.target.value }))}
                disabled={busy}
                className="h-9"
              />
            </div>
            <p className="text-xs text-muted-foreground">{t("configNamingMovedHint")}</p>
            {["logo_url", "favicon_url", "custom_domain"].map((key) => (
              <Input
                key={key}
                placeholder={t(key === "logo_url" ? "brandingLogoUrl" : key === "favicon_url" ? "brandingFaviconUrl" : "brandingCustomDomain")}
                value={dlgForm[key] ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, [key]: e.target.value }))}
                dir="ltr"
                disabled={busy}
              />
            ))}
            <div className="grid grid-cols-2 gap-2">
              <Input
                placeholder={t("brandingThemePrimary")}
                value={dlgForm.theme_primary ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, theme_primary: e.target.value }))}
                dir="ltr"
                disabled={busy}
              />
              <Input
                placeholder={t("brandingThemeAccent")}
                value={dlgForm.theme_accent ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, theme_accent: e.target.value }))}
                dir="ltr"
                disabled={busy}
              />
            </div>
            <Input
              placeholder={
                dlgRow?.has_telegram_token && !dlgForm.telegram_token ? t("tokenConfigured") : t("dlgPhTelegramToken")
              }
              value={dlgForm.telegram_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, telegram_token: e.target.value }))}
              type="password"
              autoComplete="off"
              disabled={busy}
            />
            <Input
              placeholder={dlgRow?.has_bale_token && !dlgForm.bale_token ? t("tokenConfigured") : t("dlgPhBaleToken")}
              value={dlgForm.bale_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, bale_token: e.target.value }))}
              type="password"
              autoComplete="off"
              disabled={busy}
            />
            <Input
              placeholder={t("dlgPhBaleWallet")}
              value={dlgForm.bale_wallet_provider_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, bale_wallet_provider_token: e.target.value }))}
              type="password"
              autoComplete="off"
              disabled={busy}
            />
            <div className="space-y-1">
              <Label className="text-xs">{t("textWelcomeOverride")}</Label>
              <Textarea
                value={dlgForm.text_msg_welcome ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, text_msg_welcome: e.target.value }))}
                disabled={busy}
                rows={4}
                className="text-sm"
              />
              <p className="text-[11px] text-muted-foreground">{t("textWelcomeHint")}</p>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t("textSupportContactOverride")}</Label>
              <Input
                value={dlgForm.text_btn_support_contact ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, text_btn_support_contact: e.target.value }))}
                disabled={busy}
                className="h-9"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t("textSupportFaqOverride")}</Label>
              <Input
                value={dlgForm.text_btn_support_faq ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, text_btn_support_faq: e.target.value }))}
                disabled={busy}
                className="h-9"
              />
            </div>
            {dlgRow ? (
              <>
                {renderAdminIds("telegram", asIdList(dlgRow.admin_telegram_ids), num(dlgRow.reseller_id))}
                {renderAdminIds("bale", asIdList(dlgRow.admin_bale_ids), num(dlgRow.reseller_id))}
              </>
            ) : null}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDlgOpen(false)} disabled={busy}>
              {t("adminIdCancel")}
            </Button>
            <Button
              disabled={busy}
              onClick={() => {
                void runBotAction("bot_reseller_save", buildResellerSavePayload()).then((ok) => {
                  if (ok) setDlgOpen(false)
                })
              }}
            >
              {t("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </DashPage>
  )
}
