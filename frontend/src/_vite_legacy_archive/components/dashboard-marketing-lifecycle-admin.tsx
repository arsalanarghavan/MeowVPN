"use client"

import { useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import { BookOpen, ChevronDown, Play, Send, Users } from "lucide-react"
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

export function DashboardMarketingLifecycleAdmin({
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
  const { t } = useTranslation()
  const { isFa } = useDashLocale()
  const chartPrimary = useChartPrimaryColor()
  const tp = (k: string, opts?: Record<string, string | number>) =>
    t(`marketingLifecycleAdmin.${k}`, opts)
  const canMutate = !readOnlySettings && !isReseller

  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
  const [ruleSheet, setRuleSheet] = useState<MarketingRuleRow | null>(null)
  const [manualOpen, setManualOpen] = useState(false)
  const [manualUserId, setManualUserId] = useState("")
  const [manualRuleId, setManualRuleId] = useState("")
  const [pickedSegment, setPickedSegment] = useState<string>("churned")
  const [playbookOpen, setPlaybookOpen] = useState(false)

  const summary = (stats?.summary ?? {}) as Record<string, unknown>
  const segmentCounts = (summary.segment_counts ?? {}) as Record<string, number>

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
        setErr(adminMutateErrorText(res, tp("mutateError")))
        return
      }
      onMutateSuccess?.()
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
        setErr(adminMutateErrorText(res, tp("mutateError")))
        return
      }
      setRuleSheet(null)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function deleteRule(id: number) {
    if (!window.confirm(tp("deleteConfirm"))) return
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("marketing_rule_delete", { rule_id: id })
      if (!res.ok) {
        setErr(adminMutateErrorText(res, tp("mutateError")))
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
        setErr(adminMutateErrorText(res, tp("mutateError")))
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
    const tr = tp(k)
    return tr !== `marketingLifecycleAdmin.${k}` ? tr : st
  }

  return (
    <DashPage data-testid="dash-marketing-tab">
      <DashboardPageHeader title={tp("title")} description={tp("subtitle", { days: windowDays })} />

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <div className="space-y-2">
          <Label htmlFor="mkt-window">{tp("windowDays")}</Label>
          <DashSelect
            id="mkt-window"
            triggerClassName="w-[160px]"
            value={String(windowDays)}
            onValueChange={(v) => onWindowDaysChange(Number(v))}
            options={[
              { value: "7", label: tp("window7") },
              { value: "30", label: tp("window30") },
              { value: "90", label: tp("window90") },
            ]}
          />
        </div>
        {canMutate ? (
          <Button type="button" variant="outline" onClick={() => setManualOpen(true)} disabled={busy} className={dashIconGapClass()}>
            <Send className="h-4 w-4 shrink-0" />
            {tp("manualSend")}
          </Button>
        ) : null}
        {!isReseller ? (
          <Button type="button" variant="ghost" asChild>
            <a href={buildDashboardTabUrl(dashboardBaseUrl, "discounts")}>{tp("openDiscounts")}</a>
          </Button>
        ) : null}
      </div>

      {!canMutate ? (
        <p className="mb-4 text-sm text-muted-foreground">{tp("readOnlyResellerHint")}</p>
      ) : null}

      {err ? <p className="mb-4 text-sm text-destructive">{err}</p> : null}

      <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label={tp("kpiRetention")} value={pctDisplay(summary.retention_rate, isFa)} />
        <StatCard label={tp("kpiNewToPaid")} value={pctDisplay(summary.new_to_paid_rate, isFa)} />
        <StatCard label={tp("kpiOfferSuccess")} value={pctDisplay(summary.offer_success_rate, isFa)} />
        <StatCard label={tp("kpiCampaignRevenue")} value={num(summary.campaign_revenue_toman)} suffix={tp("currency")} />
        <StatCard label={tp("kpiSent")} value={num(summary.sent_count ?? summary.offers_sent)} />
        <StatCard label={tp("kpiConverted")} value={num(summary.converted_count ?? summary.offers_converted)} />
        <StatCard label={tp("kpiAbandonedRecovery")} value={pctDisplay(summary.abandoned_recovery_rate, isFa)} />
      </div>

      <Card className="mb-6">
        <CardHeader>
          <CardTitle className="text-base">{tp("chartTitle")}</CardTitle>
          <CardDescription>{tp("chartSubtitle")}</CardDescription>
        </CardHeader>
        <CardContent className="h-64">
          {chartData.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("chartEmpty")}</p>
          ) : (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-border/50" />
                <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} />
                <RechartsTooltip />
                <Area type="monotone" dataKey="registered" stackId="1" stroke={chartPrimary} fill={chartPrimary} fillOpacity={0.15} name={tp("funnelRegistered")} />
                <Area type="monotone" dataKey="first_pending" stackId="2" stroke="#f59e0b" fill="#f59e0b" fillOpacity={0.12} name={tp("funnelPending")} />
                <Area type="monotone" dataKey="first_paid" stackId="3" stroke="#22c55e" fill="#22c55e" fillOpacity={0.12} name={tp("funnelPaid")} />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>

      <Card className="mb-6">
        <CardHeader>
          <CardTitle className="text-base">{tp("reportsTitle")}</CardTitle>
          <CardDescription>{tp("reportsSubtitle", { days: windowDays })}</CardDescription>
        </CardHeader>
        <CardContent>
          <DashTableShell minWidth="56rem" colWidths={STATS_TABLE_COLS}>
            <thead>
              <tr>
                <DashTh>{tp("colRuleId")}</DashTh>
                <DashTh>{tp("colSegment")}</DashTh>
                <DashTh>{tp("colEligible")}</DashTh>
                <DashTh>{tp("colSent")}</DashTh>
                <DashTh>{tp("colConverted")}</DashTh>
                <DashTh>{tp("colSuccessRate")}</DashTh>
                <DashTh>{tp("colRevenue")}</DashTh>
                <DashTh>{tp("colActions")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {(ruleStats ?? []).length === 0 ? (
                <tr>
                  <DashTd colSpan={8} className="text-muted-foreground">{tp("reportsEmpty")}</DashTd>
                </tr>
              ) : (
                (ruleStats ?? []).map((s) => {
                  const rid = num(s.rule_id)
                  const rule = rules.find((r) => num(r.id) === rid)
                  return (
                    <tr key={rid}>
                      <DashTd className={dashLtrCell()}>#{rid}</DashTd>
                      <DashTd>{tp(`segment_${String(s.segment_key ?? "")}`)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(s.eligible_now), isFa)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(s.sent), isFa)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(s.converted), isFa)}</DashTd>
                      <DashTd className="tabular-nums">{pctDisplay(s.success_rate, isFa)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(s.revenue_toman), isFa)} {tp("currency")}</DashTd>
                      <DashTd>
                        {canMutate ? (
                          <div className="flex flex-wrap gap-1">
                            {rule ? (
                              <Button type="button" size="sm" variant="outline" onClick={() => setRuleSheet({ ...rule })} disabled={busy}>
                                {tp("edit")}
                              </Button>
                            ) : null}
                            <Button type="button" size="sm" variant="outline" onClick={() => void runRule(rid)} disabled={busy || !rule?.enabled}>
                              <Play className="h-3 w-3 shrink-0" />
                              {tp("runNow")}
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
          <CardTitle className="text-base">{tp("segmentSectionTitle")}</CardTitle>
          <CardDescription>{tp("segmentSectionSubtitle")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-2">
              <Label htmlFor="mkt-segment-pick">{tp("segmentPickLabel")}</Label>
              <DashSelect
                id="mkt-segment-pick"
                triggerClassName="w-[220px]"
                value={pickedSegment}
                onValueChange={setPickedSegment}
                options={SEGMENTS.map((sk) => ({ value: sk, label: tp(`segment_${sk}`) }))}
              />
            </div>
            <Button type="button" variant="default" onClick={viewSegmentUsers} className={dashIconGapClass()}>
              <Users className="h-4 w-4 shrink-0" />
              {tp("viewSegmentUsersFullList")}
            </Button>
          </div>
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {SEGMENTS.map((sk) => (
              <Card key={sk} className={cn(sk === pickedSegment && "ring-1 ring-primary/40")}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">{tp(`segment_${sk}`)}</CardTitle>
                  <CardDescription className="tabular-nums">
                    {formatNumber(num(segmentCounts[sk]), isFa)} {tp("segmentEligible")}
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <p className="text-xs text-muted-foreground">{tp(`segmentHint_${sk}`)}</p>
                </CardContent>
              </Card>
            ))}
          </div>
        </CardContent>
      </Card>

      <Collapsible open={playbookOpen} onOpenChange={setPlaybookOpen} className="mb-6">
        <Card>
          <CardHeader className="pb-3">
            <CollapsibleTrigger asChild>
              <Button type="button" variant="ghost" className={cn("h-auto w-full justify-between p-0 hover:bg-transparent", dashIconGapClass())}>
                <span className={dashIconGapClass()}>
                  <BookOpen className="h-4 w-4 shrink-0" />
                  <span className="text-base font-semibold">{tp("playbookTitle")}</span>
                </span>
                <ChevronDown className={cn("h-4 w-4 shrink-0 transition-transform", playbookOpen && "rotate-180")} />
              </Button>
            </CollapsibleTrigger>
            <CardDescription>{tp("playbookSubtitle")}</CardDescription>
          </CardHeader>
          <CollapsibleContent>
            <CardContent className="space-y-4 pt-0">
              {SEGMENTS.map((sk) => (
                <div key={sk} className="rounded-lg border p-4">
                  <h4 className="mb-1 text-sm font-medium">{tp(`segment_${sk}`)}</h4>
                  <p className="mb-2 text-sm text-muted-foreground">{tp(`segmentPlaybook_${sk}`)}</p>
                  {canMutate ? (
                    <Button type="button" size="sm" variant="outline" onClick={() => setRuleSheet(emptyRule(sk))} disabled={busy}>
                      {tp("createFromTemplate")}
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
            <CardTitle className="text-base">{tp("rulesTitle")}</CardTitle>
            <CardDescription>{tp("rulesSubtitle")}</CardDescription>
          </div>
          {canMutate ? (
            <Button type="button" size="sm" onClick={() => setRuleSheet(emptyRule())} disabled={busy}>
              {tp("addRule")}
            </Button>
          ) : null}
        </CardHeader>
        <CardContent>
          <DashTableShell minWidth="72rem" colWidths={RULES_TABLE_COLS}>
            <thead>
              <tr>
                <DashTh>{tp("colSegment")}</DashTh>
                <DashTh>{tp("colThreshold")}</DashTh>
                <DashTh>{tp("colDiscount")}</DashTh>
                <DashTh>{tp("colPriority")}</DashTh>
                <DashTh>{tp("colCooldown")}</DashTh>
                <DashTh>{tp("colChannels")}</DashTh>
                <DashTh>{tp("colEligible")}</DashTh>
                <DashTh>{tp("colStats")}</DashTh>
                <DashTh>{tp("colEnabled")}</DashTh>
                <DashTh>{tp("colActions")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {rules.length === 0 ? (
                <tr>
                  <DashTd colSpan={10} className="text-muted-foreground">{tp("rulesEmpty")}</DashTd>
                </tr>
              ) : (
                rules.map((r) => {
                  const id = num(r.id)
                  const disc =
                    r.discount_type === "fixed_toman"
                      ? `${formatNumber(num(r.discount_value), isFa)} ${tp("currency")}`
                      : `${num(r.discount_value)}%`
                  const rs = statsByRuleId.get(id)
                  return (
                    <tr key={id}>
                      <DashTd>{tp(`segment_${String(r.segment_key ?? "")}`)}</DashTd>
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
                          {r.enabled ? tp("enabledYes") : tp("enabledNo")}
                        </Badge>
                      </DashTd>
                      <DashTd>
                        {canMutate ? (
                          <div className="flex flex-wrap gap-1">
                            <Button type="button" size="sm" variant="outline" onClick={() => setRuleSheet({ ...r })} disabled={busy}>
                              {tp("edit")}
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => void runRule(id)} disabled={busy || !r.enabled} className={dashIconGapClass()}>
                              <Play className="h-3 w-3 shrink-0" />
                              {tp("runNow")}
                            </Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => void deleteRule(id)} disabled={busy}>
                              {tp("delete")}
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
            <CardTitle className="text-base">{tp("offersTitle")}</CardTitle>
            <CardDescription>{tp("offersSubtitle")}</CardDescription>
          </div>
          <div className="space-y-2">
            <Label htmlFor="mkt-offer-status">{tp("offerStatusFilter")}</Label>
            <DashSelect
              id="mkt-offer-status"
              triggerClassName="w-[180px]"
              value={offerStatusFilter || "all"}
              onValueChange={(v) => onOfferStatusChange?.(v === "all" ? "" : v)}
              options={[
                { value: "all", label: tp("statusAll") },
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
                <DashTh>{tp("colOfferId")}</DashTh>
                <DashTh>{tp("colUser")}</DashTh>
                <DashTh>{tp("colRuleId")}</DashTh>
                <DashTh>{tp("colSegment")}</DashTh>
                <DashTh>{tp("colCode")}</DashTh>
                <DashTh>{tp("colStatus")}</DashTh>
                <DashTh>{tp("colRevenue")}</DashTh>
                <DashTh>{tp("colCreated")}</DashTh>
                <DashTh>{tp("colSent")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {offers.length === 0 ? (
                <tr>
                  <DashTd colSpan={9} className="text-muted-foreground">{tp("offersEmpty")}</DashTd>
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
                      <DashTd>{tp(`segment_${String(o.segment_key ?? "")}`)}</DashTd>
                      <DashTd className={dashLtrCell("font-mono text-xs")}>{o.discount_code || "—"}</DashTd>
                      <DashTd>
                        <Badge variant={o.status === "converted" ? "default" : "secondary"}>{statusLabel(String(o.status ?? ""))}</Badge>
                      </DashTd>
                      <DashTd className="tabular-nums">
                        {num(o.revenue_toman) > 0 ? `${formatNumber(num(o.revenue_toman), isFa)} ${tp("currency")}` : "—"}
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
            <SheetTitle>{ruleSheet?.id ? tp("editRule") : tp("addRule")}</SheetTitle>
          </SheetHeader>
          {ruleSheet ? (
            <div className="flex-1 space-y-4 p-4">
              <p className="rounded-md border bg-muted/40 p-3 text-xs text-muted-foreground">{tp(`segmentPlaybook_${segmentKey}`)}</p>
              <div className="space-y-2">
                <Label htmlFor="rule-segment">{tp("colSegment")}</Label>
                <DashSelect
                  id="rule-segment"
                  value={segmentKey}
                  onValueChange={(v) =>
                    setRuleSheet((r) => (r ? { ...r, segment_key: v, ...SEGMENT_PRESETS[v] } : r))
                  }
                  options={SEGMENTS.map((sk) => ({ value: sk, label: tp(`segment_${sk}`) }))}
                />
              </div>
              <div className="flex items-center gap-2">
                <Switch id="rule-enabled" checked={!!ruleSheet.enabled} onCheckedChange={(c) => setRuleSheet((r) => (r ? { ...r, enabled: c } : r))} />
                <Label htmlFor="rule-enabled">{tp("colEnabled")}</Label>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="rule-priority">{tp("fieldPriority")}</Label>
                  <Input id="rule-priority" type="number" value={String(ruleSheet.priority ?? 100)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, priority: parseInt(e.target.value, 10) || 100 } : r))} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="rule-cooldown">{tp("fieldCooldown")}</Label>
                  <Input id="rule-cooldown" type="number" value={String(ruleSheet.cooldown_days ?? 90)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, cooldown_days: parseInt(e.target.value, 10) || 90 } : r))} />
                </div>
                {showAfterDays ? (
                  <div className="space-y-2">
                    <Label htmlFor="rule-after">{tp("fieldAfterDays")}</Label>
                    <Input id="rule-after" type="number" value={String(ruleSheet.after_days ?? 0)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, after_days: parseInt(e.target.value, 10) || 0 } : r))} />
                  </div>
                ) : null}
                {showPendingHours ? (
                  <div className="space-y-2">
                    <Label htmlFor="rule-pending">{tp("fieldPendingHours")}</Label>
                    <Input id="rule-pending" type="number" value={String(ruleSheet.pending_hours ?? 24)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, pending_hours: parseInt(e.target.value, 10) || 24 } : r))} />
                  </div>
                ) : null}
                {showFunnelHours ? (
                  <div className="space-y-2">
                    <Label htmlFor="rule-funnel">{tp("fieldFunnelHours")}</Label>
                    <Input id="rule-funnel" type="number" value={String(ruleSheet.funnel_idle_hours ?? 48)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, funnel_idle_hours: parseInt(e.target.value, 10) || 48 } : r))} />
                  </div>
                ) : null}
                {showExpiresDays ? (
                  <div className="space-y-2">
                    <Label htmlFor="rule-expires">{tp("fieldExpiresDays")}</Label>
                    <Input id="rule-expires" type="number" value={String(ruleSheet.expires_within_days ?? 7)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, expires_within_days: parseInt(e.target.value, 10) || 7 } : r))} />
                  </div>
                ) : null}
                <div className="space-y-2">
                  <Label htmlFor="rule-code-days">{tp("fieldCodeDays")}</Label>
                  <Input id="rule-code-days" type="number" value={String(ruleSheet.code_valid_days ?? 7)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, code_valid_days: parseInt(e.target.value, 10) || 7 } : r))} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="rule-max-uses">{tp("fieldMaxUses")}</Label>
                  <Input id="rule-max-uses" type="number" value={String(ruleSheet.max_uses_per_user ?? 1)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, max_uses_per_user: parseInt(e.target.value, 10) || 1 } : r))} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="rule-disc-type">{tp("fieldDiscountType")}</Label>
                  <DashSelect
                    id="rule-disc-type"
                    value={String(ruleSheet.discount_type ?? "percent")}
                    onValueChange={(v) => setRuleSheet((r) => (r ? { ...r, discount_type: v } : r))}
                    options={[
                      { value: "percent", label: tp("discountPercent") },
                      { value: "fixed_toman", label: tp("discountFixed") },
                    ]}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="rule-disc-val">{tp("fieldDiscountValue")}</Label>
                  <Input id="rule-disc-val" type="number" value={String(ruleSheet.discount_value ?? 0)} onChange={(e) => setRuleSheet((r) => (r ? { ...r, discount_value: parseFloat(e.target.value) || 0 } : r))} />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="rule-max-disc">{tp("fieldMaxDiscount")}</Label>
                <Input id="rule-max-disc" type="number" placeholder={tp("placeholderUnlimited")} value={ruleSheet.max_discount_toman != null ? String(ruleSheet.max_discount_toman) : ""} onChange={(e) => setRuleSheet((r) => (r ? { ...r, max_discount_toman: e.target.value === "" ? null : parseFloat(e.target.value) || 0 } : r))} />
              </div>
              <div className="flex flex-wrap gap-4">
                <div className="flex items-center gap-2">
                  <Switch id="rule-ch-tg" checked={!!ruleSheet.channel_telegram} onCheckedChange={(c) => setRuleSheet((r) => (r ? { ...r, channel_telegram: c } : r))} />
                  <Label htmlFor="rule-ch-tg">{tp("channelTelegram")}</Label>
                </div>
                <div className="flex items-center gap-2">
                  <Switch id="rule-ch-bale" checked={!!ruleSheet.channel_bale} onCheckedChange={(c) => setRuleSheet((r) => (r ? { ...r, channel_bale: c } : r))} />
                  <Label htmlFor="rule-ch-bale">{tp("channelBale")}</Label>
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="rule-message">{tp("fieldMessage")}</Label>
                <Textarea id="rule-message" rows={4} value={String(ruleSheet.message_body ?? "")} onChange={(e) => setRuleSheet((r) => (r ? { ...r, message_body: e.target.value } : r))} placeholder={tp("messagePlaceholder")} />
              </div>
              {canMutate ? (
              <Button type="button" className="w-full" onClick={() => void saveRule()} disabled={busy}>
                {tp("saveRule")}
              </Button>
              ) : null}
            </div>
          ) : null}
        </DashSheetContent>
      </Sheet>

      <Dialog open={manualOpen} onOpenChange={setManualOpen}>
        <DashDialogContent>
          <DashDialogHeader>
            <DialogTitle>{tp("manualDialogTitle")}</DialogTitle>
          </DashDialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-2">
              <Label htmlFor="manual-uid">{tp("manualUserId")}</Label>
              <Input id="manual-uid" className={dashLtrCell()} value={manualUserId} onChange={(e) => setManualUserId(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="manual-rid">{tp("manualRuleId")}</Label>
              <Input id="manual-rid" className={dashLtrCell()} value={manualRuleId} onChange={(e) => setManualRuleId(e.target.value)} placeholder={tp("manualRuleOptional")} />
            </div>
          </div>
          <DashDialogFooter>
            <Button type="button" variant="outline" onClick={() => setManualOpen(false)}>{tp("cancel")}</Button>
            <Button type="button" onClick={() => void sendManual()} disabled={busy}>{tp("manualSend")}</Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </DashPage>
  )
}
