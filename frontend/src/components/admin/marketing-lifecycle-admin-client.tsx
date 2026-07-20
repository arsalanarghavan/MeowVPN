"use client"

import { useTranslations } from "next-intl"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
import { useDashboardShellOptional } from "@/components/dashboard-shell-provider"

import { useMemo, useState } from "react"
import { BookOpen, ChevronDown, Play, Send, Users, AlertTriangle } from "lucide-react"
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from "recharts"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DataPagination } from "@/components/data-pagination"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { DashSheetContent } from "@/components/dash-sheet-content"
import { Dialog, DialogTitle } from "@/components/ui/dialog"
import { Sheet, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { buildDashboardTabUrl } from "@/lib/dash-tab"
import { useChartPrimaryColor } from "@/lib/chart-accent"
import { adminMutateErrorText, postAdminMutate } from "@/lib/dash-admin-mutate"
import { useDashLocale } from "@/lib/dash-locale-context"
import { dashIconGapClass, dashLtrCell } from "@/lib/dash-locale"
import { formatChartDayLabel, formatNumber } from "@/lib/format-locale"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"

export type MarketingLifecycleStats = {
  window_days?: number
  since?: string
  summary?: Record<string, unknown>
}

export type MarketingRuleRow = {
  id?: number
  owner_svp_user_id?: number
  segment_key?: string
  enabled?: boolean
  priority?: number
  cooldown_days?: number
  after_days?: number
  pending_hours?: number
  funnel_idle_hours?: number
  expires_within_days?: number
  discount_type?: string
  discount_value?: number
  max_discount_toman?: number | null
  code_valid_days?: number
  max_uses_per_user?: number
  message_body?: string
  channel_telegram?: boolean
  channel_bale?: boolean
}

export type MarketingOfferRow = {
  id?: number
  rule_id?: number
  svp_user_id?: number
  discount_code?: string
  status?: string
  segment_key?: string
  user_label?: string
  sent_at?: string
  created_at?: string
  converted_transaction_id?: number
  revenue_toman?: number
}

export type MarketingFunnelDay = {
  date?: string
  registered?: number
  first_pending?: number
  first_paid?: number
}

export type MarketingRuleStatRow = {
  rule_id?: number
  segment_key?: string
  sent?: number
  converted?: number
  success_rate?: number
  revenue_toman?: number
  eligible_now?: number
}

const SEGMENTS = [
  "churned",
  "never_purchased",
  "abandoned_checkout",
  "stale_buy_funnel",
  "expiring_renew",
] as const

const OFFER_STATUSES = ["", "issued", "sent", "converted", "expired", "skipped"] as const

const RULES_TABLE_COLS = ["14%", "12%", "10%", "8%", "8%", "10%", "10%", "10%", "8%", "10%"]
const STATS_TABLE_COLS = ["14%", "12%", "10%", "10%", "10%", "12%", "12%", "20%"]
const OFFERS_TABLE_COLS = ["8%", "14%", "10%", "12%", "12%", "10%", "10%", "12%", "12%"]

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function pctDisplay(v: unknown, isFa: boolean): string {
  return `${formatNumber(num(v), isFa)}%`
}

function StatCard({ label, value, suffix }: { label: string; value: string | number; suffix?: string }) {
  const { isFa } = useDashLocale()
  const display = typeof value === "number" ? formatNumber(value, isFa) : value
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardDescription>{label}</CardDescription>
        <CardTitle className="text-2xl tabular-nums">
          {display}
          {suffix ? <span className="ms-1 text-sm font-normal text-muted-foreground">{suffix}</span> : null}
        </CardTitle>
      </CardHeader>
    </Card>
  )
}

const SEGMENT_PRESETS: Record<string, Partial<MarketingRuleRow>> = {
  churned: { after_days: 45, cooldown_days: 90, discount_value: 10, code_valid_days: 14 },
  never_purchased: { after_days: 3, cooldown_days: 30, discount_value: 15, max_discount_toman: 50000, code_valid_days: 7 },
  abandoned_checkout: { pending_hours: 24, cooldown_days: 14, discount_value: 10, code_valid_days: 3 },
  stale_buy_funnel: { funnel_idle_hours: 48, cooldown_days: 30, discount_value: 10, code_valid_days: 5 },
  expiring_renew: { expires_within_days: 7, cooldown_days: 30, discount_value: 15, code_valid_days: 10 },
}

const emptyRule = (segment = "churned"): MarketingRuleRow => {
  const preset = SEGMENT_PRESETS[segment] ?? {}
  return {
    segment_key: segment,
    enabled: true,
    priority: 100,
    cooldown_days: 90,
    after_days: 45,
    pending_hours: 24,
    funnel_idle_hours: 48,
    expires_within_days: 7,
    discount_type: "percent",
    discount_value: 10,
    code_valid_days: 14,
    max_uses_per_user: 1,
    message_body: "",
    channel_telegram: true,
    channel_bale: true,
    ...preset,
  }
}

function ruleThresholdLabel(rule: MarketingRuleRow, tp: (k: string, o?: Record<string, string | number>) => string, isFa: boolean): string {
  const sk = String(rule.segment_key ?? "")
  if (sk === "churned" || sk === "never_purchased") {
    return tp("thresholdAfterDays", { days: formatNumber(num(rule.after_days), isFa) })
  }
  if (sk === "abandoned_checkout") {
    return tp("thresholdPendingHours", { hours: formatNumber(num(rule.pending_hours), isFa) })
  }
  if (sk === "stale_buy_funnel") {
    return tp("thresholdFunnelHours", { hours: formatNumber(num(rule.funnel_idle_hours), isFa) })
  }
  if (sk === "expiring_renew") {
    return tp("thresholdExpiresDays", { days: formatNumber(num(rule.expires_within_days), isFa) })
  }
  return "—"
}

function channelBadges(rule: MarketingRuleRow, tp: (k: string) => string) {
  const out: string[] = []
  if (rule.channel_telegram) out.push(tp("channelTelegram"))
  if (rule.channel_bale) out.push(tp("channelBale"))
  return out.length ? out.join(" · ") : "—"
}

export function MarketingLifecycleAdminView({
  stats,
  funnel,
  rules,
  ruleStats,
  offers,
  pagination,
  dashboardBaseUrl,
  windowDays,
  offerStatusFilter = "",
  onWindowDaysChange,
  onOfferStatusChange,
  onPageChange,
  onPerPageChange,
  onMutateSuccess,
  onOpenUserDetail,
  onViewSegmentUsers,
  isReseller = false,
  readOnlySettings = false,
}: {
  stats: MarketingLifecycleStats | null
  funnel: MarketingFunnelDay[]
  rules: MarketingRuleRow[]
  ruleStats: MarketingRuleStatRow[]
  offers: MarketingOfferRow[]
  pagination: PaginationMeta | null
  dashboardBaseUrl: string
  windowDays: number
  offerStatusFilter?: string
  onWindowDaysChange: (days: number) => void
  onOfferStatusChange?: (status: string) => void
  onPageChange: (page: number) => void
  onPerPageChange: (n: number) => void
  onMutateSuccess?: () => void
  onOpenUserDetail?: (id: number) => void
  onViewSegmentUsers?: (segment: string) => void
  isReseller?: boolean
  readOnlySettings?: boolean
}) {
  const t = useTranslations("marketingLifecycleAdmin")
  const { isFa } = useDashLocale()
  const chartPrimary = useChartPrimaryColor()
  const tp = (k: string, opts?: Record<string, string | number>) =>
    t(`${k}`, opts)
  const canMutate = !readOnlySettings && !isReseller

  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
  const [ruleSheet, setRuleSheet] = useState<MarketingRuleRow | null>(null)
  const [manualOpen, setManualOpen] = useState(false)
  const [manualUserId, setManualUserId] = useState("")
  const [manualRuleId, setManualRuleId] = useState("")
  const [pickedSegment, setPickedSegment] = useState<string>("churned")
  const [playbookOpen, setPlaybookOpen] = useState(false)
  const [previewUserId, setPreviewUserId] = useState("")
  const [rulePreviewText, setRulePreviewText] = useState("")

  const summary = (stats?.summary ?? {}) as Record<string, unknown>
  const segmentCounts = (summary.segment_counts ?? {}) as Record<string, number>
  const lifecycleConfirmed = Boolean(summary.lifecycle_confirmed)

  const statsByRuleId = useMemo(() => {
    const m = new Map<number, MarketingRuleStatRow>()
    for (const s of ruleStats ?? []) {
      const id = num(s.rule_id)
      if (id > 0) m.set(id, s)
    }
    return m
  }, [ruleStats])

  const chartData = useMemo(
    () =>
      (funnel ?? []).map((d) => ({
        date: formatChartDayLabel(String(d.date ?? ""), isFa),
        registered: num(d.registered),
        first_pending: num(d.first_pending),
        first_paid: num(d.first_paid),
      })),
    [funnel, isFa]
  )

  async function runRule(ruleId: number) {
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("marketing_run_rule_now", { rule_id: ruleId, limit: 80 })
      if (!res.ok) {
        setErr(adminMutateErrorText(res, t("mutateError")))
        return
      }
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function confirmDefaults() {
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("marketing_lifecycle_confirm_defaults", {})
      if (!res.ok) {
        setErr(adminMutateErrorText(res, t("mutateError")))
        return
      }
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function previewMessage(ruleId: number, userId = 0) {
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("marketing_preview_message", {
        rule_id: ruleId,
        svp_user_id: userId > 0 ? userId : undefined,
      })
      if (!res.ok) {
        setErr(adminMutateErrorText(res, t("mutateError")))
        return
      }
      const body = res as { message?: string; data?: { message?: string } }
      setRulePreviewText(String(body.message ?? body.data?.message ?? ""))
    } finally {
      setBusy(false)
    }
  }

  async function saveRule() {
    if (!ruleSheet) return
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("marketing_rule_save", {
        rule_id: ruleSheet.id ?? 0,
        segment_key: ruleSheet.segment_key,
        enabled: ruleSheet.enabled ? 1 : 0,
        priority: ruleSheet.priority,
        cooldown_days: ruleSheet.cooldown_days,
        after_days: ruleSheet.after_days,
        pending_hours: ruleSheet.pending_hours,
        funnel_idle_hours: ruleSheet.funnel_idle_hours,
        expires_within_days: ruleSheet.expires_within_days,
        discount_type: ruleSheet.discount_type,
        discount_value: ruleSheet.discount_value,
        max_discount_toman: ruleSheet.max_discount_toman ?? "",
        code_valid_days: ruleSheet.code_valid_days,
        max_uses_per_user: ruleSheet.max_uses_per_user,
        message_body: ruleSheet.message_body ?? "",
        channel_telegram: ruleSheet.channel_telegram ? 1 : 0,
        channel_bale: ruleSheet.channel_bale ? 1 : 0,
        owner_svp_user_id: ruleSheet.owner_svp_user_id ?? 0,
      })
      if (!res.ok) {
        setErr(adminMutateErrorText(res, t("mutateError")))
        return
      }
      setRuleSheet(null)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function deleteRule(id: number) {
    if (!window.confirm(t("deleteConfirm"))) return
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("marketing_rule_delete", { rule_id: id })
      if (!res.ok) {
        setErr(adminMutateErrorText(res, t("mutateError")))
        return
      }
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function sendManual() {
    const uid = parseInt(manualUserId.trim(), 10)
    const rid = parseInt(manualRuleId.trim(), 10)
    if (!Number.isFinite(uid) || uid < 1) return
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("marketing_send_manual", {
        user_id: uid,
        rule_id: Number.isFinite(rid) && rid > 0 ? rid : 0,
      })
      if (!res.ok) {
        setErr(adminMutateErrorText(res, t("mutateError")))
        return
      }
      setManualOpen(false)
      setManualUserId("")
      setManualRuleId("")
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  function viewSegmentUsers() {
    onViewSegmentUsers?.(pickedSegment)
  }

  const segmentKey = String(ruleSheet?.segment_key ?? "churned")
  const showAfterDays = segmentKey === "churned" || segmentKey === "never_purchased"
  const showPendingHours = segmentKey === "abandoned_checkout"
  const showFunnelHours = segmentKey === "stale_buy_funnel"
  const showExpiresDays = segmentKey === "expiring_renew"

  const statusLabel = (st: string) => {
    const k = `status_${st}`
    const tr = t(k)
    return tr !== `marketingLifecycleAdmin.${k}` ? tr : st
  }

  return (
    <DashPage data-testid="dash-marketing-tab">
      <DashboardPageHeader title={t("title")} description={t("subtitle", { days: windowDays })} />

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <div className="space-y-2">
          <Label htmlFor="mkt-window">{t("windowDays")}</Label>
          <DashSelect
            id="mkt-window"
            triggerClassName="w-[160px]"
            value={String(windowDays)}
            onValueChange={(v) => onWindowDaysChange(Number(v))}
            options={[
              { value: "7", label: t("window7") },
              { value: "30", label: t("window30") },
              { value: "90", label: t("window90") },
            ]}
          />
        </div>
        {canMutate ? (
          <Button type="button" variant="outline" onClick={() => setManualOpen(true)} disabled={busy} className={dashIconGapClass()}>
            <Send className="h-4 w-4 shrink-0" />
            {t("manualSend")}
          </Button>
        ) : null}
        {!isReseller ? (
          <Button type="button" variant="ghost">
            <a href={buildDashboardTabUrl(dashboardBaseUrl, "discounts")}>{t("openDiscounts")}</a>
          </Button>
        ) : null}
      </div>

      {!canMutate ? (
        <p className="mb-4 text-sm text-muted-foreground">{t("readOnlyResellerHint")}</p>
      ) : null}

      {err ? <p className="mb-4 text-sm text-destructive">{err}</p> : null}

      {canMutate && !lifecycleConfirmed ? (
        <Card className="mb-6 border-amber-500/40 bg-amber-500/5">
          <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className={cn("flex gap-3", dashIconGapClass())}>
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
              <div>
                <p className="font-medium">{t("confirmBannerTitle")}</p>
                <p className="text-sm text-muted-foreground">{t("confirmBannerBody")}</p>
              </div>
            </div>
            <Button type="button" onClick={() => void confirmDefaults()} disabled={busy}>
              {t("confirmBannerAction")}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label={t("kpiRetention")} value={pctDisplay(summary.retention_rate, isFa)} />
        <StatCard label={t("kpiNewToPaid")} value={pctDisplay(summary.new_to_paid_rate, isFa)} />
        <StatCard label={t("kpiOfferSuccess")} value={pctDisplay(summary.offer_success_rate, isFa)} />
        <StatCard label={t("kpiCampaignRevenue")} value={num(summary.campaign_revenue_toman)} suffix={t("currency")} />
        <StatCard label={t("kpiSent")} value={num(summary.sent_count ?? summary.offers_sent)} />
        <StatCard label={t("kpiConverted")} value={num(summary.converted_count ?? summary.offers_converted)} />
        <StatCard label={t("kpiAbandonedRecovery")} value={pctDisplay(summary.abandoned_recovery_rate, isFa)} />
      </div>

      <Card className="mb-6">
        <CardHeader>
          <CardTitle className="text-base">{t("chartTitle")}</CardTitle>
          <CardDescription>{t("chartSubtitle")}</CardDescription>
        </CardHeader>
        <CardContent className="h-64">
          {chartData.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("chartEmpty")}</p>
          ) : (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-border/50" />
                <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} />
                <RechartsTooltip />
                <Area type="monotone" dataKey="registered" stackId="1" stroke={chartPrimary} fill={chartPrimary} fillOpacity={0.15} name={t("funnelRegistered")} />
                <Area type="monotone" dataKey="first_pending" stackId="2" stroke="#f59e0b" fill="#f59e0b" fillOpacity={0.12} name={t("funnelPending")} />
                <Area type="monotone" dataKey="first_paid" stackId="3" stroke="#22c55e" fill="#22c55e" fillOpacity={0.12} name={t("funnelPaid")} />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>

      <Card className="mb-6">
        <CardHeader>
          <CardTitle className="text-base">{t("reportsTitle")}</CardTitle>
          <CardDescription>{t("reportsSubtitle", { days: windowDays })}</CardDescription>
        </CardHeader>
        <CardContent>
          <DashTableShell minWidth="56rem" colWidths={STATS_TABLE_COLS}>
            <thead>
              <tr>
                <DashTh>{t("colRuleId")}</DashTh>
                <DashTh>{t("colSegment")}</DashTh>
                <DashTh>{t("colEligible")}</DashTh>
                <DashTh>{t("colSent")}</DashTh>
                <DashTh>{t("colConverted")}</DashTh>
                <DashTh>{t("colSuccessRate")}</DashTh>
                <DashTh>{t("colRevenue")}</DashTh>
                <DashTh>{t("colActions")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {(ruleStats ?? []).length === 0 ? (
                <tr>
                  <DashTd colSpan={8} className="text-muted-foreground">{t("reportsEmpty")}</DashTd>
                </tr>
              ) : (
                (ruleStats ?? []).map((s) => {
                  const rid = num(s.rule_id)
                  const rule = rules.find((r) => num(r.id) === rid)
                  return (
                    <tr key={rid}>
                      <DashTd className={dashLtrCell()}>#{rid}</DashTd>
                      <DashTd>{t(`segment_${String(s.segment_key ?? "")}`)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(s.eligible_now), isFa)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(s.sent), isFa)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(s.converted), isFa)}</DashTd>
                      <DashTd className="tabular-nums">{pctDisplay(s.success_rate, isFa)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(s.revenue_toman), isFa)} {t("currency")}</DashTd>
                      <DashTd>
                        {canMutate ? (
                          <div className="flex flex-wrap gap-1">
                            {rule ? (
                              <Button type="button" size="sm" variant="outline" onClick={() => setRuleSheet({ ...rule })} disabled={busy}>
                                {t("edit")}
                              </Button>
                            ) : null}
                            <Button type="button" size="sm" variant="outline" onClick={() => void runRule(rid)} disabled={busy || !rule?.enabled}>
                              <Play className="h-3 w-3 shrink-0" />
                              {t("runNow")}
                            </Button>
                          </div>
                        ) : (
                          <span className="text-muted-foreground">—</span>
                        )}
                      </DashTd>
                    </tr>
                  )
                })
              )}
            </tbody>
          </DashTableShell>
        </CardContent>
      </Card>

      <Card className="mb-6">
        <CardHeader>
          <CardTitle className="text-base">{t("segmentSectionTitle")}</CardTitle>
          <CardDescription>{t("segmentSectionSubtitle")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-2">
              <Label htmlFor="mkt-segment-pick">{t("segmentPickLabel")}</Label>
              <DashSelect
                id="mkt-segment-pick"
                triggerClassName="w-[220px]"
                value={pickedSegment}
                onValueChange={setPickedSegment}
                options={SEGMENTS.map((sk) => ({ value: sk, label: t(`segment_${sk}`) }))}
              />
            </div>
            <Button type="button" variant="default" onClick={viewSegmentUsers} className={dashIconGapClass()}>
              <Users className="h-4 w-4 shrink-0" />
              {t("viewSegmentUsersFullList")}
            </Button>
          </div>
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {SEGMENTS.map((sk) => (
              <Card key={sk} className={cn(sk === pickedSegment && "ring-1 ring-primary/40")}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{t(`segment_${sk}`)}</CardTitle>
                  <CardDescription className="tabular-nums">
                    {formatNumber(num(segmentCounts[sk]), isFa)} {t("segmentEligible")}
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <p className="text-xs text-muted-foreground">{t(`segmentHint_${sk}`)}</p>
                </CardContent>
              </Card>
            ))}
          </div>
        </CardContent>
      </Card>

      <Collapsible open={playbookOpen} onOpenChange={setPlaybookOpen} className="mb-6">
        <Card>
          <CardHeader className="pb-3">
            <CollapsibleTrigger
                    render={
                      <Button type="button" variant="ghost" className={cn("h-auto w-full justify-between p-0 hover:bg-transparent", dashIconGapClass())}>
                <span className={dashIconGapClass()}>
                  <BookOpen className="h-4 w-4 shrink-0" />
                  <span className="text-base font-semibold">{t("playbookTitle")}</span>
                </span>
                <ChevronDown className={cn("h-4 w-4 shrink-0 transition-transform", playbookOpen && "rotate-180")} />
              </Button>
                    }
                  />
            <CardDescription>{t("playbookSubtitle")}</CardDescription>
          </CardHeader>
          <CollapsibleContent>
            <CardContent className="space-y-4 pt-0">
              {SEGMENTS.map((sk) => (
                <div key={sk} className="rounded-lg border p-4">
                  <h4 className="mb-1 text-sm font-medium">{t(`segment_${sk}`)}</h4>
                  <p className="mb-2 text-sm text-muted-foreground">{t(`segmentPlaybook_${sk}`)}</p>
                  {canMutate ? (
                    <Button type="button" size="sm" variant="outline" onClick={() => setRuleSheet(emptyRule(sk))} disabled={busy}>
                      {t("createFromTemplate")}
                    </Button>
                  ) : null}
                </div>
              ))}
            </CardContent>
          </CollapsibleContent>
        </Card>
      </Collapsible>

      <Card className="mb-6">
        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
          <div>
            <CardTitle className="text-base">{t("rulesTitle")}</CardTitle>
            <CardDescription>{t("rulesSubtitle")}</CardDescription>
          </div>
          {canMutate ? (
            <Button type="button" size="sm" onClick={() => setRuleSheet(emptyRule())} disabled={busy}>
              {t("addRule")}
            </Button>
          ) : null}
        </CardHeader>
        <CardContent>
          <DashTableShell minWidth="72rem" colWidths={RULES_TABLE_COLS}>
            <thead>
              <tr>
                <DashTh>{t("colSegment")}</DashTh>
                <DashTh>{t("colThreshold")}</DashTh>
                <DashTh>{t("colDiscount")}</DashTh>
                <DashTh>{t("colPriority")}</DashTh>
                <DashTh>{t("colCooldown")}</DashTh>
                <DashTh>{t("colChannels")}</DashTh>
                <DashTh>{t("colEligible")}</DashTh>
                <DashTh>{t("colStats")}</DashTh>
                <DashTh>{t("colEnabled")}</DashTh>
                <DashTh>{t("colActions")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {rules.length === 0 ? (
                <tr>
                  <DashTd colSpan={10} className="text-muted-foreground">{t("rulesEmpty")}</DashTd>
                </tr>
              ) : (
                rules.map((r) => {
                  const id = num(r.id)
                  const disc =
                    r.discount_type === "fixed_toman"
                      ? `${formatNumber(num(r.discount_value), isFa)} ${t("currency")}`
                      : `${num(r.discount_value)}%`
                  const rs = statsByRuleId.get(id)
                  return (
                    <tr key={id}>
                      <DashTd>{t(`segment_${String(r.segment_key ?? "")}`)}</DashTd>
                      <DashTd>{ruleThresholdLabel(r, tp, isFa)}</DashTd>
                      <DashTd>{disc}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(r.priority), isFa)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(r.cooldown_days), isFa)}</DashTd>
                      <DashTd className="text-xs">{channelBadges(r, tp)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(rs?.eligible_now), isFa)}</DashTd>
                      <DashTd className="text-xs tabular-nums">
                        {rs ? `${formatNumber(num(rs.sent), isFa)} / ${formatNumber(num(rs.converted), isFa)} (${pctDisplay(rs.success_rate, isFa)})` : "—"}
                      </DashTd>
                      <DashTd>
                        <Badge variant={r.enabled ? "default" : "secondary"}>
                          {r.enabled ? t("enabledYes") : t("enabledNo")}
                        </Badge>
                      </DashTd>
                      <DashTd>
                        {canMutate ? (
                          <div className="flex flex-wrap gap-1">
                            <Button type="button" size="sm" variant="outline" onClick={() => setRuleSheet({ ...r })} disabled={busy}>
                              {t("edit")}
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => void runRule(id)} disabled={busy || !r.enabled} className={dashIconGapClass()}>
                              <Play className="h-3 w-3 shrink-0" />
                              {t("runNow")}
                            </Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => void deleteRule(id)} disabled={busy}>
                              {t("delete")}
                            </Button>
                          </div>
                        ) : (
                          <span className="text-muted-foreground">—</span>
                        )}
                      </DashTd>
                    </tr>
                  )
                })
              )}
            </tbody>
          </DashTableShell>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row flex-wrap items-end justify-between gap-3">
          <div>
            <CardTitle className="text-base">{t("offersTitle")}</CardTitle>
            <CardDescription>{t("offersSubtitle")}</CardDescription>
          </div>
          <div className="space-y-2">
            <Label htmlFor="mkt-offer-status">{t("offerStatusFilter")}</Label>
            <DashSelect
              id="mkt-offer-status"
              triggerClassName="w-[180px]"
              value={offerStatusFilter || "all"}
              onValueChange={(v) => onOfferStatusChange?.(v === "all" ? "" : v)}
              options={[
                { value: "all", label: t("statusAll") },
                ...OFFER_STATUSES.filter((s) => s).map((st) => ({
                  value: st,
                  label: statusLabel(st),
                })),
              ]}
            />
          </div>
        </CardHeader>
        <CardContent>
          <DashTableShell minWidth="64rem" colWidths={OFFERS_TABLE_COLS}>
            <thead>
              <tr>
                <DashTh>{t("colOfferId")}</DashTh>
                <DashTh>{t("colUser")}</DashTh>
                <DashTh>{t("colRuleId")}</DashTh>
                <DashTh>{t("colSegment")}</DashTh>
                <DashTh>{t("colCode")}</DashTh>
                <DashTh>{t("colStatus")}</DashTh>
                <DashTh>{t("colRevenue")}</DashTh>
                <DashTh>{t("colCreated")}</DashTh>
                <DashTh>{t("colSent")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {offers.length === 0 ? (
                <tr>
                  <DashTd colSpan={9} className="text-muted-foreground">{t("offersEmpty")}</DashTd>
                </tr>
              ) : (
                offers.map((o) => {
                  const uid = num(o.svp_user_id)
                  return (
                    <tr key={num(o.id)}>
                      <DashTd className={dashLtrCell()}>#{num(o.id)}</DashTd>
                      <DashTd>
                        {onOpenUserDetail && uid > 0 ? (
                          <button type="button" className="text-primary underline-offset-2 hover:underline" onClick={() => onOpenUserDetail(uid)}>
                            {o.user_label || `#${uid}`}
                          </button>
                        ) : (
                          o.user_label || `#${uid}`
                        )}
                      </DashTd>
                      <DashTd className={dashLtrCell()}>#{num(o.rule_id)}</DashTd>
                      <DashTd>{t(`segment_${String(o.segment_key ?? "")}`)}</DashTd>
                      <DashTd className={dashLtrCell("font-mono text-xs")}>{o.discount_code || "—"}</DashTd>
                      <DashTd>
                        <Badge variant={o.status === "converted" ? "default" : "secondary"}>{statusLabel(String(o.status ?? ""))}</Badge>
                      </DashTd>
                      <DashTd className="tabular-nums">
                        {num(o.revenue_toman) > 0 ? `${formatNumber(num(o.revenue_toman), isFa)} ${t("currency")}` : "—"}
                      </DashTd>
                      <DashTd className={dashLtrCell("text-xs")}>{o.created_at || "—"}</DashTd>
                      <DashTd className={dashLtrCell("text-xs")}>{o.sent_at || "—"}</DashTd>
                    </tr>
                  )
                })
              )}
            </tbody>
          </DashTableShell>
          <DataPagination className="mt-4" meta={pagination} onPageChange={onPageChange} onPerPageChange={onPerPageChange} />
        </CardContent>
      </Card>

      <Sheet open={ruleSheet != null} onOpenChange={(o) => !o && setRuleSheet(null)}>
        <DashSheetContent className="flex w-full flex-col gap-0 overflow-y-auto sm:max-w-lg">
          <SheetHeader className="border-b p-4 text-start">
            <SheetTitle>{ruleSheet?.id ? t("editRule") : t("addRule")}</SheetTitle>
          </SheetHeader>
          {ruleSheet ? (
            <div className="flex-1 space-y-4 p-4">
              <p className="rounded-md border bg-muted/40 p-3 text-xs text-muted-foreground">{t(`segmentPlaybook_${segmentKey}`)}</p>
              <div className="space-y-2">
                <Label htmlFor="rule-segment">{t("colSegment")}</Label>
                <DashSelect
                  id="rule-segment"
                  value={segmentKey}
                  onValueChange={(v) =>
                    setRuleSheet((r) => (r ? { ...r, segment_key: v, ...SEGMENT_PRESETS[v] } : r))
                  }
                  options={SEGMENTS.map((sk) => ({ value: sk, label: t(`segment_${sk}`) }))}
                />
              </div>
              <div className="flex items-center gap-2">
                <Switch id="rule-enabled" checked={!!ruleSheet.enabled} onCheckedChange={(c) => setRuleSheet((r) => (r ? { ...r, enabled: c } : r))} />
                <Label htmlFor="rule-enabled">{t("colEnabled")}</Label>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="rule-priority">{t("fieldPriority")}</Label>
                  <Input id="rule-priority" type="number" value={String(ruleSheet.priority ?? 100)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, priority: parseInt(e.target.value, 10) || 100 } : r))} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="rule-cooldown">{t("fieldCooldown")}</Label>
                  <Input id="rule-cooldown" type="number" value={String(ruleSheet.cooldown_days ?? 90)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, cooldown_days: parseInt(e.target.value, 10) || 90 } : r))} />
                </div>
                {showAfterDays ? (
                  <div className="space-y-2">
                    <Label htmlFor="rule-after">{t("fieldAfterDays")}</Label>
                    <Input id="rule-after" type="number" value={String(ruleSheet.after_days ?? 0)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, after_days: parseInt(e.target.value, 10) || 0 } : r))} />
                  </div>
                ) : null}
                {showPendingHours ? (
                  <div className="space-y-2">
                    <Label htmlFor="rule-pending">{t("fieldPendingHours")}</Label>
                    <Input id="rule-pending" type="number" value={String(ruleSheet.pending_hours ?? 24)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, pending_hours: parseInt(e.target.value, 10) || 24 } : r))} />
                  </div>
                ) : null}
                {showFunnelHours ? (
                  <div className="space-y-2">
                    <Label htmlFor="rule-funnel">{t("fieldFunnelHours")}</Label>
                    <Input id="rule-funnel" type="number" value={String(ruleSheet.funnel_idle_hours ?? 48)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, funnel_idle_hours: parseInt(e.target.value, 10) || 48 } : r))} />
                  </div>
                ) : null}
                {showExpiresDays ? (
                  <div className="space-y-2">
                    <Label htmlFor="rule-expires">{t("fieldExpiresDays")}</Label>
                    <Input id="rule-expires" type="number" value={String(ruleSheet.expires_within_days ?? 7)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, expires_within_days: parseInt(e.target.value, 10) || 7 } : r))} />
                  </div>
                ) : null}
                <div className="space-y-2">
                  <Label htmlFor="rule-code-days">{t("fieldCodeDays")}</Label>
                  <Input id="rule-code-days" type="number" value={String(ruleSheet.code_valid_days ?? 7)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, code_valid_days: parseInt(e.target.value, 10) || 7 } : r))} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="rule-max-uses">{t("fieldMaxUses")}</Label>
                  <Input id="rule-max-uses" type="number" value={String(ruleSheet.max_uses_per_user ?? 1)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, max_uses_per_user: parseInt(e.target.value, 10) || 1 } : r))} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="rule-disc-type">{t("fieldDiscountType")}</Label>
                  <DashSelect
                    id="rule-disc-type"
                    value={String(ruleSheet.discount_type ?? "percent")}
                    onValueChange={(v) => setRuleSheet((r) => (r ? { ...r, discount_type: v } : r))}
                    options={[
                      { value: "percent", label: t("discountPercent") },
                      { value: "fixed_toman", label: t("discountFixed") },
                    ]}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="rule-disc-val">{t("fieldDiscountValue")}</Label>
                  <Input id="rule-disc-val" type="number" value={String(ruleSheet.discount_value ?? 0)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, discount_value: parseFloat(e.target.value) || 0 } : r))} />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="rule-max-disc">{t("fieldMaxDiscount")}</Label>
                <Input id="rule-max-disc" type="number" placeholder={t("placeholderUnlimited")} value={ruleSheet.max_discount_toman != null ? String(ruleSheet.max_discount_toman) : ""} onChange={(e) => setRuleSheet((r) => (r ? { ...r, max_discount_toman: e.target.value === "" ? null : parseFloat(e.target.value) || 0 } : r))} />
              </div>
              <div className="flex flex-wrap gap-4">
                <div className="flex items-center gap-2">
                  <Switch id="rule-ch-tg" checked={!!ruleSheet.channel_telegram} onCheckedChange={(c) => setRuleSheet((r) => (r ? { ...r, channel_telegram: c } : r))} />
                  <Label htmlFor="rule-ch-tg">{t("channelTelegram")}</Label>
                </div>
                <div className="flex items-center gap-2">
                  <Switch id="rule-ch-bale" checked={!!ruleSheet.channel_bale} onCheckedChange={(c) => setRuleSheet((r) => (r ? { ...r, channel_bale: c } : r))} />
                  <Label htmlFor="rule-ch-bale">{t("channelBale")}</Label>
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="rule-message">{t("fieldMessage")}</Label>
                <Textarea id="rule-message" rows={4} value={String(ruleSheet.message_body ?? "")} onChange={(e) => setRuleSheet((r) => (r ? { ...r, message_body: e.target.value } : r))} placeholder={t("messagePlaceholder")} />
              </div>
              {ruleSheet.id ? (
                <div className="space-y-2 rounded-md border p-3">
                  <Label htmlFor="rule-preview-uid">{t("previewUserId")}</Label>
                  <Input
                    id="rule-preview-uid"
                    className={dashLtrCell()}
                    value={previewUserId}
                    onChange={(e) => setPreviewUserId(e.target.value)}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    disabled={busy}
                    onClick={() => {
                      const uid = Number(previewUserId)
                      void previewMessage(num(ruleSheet.id), Number.isFinite(uid) && uid > 0 ? uid : 0)
                    }}
                  >
                    {t("previewMessage")}
                  </Button>
                  <Textarea
                    readOnly
                    rows={4}
                    value={rulePreviewText}
                    placeholder={t("previewPlaceholder")}
                    className="bg-muted/40"
                  />
                </div>
              ) : null}
              {canMutate ? (
              <Button type="button" className="w-full" onClick={() => void saveRule()} disabled={busy}>
                {t("saveRule")}
              </Button>
              ) : null}
            </div>
          ) : null}
        </DashSheetContent>
      </Sheet>

      <Dialog open={manualOpen} onOpenChange={setManualOpen}>
        <DashDialogContent>
          <DashDialogHeader>
            <DialogTitle>{t("manualDialogTitle")}</DialogTitle>
          </DashDialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-2">
              <Label htmlFor="manual-uid">{t("manualUserId")}</Label>
              <Input id="manual-uid" className={dashLtrCell()} value={manualUserId} onChange={(e) => setManualUserId(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="manual-rid">{t("manualRuleId")}</Label>
              <Input id="manual-rid" className={dashLtrCell()} value={manualRuleId} onChange={(e) => setManualRuleId(e.target.value)} placeholder={t("manualRuleOptional")} />
            </div>
          </div>
          <DashDialogFooter>
            <Button type="button" variant="outline" onClick={() => setManualOpen(false)}>{t("cancel")}</Button>
            <Button type="button" onClick={() => void sendManual()} disabled={busy}>{t("manualSend")}</Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </DashPage>
  )
}

export function MarketingLifecycleAdminClient() {
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, patchQuery, listQuery, isReseller } =
    useAdminTabState("marketing_lifecycle")
  const t = useTranslations("marketingLifecycleAdmin")
  const shell = useDashboardShellOptional()
  if (loading) return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <MarketingLifecycleAdminView
      stats={(data.marketingLifecycleStats as import("@/components/admin/marketing-lifecycle-admin-client").MarketingLifecycleStats | null) ?? null}
      funnel={Array.isArray(data.marketingFunnel) ? data.marketingFunnel as import("@/components/admin/marketing-lifecycle-admin-client").MarketingFunnelDay[] : []}
      rules={Array.isArray(data.marketingRules) ? data.marketingRules as import("@/components/admin/marketing-lifecycle-admin-client").MarketingRuleRow[] : []}
      ruleStats={Array.isArray(data.marketingRuleStats) ? data.marketingRuleStats as import("@/components/admin/marketing-lifecycle-admin-client").MarketingRuleStatRow[] : []}
      offers={rows(data.marketingOffers ?? data.marketingOffersList)}
      pagination={pickPagination("marketingOffers")}
      dashboardBaseUrl=""
      windowDays={Number(listQuery.marketing_window_days ?? data.marketingWindowDays ?? 30)}
      offerStatusFilter={listQuery.marketing_offer_status ?? ""}
      onWindowDaysChange={(n) => patchQuery({ marketing_window_days: String(n) })}
      onOfferStatusChange={(s) => patchQuery({ marketing_offer_status: s })}
      onPageChange={(p) => setPage("marketingOffers", p)}
      onPerPageChange={(n) => setPer("marketingOffers", n)}
      onMutateSuccess={reload}
      onViewSegmentUsers={shell?.openUsersSegment}
      isReseller={isReseller}
      readOnlySettings={isReseller}
    />
  )
}
