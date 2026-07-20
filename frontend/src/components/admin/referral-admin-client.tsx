"use client"

import { useTranslations } from "next-intl"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"

import { useCallback, useEffect, useMemo, useState } from "react"

import { Button } from "@/components/ui/button"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DashPage } from "@/components/dash-page"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { DataPagination } from "@/components/data-pagination"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Skeleton } from "@/components/ui/skeleton"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber, formatServiceExpiryLine } from "@/lib/format-locale"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"
import { mainEnabledPlatforms } from "@/lib/enabled-platforms"
import { useDashLocale } from "@/lib/dash-locale-context"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function asRecord(v: unknown): DashRecord {
  return v && typeof v === "object" ? (v as DashRecord) : {}
}

export function ReferralAdminView({
  mode = "settings",
  settings,
  referralStats,
  referralEvents,
  eventsPagination,
  readOnlySettings = false,
  onMutateSuccess,
  onEventsPageChange,
  onEventsPerPageChange,
  onOpenUserDetail,
}: {
  mode?: "settings" | "reports"
  settings: DashRecord | undefined
  referralStats: unknown
  referralEvents: DashRecord[]
  eventsPagination: PaginationMeta | null
  /** Hide global referral program settings (resellers see scoped stats only). */
  readOnlySettings?: boolean
onMutateSuccess?: () => void
  onEventsPageChange?: (page: number) => void
  onEventsPerPageChange?: (n: number) => void
  onOpenUserDetail?: (svpUserId: number) => void
}) {
  const { isFa, ltrCell } = useDashLocale()

  const t = useTranslations("referralAdmin")
  const tp = (k: string) => t(`${k}`)
  const platformLabel = (p: string) =>
    t(`platform_${p}`, { defaultValue: p || "—" })
  const outcomeLabel = (o: string) =>
    t(`outcome_${o}`, { defaultValue: o || "—" })
  const isReports = mode === "reports"
  const s = settings ?? {}
  const stats =
    referralStats != null && typeof referralStats === "object" ? asRecord(referralStats) : null
  const summary = stats ? asRecord(stats.summary) : {}
  const statsLoading = isReports && referralStats == null

  const initial = useMemo(
    () => ({
      referral_enabled: bool(s.referral_enabled),
      referral_percent: String(s.referral_percent ?? "0"),
      referral_min_payout_base: String(s.referral_min_payout_base ?? "0"),
      referral_example_base_toman: String(s.referral_example_base_toman ?? "170000"),
      referral_example_invite_count: String(Math.max(1, num(s.referral_example_invite_count) || 10)),
      referral_require_approved_referrer: bool(s.referral_require_approved_referrer),
      telegram_bot_username: String(s.telegram_bot_username ?? ""),
      bale_bot_username: String(s.bale_bot_username ?? ""),
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => {
    setForm(initial)
  }, [initial])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const onSave = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "referral",
        referral_enabled: form.referral_enabled ? 1 : 0,
        referral_percent: form.referral_percent.trim(),
        referral_min_payout_base: form.referral_min_payout_base.trim(),
        referral_example_base_toman: form.referral_example_base_toman.trim(),
        referral_example_invite_count: num(form.referral_example_invite_count),
        referral_require_approved_referrer: form.referral_require_approved_referrer ? 1 : 0,
        telegram_bot_username: form.telegram_bot_username.trim(),
        bale_bot_username: form.bale_bot_username.trim(),
      })
      if (!res.ok) {
        setError(res.message || t("saveError"))
        return
      }
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, onMutateSuccess, tp])

  const top =
    stats && Array.isArray(stats.topReferrers) ? (stats.topReferrers as DashRecord[]) : []

  const hasEvents = referralEvents.length > 0 || (eventsPagination && eventsPagination.total > 0)
  const showSettings = !isReports && !readOnlySettings
  const showTgBot = mainEnabledPlatforms(s).includes("telegram")
  const showBaleBot = mainEnabledPlatforms(s).includes("bale")

  const userLink = (id: number, label: string) => {
    if (id > 0 && onOpenUserDetail) {
      return (
        <Button
          type="button"
          variant="link"
          size="sm"
          className={cn("h-auto p-0 font-mono text-xs", ltrCell(""))}
          dir="ltr"
          onClick={() => onOpenUserDetail(id)}
        >
          {label}
        </Button>
      )
    }
    return (
      <span dir="ltr" className={ltrCell("font-mono text-xs")}>
        {label}
      </span>
    )
  }

  return (
    <DashPage className={"w-full space-y-6"} data-testid="dash-referral-tab">
      <DashboardPageHeader
        title={isReports ? t("reportsTitle") : t("title")}
        description={isReports ? t("reportsSubtitle") : t("subtitle")}
      />

      {!isReports && readOnlySettings ? (
        <p className="text-sm text-muted-foreground">{t("settingsReadOnlyHint")}</p>
      ) : null}

      {isReports ? (
        <div className="space-y-6">
          {statsLoading ? (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i}>
                  <CardHeader className="pb-2">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="mt-2 h-8 w-20" />
                  </CardHeader>
                </Card>
              ))}
            </div>
          ) : stats ? (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <StatCard label={t("statEvents30")} value={formatNumber(num(summary.eventsLast30), isFa)} />
              <StatCard label={t("statInvitedUsers")} value={formatNumber(num(summary.invitedUsersWithReferrer), isFa)} />
              <StatCard label={t("statCommissionPaid")} value={formatNumber(num(summary.totalCommissionPaid), isFa)} />
              <StatCard
                label={t("statReferralOnPurchases")}
                value={formatNumber(num(summary.totalReferralAmountOnPurchases), isFa)}
              />
            </div>
          ) : null}

          <div className="grid gap-6 xl:grid-cols-2 xl:items-start">
            <Card className="min-w-0">
              <CardHeader>
                <CardTitle className="text-base">{t("topReferrers")}</CardTitle>
                <CardDescription>{t("topReferrersDesc")}</CardDescription>
              </CardHeader>
              <CardContent>
                {statsLoading ? (
                  <Skeleton className="h-32 w-full" />
                ) : stats && top.length > 0 ? (
                  <DashTableShell minWidth="32rem" colWidths={["8%", "32%", "20%", "20%", "20%"]}>
                    <thead>
                      <tr className="bg-muted/40">
                        <DashTh>#</DashTh>
                        <DashTh>{t("colReferrer")}</DashTh>
                        <DashTh>{t("colDirectInvites")}</DashTh>
                        <DashTh>{t("colCommissionCount")}</DashTh>
                        <DashTh>{t("colCommissionSum")}</DashTh>
                      </tr>
                    </thead>
                    <tbody>
                      {top.map((row, i) => {
                        const id = num(row.referrerId)
                        const label =
                          String(row.username || "").trim() !== ""
                            ? `@${String(row.username)}`
                            : `${String(row.firstName || "").trim() || "—"} (#${formatNumber(id, isFa)})`
                        return (
                          <tr key={id || i}>
                            <DashTd className="text-muted-foreground">{i + 1}</DashTd>
                            <DashTd dir="ltr" className={ltrCell("font-mono text-xs")}>
                              {userLink(id, label)}
                            </DashTd>
                            <DashTd>{formatNumber(num(row.directInvites), isFa)}</DashTd>
                            <DashTd>{formatNumber(num(row.commissionCount), isFa)}</DashTd>
                            <DashTd>{formatNumber(num(row.commissionTotal), isFa)}</DashTd>
                          </tr>
                        )
                      })}
                    </tbody>
                  </DashTableShell>
                ) : (
                  <p className="text-sm text-muted-foreground">{t("topReferrersEmpty")}</p>
                )}
              </CardContent>
            </Card>

            <Card className="min-w-0 w-full">
              <CardHeader>
                <CardTitle className="text-base">{t("recentEvents")}</CardTitle>
                <CardDescription>{t("recentEventsDesc")}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {statsLoading ? (
                  <Skeleton className="h-48 w-full" />
                ) : hasEvents ? (
                  <>
                    <DashTableShell
                      minWidth="48rem"
                      colWidths={["18%", "12%", "12%", "14%", "14%", "30%"]}
                    >
                      <thead>
                        <tr className="bg-muted/40">
                          <DashTh>{t("colTime")}</DashTh>
                          <DashTh>{t("colInviter")}</DashTh>
                          <DashTh>{t("colPlatform")}</DashTh>
                          <DashTh>{t("colOutcome")}</DashTh>
                          <DashTh>{t("colVisitor")}</DashTh>
                          <DashTh>{t("colPayload")}</DashTh>
                        </tr>
                      </thead>
                      <tbody>
                        {referralEvents.map((ev) => (
                          <tr key={String(ev.id ?? "")}>
                            <DashTd className="whitespace-nowrap text-xs">
                              {ev.created_at ? formatServiceExpiryLine(String(ev.created_at), isFa) : "—"}
                            </DashTd>
                            <DashTd dir="ltr" className={ltrCell("font-mono text-xs")}>
                              {userLink(num(ev.inviter_svp_user_id), formatNumber(num(ev.inviter_svp_user_id), isFa))}
                            </DashTd>
                            <DashTd className="text-xs">{platformLabel(String(ev.platform ?? ""))}</DashTd>
                            <DashTd className="text-xs">{outcomeLabel(String(ev.outcome ?? ""))}</DashTd>
                            <DashTd dir="ltr" className={ltrCell("font-mono text-xs")}>
                              {userLink(num(ev.resulting_svp_user_id), formatNumber(num(ev.resulting_svp_user_id), isFa))}
                            </DashTd>
                            <DashTd dir="ltr" className={ltrCell("max-w-[12rem] truncate font-mono text-xs")}>
                              {String(ev.start_payload ?? "")}
                            </DashTd>
                          </tr>
                        ))}
                      </tbody>
                    </DashTableShell>
                    <DataPagination
                      meta={eventsPagination}
                      onPageChange={(p) => onEventsPageChange?.(p)}
                      onPerPageChange={(n) => onEventsPerPageChange?.(n)}
                    />
                  </>
                ) : (
                  <p className="text-sm text-muted-foreground">{t("recentEventsEmpty")}</p>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      ) : null}

      {showSettings ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("cardTitle")}</CardTitle>
            <CardDescription>{t("cardDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <label className={cn("flex items-center gap-2 text-sm")}>
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.referral_enabled}
                onChange={(e) => setForm((f) => ({ ...f, referral_enabled: e.target.checked }))}
              />
              {t("enabled")}
            </label>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="r_pct">{t("percent")}</Label>
                <Input
                  id="r_pct"
                  inputMode="decimal"
                  value={form.referral_percent}
                  onChange={(e) => setForm((f) => ({ ...f, referral_percent: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="r_min">{t("minPayout")}</Label>
                <Input
                  id="r_min"
                  inputMode="decimal"
                  value={form.referral_min_payout_base}
                  onChange={(e) => setForm((f) => ({ ...f, referral_min_payout_base: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="r_ex_base">{t("exampleBase")}</Label>
                <Input
                  id="r_ex_base"
                  inputMode="decimal"
                  value={form.referral_example_base_toman}
                  onChange={(e) => setForm((f) => ({ ...f, referral_example_base_toman: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="r_ex_n">{t("exampleInvites")}</Label>
                <Input
                  id="r_ex_n"
                  type="number"
                  min={1}
                  value={form.referral_example_invite_count}
                  onChange={(e) => setForm((f) => ({ ...f, referral_example_invite_count: e.target.value }))}
                />
              </div>
              {showTgBot ? (
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="r_tg">{t("telegramBotUsername")}</Label>
                <Input
                  id="r_tg"
                  value={form.telegram_bot_username}
                  onChange={(e) => setForm((f) => ({ ...f, telegram_bot_username: e.target.value }))}
                />
              </div>
              ) : null}
              {showBaleBot ? (
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="r_bl">{t("baleBotUsername")}</Label>
                <Input
                  id="r_bl"
                  value={form.bale_bot_username}
                  onChange={(e) => setForm((f) => ({ ...f, bale_bot_username: e.target.value }))}
                />
              </div>
              ) : null}
            </div>
            <label className={cn("flex items-center gap-2 text-sm")}>
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.referral_require_approved_referrer}
                onChange={(e) => setForm((f) => ({ ...f, referral_require_approved_referrer: e.target.checked }))}
              />
              {t("requireApproved")}
            </label>
            {error ? (
              <div
                role="alert"
                className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
              >
                {error}
              </div>
            ) : null}
            <Button type="button" disabled={saving} onClick={() => void onSave()}>
              {t("save")}
            </Button>
          </CardContent>
        </Card>
      ) : null}
    </DashPage>
  )
}

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardDescription>{label}</CardDescription>
        <CardTitle className="text-xl tabular-nums">{value}</CardTitle>
      </CardHeader>
    </Card>
  )
}

export function ReferralAdminClient({ reports = false }: { reports?: boolean }) {
  const tab = reports ? "referral_reports" : "referral"
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, isReseller } = useAdminTabState(tab)
  const t = useTranslations("referralAdmin")
  if (loading) return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <ReferralAdminView
      mode={reports ? "reports" : "settings"}
      settings={data.settings as Record<string, unknown> | undefined}
      referralStats={data.referralStats}
      referralEvents={rows(data.referralEvents)}
      eventsPagination={pickPagination("referralEvents")}
      readOnlySettings={isReseller}
      onMutateSuccess={reload}
      onEventsPageChange={(p) => setPage("referralEvents", p)}
      onEventsPerPageChange={(n) => setPer("referralEvents", n)}
    />
  )
}
