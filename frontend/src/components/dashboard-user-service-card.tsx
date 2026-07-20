"use client"

import { useMemo, useState, type ReactNode } from "react"
import {
  Archive,
  Calendar,
  ChevronDown,
  HardDrive,
  Hash,
  KeyRound,
  Link2,
  Mail,
  Minus,
  Package,
  Pencil,
  Plus,
  Power,
  Radio,
  RefreshCw,
  Send,
  Server,
  StickyNote,
  Trash2,
  Users,
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
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Progress } from "@/components/ui/progress"
import { Separator } from "@/components/ui/separator"
import {
  planForService,
  previewAddSlotsPriceToman,
  previewAddVolumePriceToman,
  previewRenewPriceToman,
} from "@/lib/dashboard-user-detail-pricing"
import {
  formatNumber,
  formatPlainLatinInt,
  formatServiceExpiryLine,
  formatServiceQuotaLine,
} from "@/lib/format-locale"
import { useTranslations } from "next-intl"
import { DashSelect } from "@/components/dash-select"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

type DashRecord = Record<string, unknown>
type PayMode = "free" | "wallet" | "invoice"

export type ServiceActionKind =
  | "renew"
  | "traffic"
  | "days"
  | "users"
  | "limitIp"
  | "regen"
  | "regenSub"
  | "refresh"
  | "sync"
  | "deletePanel"
  | "deleteService"
  | "transfer"

export type ServiceActionDlg = {
  kind: ServiceActionKind
  sid: number
  svc: DashRecord
} | null

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function statusVariant(subState: string): "default" | "secondary" | "destructive" {
  if (subState === "active") return "default"
  if (subState === "expired") return "destructive"
  return "secondary"
}

function InfoRow({
  icon: Icon,
  label,
  children,
  valueDir,
}: {
  icon: React.ComponentType<{ className?: string }>
  label: string
  children: ReactNode
  valueDir?: "ltr" | "rtl"
}) {

  return (
    <div className="flex gap-2 text-start text-xs">
      <Icon className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" aria-hidden />
      <div className="min-w-0 flex-1">
        <p className="text-muted-foreground">{label}</p>
        <div className="font-medium text-foreground" dir={valueDir}>
          {children}
        </div>
      </div>
    </div>
  )
}

export function ServiceActionDialog({
  dlg,
  setDlg,
  isReseller,
  busy,
  plans,
  pricePerExtraUser,
  tp,
  onConfirm,
}: {
  dlg: ServiceActionDlg
  setDlg: (v: ServiceActionDlg) => void
isReseller: boolean
  busy: boolean
  plans: DashRecord[]
  pricePerExtraUser: number
  tp: (k: string, opts?: Record<string, string | number>) => string
  onConfirm: (op: string, payload: Record<string, unknown>) => void
}) {
  const { isFa } = useDashLocale()
  const [payMode, setPayMode] = useState<PayMode>(isReseller ? "wallet" : "free")
  const [direction, setDirection] = useState<"add" | "reduce">("add")
  const [amount, setAmount] = useState("")
  const [transferTarget, setTransferTarget] = useState("")

  const svc = dlg?.svc
  const kind = dlg?.kind
  const sid = dlg?.sid ?? 0
  const plan = svc ? planForService(svc, plans) : undefined

  const open = dlg !== null

  const resetFields = () => {
    setPayMode(isReseller ? "wallet" : "free")
    setDirection("add")
    setAmount("")
    setTransferTarget("")
  }

  const handleOpenChange = (o: boolean) => {
    if (!o) {
      setDlg(null)
      resetFields()
    }
  }

  const amtNum = parseInt(amount.trim(), 10)
  const pricePreview = useMemo(() => {
    if (!svc || !kind) return null
    if (kind === "renew") return previewRenewPriceToman(svc)
    if (kind === "traffic" && direction === "add" && Number.isFinite(amtNum) && amtNum > 0) {
      return previewAddVolumePriceToman(svc, amtNum, plan)
    }
    if (kind === "users" && direction === "add" && Number.isFinite(amtNum) && amtNum > 0) {
      return previewAddSlotsPriceToman(amtNum, pricePerExtraUser)
    }
    return null
  }, [svc, kind, direction, amtNum, plan, pricePerExtraUser])

  const needsAmount = kind === "traffic" || kind === "days" || kind === "users" || kind === "limitIp"
  const needsPayMode =
    !isReseller &&
    ((kind === "renew") || (kind === "traffic" && direction === "add") || (kind === "users" && direction === "add"))
  const showPayMode = needsPayMode || (isReseller && (kind === "renew" || (kind === "traffic" && direction === "add") || (kind === "users" && direction === "add")))

  const titleKey = useMemo(() => {
    if (!kind) return ""
    const map: Record<ServiceActionKind, string> = {
      renew: "dlgRenewTitle",
      traffic: "dlgTrafficTitle",
      days: "dlgDaysTitle",
      users: "dlgUsersTitle",
      limitIp: "dlgLimitIpTitle",
      regen: "dlgRegenTitle",
      regenSub: "dlgRegenSubTitle",
      refresh: "dlgRefreshTitle",
      sync: "actionSyncMeta",
      deletePanel: "dlgDeletePanelTitle",
      deleteService: "dlgDeleteServiceTitle",
      transfer: "dlgTransferTitle",
    }
    return map[kind]
  }, [kind])

  const descKey = useMemo(() => {
    if (!kind) return ""
    if (kind === "traffic") return direction === "add" ? "dlgTrafficDescAdd" : "dlgTrafficDescReduce"
    if (kind === "days") return "dlgDaysDesc"
    if (kind === "users") return direction === "add" ? "dlgUsersDescAdd" : "dlgUsersDescReduce"
    const map: Partial<Record<ServiceActionKind, string>> = {
      renew: "dlgRenewDesc",
      limitIp: "dlgLimitIpDesc",
      regen: "dlgRegenDesc",
      regenSub: "dlgRegenSubDesc",
      refresh: "dlgRefreshDesc",
      deletePanel: "dlgDeletePanelDesc",
      deleteService: "dlgDeleteServiceDesc",
      transfer: "dlgTransferDesc",
    }
    return map[kind] ?? ""
  }, [kind, direction])

  const canConfirm = () => {
    if (!kind || sid < 1) return false
    if (kind === "transfer") return transferTarget.trim() !== ""
    if (needsAmount && (!Number.isFinite(amtNum) || amtNum < 1)) return false
    return true
  }

  const handleConfirm = () => {
    if (!kind || sid < 1) return
    const payload: Record<string, unknown> = { service_id: sid }
    let op = ""
    switch (kind) {
      case "renew":
        op = "user_renew_service"
        payload.mode = payMode
        break
      case "traffic":
        if (direction === "add") {
          op = "user_add_volume"
          payload.extra_gb = amtNum
          payload.mode = payMode
        } else {
          op = "user_reduce_volume"
          payload.reduce_gb = amtNum
        }
        break
      case "days":
        payload.days = amtNum
        op = direction === "add" ? "user_add_days" : "user_reduce_days"
        break
      case "users":
        if (direction === "add") {
          op = "user_service_add_slots"
          payload.extra_users = amtNum
          payload.mode = payMode
        } else {
          op = "user_service_reduce_slots"
          payload.reduce_users = amtNum
        }
        break
      case "limitIp":
        op = "service_set_limit_ip"
        payload.limit_ip = amtNum
        break
      case "regen":
        op = "service_regen_key"
        break
      case "regenSub":
        op = "service_regen_sub_id"
        break
      case "refresh":
        op = "service_panel_refresh"
        break
      case "sync":
        op = "service_panel_sync"
        break
      case "deletePanel":
        op = "service_panel_delete_client"
        break
      case "deleteService":
        op = "service_delete"
        break
      case "transfer":
        op = "user_service_transfer"
        payload.target = transferTarget.trim()
        break
      default:
        break
    }
    if (op) {
      onConfirm(op, payload)
    }
    setDlg(null)
    resetFields()
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DashDialogContent className="sm:max-w-md">
        <DashDialogHeader>
          <DialogTitle>{titleKey ? tp(titleKey) : ""}</DialogTitle>
          {descKey ? <DialogDescription>{tp(descKey)}</DialogDescription> : null}
        </DashDialogHeader>

        <div className="space-y-3">
          {(kind === "traffic" || kind === "days" || kind === "users") && (
            <div className="flex gap-2">
              <Button
                type="button"
                size="sm"
                variant={direction === "add" ? "default" : "outline"}
                onClick={() => setDirection("add")}
              >
                <Plus className="size-3.5" />
                {tp("dlgAdd")}
              </Button>
              <Button
                type="button"
                size="sm"
                variant={direction === "reduce" ? "default" : "outline"}
                onClick={() => setDirection("reduce")}
              >
                <Minus className="size-3.5" />
                {tp("dlgReduce")}
              </Button>
            </div>
          )}

          {needsAmount ? (
            <div className="space-y-1">
              <Label>
                {kind === "days"
                  ? tp("dlgDays")
                  : kind === "users"
                    ? tp("dlgUsers")
                    : kind === "limitIp"
                      ? tp("dlgUsers")
                      : tp("dlgGb")}
              </Label>
              <Input
                dir="ltr"
                type="number"
                min={1}
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                disabled={busy}
              />
            </div>
          ) : null}

          {kind === "transfer" ? (
            <div className="space-y-1">
              <Label>{tp("dlgTransferTarget")}</Label>
              <Input
                dir="ltr"
                value={transferTarget}
                onChange={(e) => setTransferTarget(e.target.value)}
                placeholder={tp("transferPlaceholder")}
                disabled={busy}
              />
            </div>
          ) : null}

          {showPayMode ? (
            <div className="space-y-1">
              <Label>{tp("mode")}</Label>
              <DashSelect
                value={payMode}
                onValueChange={(v) => setPayMode(v as PayMode)}
                disabled={busy}
                options={[
                  ...(!isReseller ? [{ value: "free", label: tp("modeFree") }] : []),
                  { value: "wallet", label: tp("modeWallet") },
                  { value: "invoice", label: tp("modeInvoice") },
                ]}
              />
            </div>
          ) : null}

          {pricePreview != null && showPayMode ? (
            <p className="text-sm text-muted-foreground">
              {tp("dlgEstimatedPrice")}:{" "}
              <span className="font-medium tabular-nums text-foreground">{formatNumber(pricePreview, isFa)}</span>
            </p>
          ) : null}
        </div>

        <DashDialogFooter className={cn("gap-2")}>
          <Button type="button" variant="outline" onClick={() => handleOpenChange(false)} disabled={busy}>
            {tp("dlgCancel")}
          </Button>
          <Button
            type="button"
            variant={kind === "deletePanel" || kind === "deleteService" ? "destructive" : "default"}
            disabled={busy || !canConfirm()}
            onClick={handleConfirm}
          >
            {tp("dlgConfirm")}
          </Button>
        </DashDialogFooter>
      </DashDialogContent>
    </Dialog>
  )
}

export function DashboardUserServiceCard({
  svc,
  plans: _plans,
  isReseller: _isReseller,
  busy,
  tp,
  onOpenAction,
  onPatchAlert,
  onToggleEnable,
  onSetServiceNote,
}: {
  svc: DashRecord
  plans: DashRecord[]
isReseller: boolean
  busy: boolean
  tp: (k: string, opts?: Record<string, string | number>) => string
  onOpenAction: (kind: ServiceActionKind, svc: DashRecord) => void
  onPatchAlert: (key: "alerts_enabled" | "alerts_volume" | "alerts_expiry" | "alerts_users", val: boolean) => void
  onToggleEnable?: (enabled: boolean) => void
  onSetServiceNote?: (note: string) => void
}) {
  const { isFa } = useDashLocale()
  const tPlans = useTranslations("plansAdmin")
  const [noteOpen, setNoteOpen] = useState(false)
  const [noteDraft, setNoteDraft] = useState("")
  const sid = num(svc.id)
  const expire = svc.expires_at ?? svc.expire_at ?? svc.expired_at ?? svc.expiry ?? ""
  const subState = String(svc.subscription_state ?? "")
  const quotaGb = num(svc.quota_gb)
  const usedGb = num(svc.used_gb)
  const planName = String(svc.plan_name ?? "")
  const planPrice = num(svc.plan_price)
  const planPpg = num(svc.plan_price_per_gb)
  const pricingType = String(svc.plan_pricing_type ?? "")
  const isL2tp = String(svc.service_type ?? "xray") === "l2tp"
  if (isL2tp) {
    return null
  }
  const subscriptionName = String(
    svc.subscription_name ?? svc.remark ?? svc.display_label ?? ""
  ).trim()
  const panelRemark = String(svc.panel_remark ?? "").trim()
  const serviceNote = String(svc.service_note ?? "").trim()
  const panelEnabled =
    svc.panel_client_enabled != null && svc.panel_client_enabled !== ""
      ? num(svc.panel_client_enabled) === 1
      : true
  const portalSvc = String(svc.portal_service_url ?? "")
  const limitCached = svc.panel_limit_ip != null && svc.panel_limit_ip !== "" ? num(svc.panel_limit_ip) : null
  const ipRows = Array.isArray(svc.ip_log) ? (svc.ip_log as DashRecord[]) : []
  const usedPct = quotaGb > 0 ? Math.min(100, (usedGb / quotaGb) * 100) : 0
  const gbSuffix = tPlans("gbSuffix")
  const quotaLine = formatServiceQuotaLine(quotaGb, usedGb, isFa, {
    usedShort: tp("usedShort"),
    gbSuffix,
  })
  const expiryLine = formatServiceExpiryLine(expire ? String(expire) : null, isFa)
  const valueDir = isFa ? "rtl" : "ltr"

  const actionBtn = (
    label: string,
    kind: ServiceActionKind,
    icon: React.ComponentType<{ className?: string }>,
    variant: "default" | "secondary" | "outline" | "destructive" = "outline"
  ) => {
    const Icon = icon
    return (
      <Button
        type="button"
        size="sm"
        variant={variant}
        className="h-8 gap-1.5 text-xs"
        disabled={busy}
        onClick={() => onOpenAction(kind, svc)}
      >
        <Icon className="size-3.5 shrink-0" />
        {label}
      </Button>
    )
  }

  return (
    <Card className="overflow-hidden">
      <CardHeader className="pb-2">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0 flex-1 text-start">
            <div className="relative flex w-full items-center gap-2">
              <CardTitle className="flex-1 text-center text-base">
                {subscriptionName || tp("serviceUntitled")}
              </CardTitle>
              <span
                className="absolute end-0 font-mono text-xs font-normal tabular-nums text-muted-foreground"
                dir="ltr"
              >
                #{formatPlainLatinInt(sid)}
              </span>
            </div>
            <div className="mt-1 flex flex-wrap items-center justify-center gap-1 text-center text-xs text-muted-foreground">
              <span className="shrink-0">{tp("userNoteLabel")}:</span>
              <span className="min-w-0 break-words">{serviceNote || "—"}</span>
              {onSetServiceNote ? (
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  className="size-7 shrink-0"
                  disabled={busy}
                  aria-label={tp("editServiceNote")}
                  onClick={() => {
                    setNoteDraft(serviceNote)
                    setNoteOpen(true)
                  }}
                >
                  <Pencil className="size-3.5" />
                </Button>
              ) : null}
            </div>
            <CardDescription className="mt-1 flex items-center gap-1 break-all text-start text-xs">
              <Mail className="size-3 shrink-0" />
              <span dir="ltr">{String(svc.email ?? "—")}</span>
            </CardDescription>
          </div>
          {portalSvc ? (
            <Button
              size="sm"
              variant="secondary"
              className="shrink-0 gap-1"
              render={
                <a href={portalSvc} target="_blank" rel="noopener noreferrer" />
              }
            >
              {tp("servicePanelBtn")}
            </Button>
          ) : null}
        </div>
      </CardHeader>

      {onSetServiceNote ? (
        <Dialog open={noteOpen} onOpenChange={setNoteOpen}>
          <DashDialogContent>
            <DashDialogHeader>
              <DialogTitle>{tp("editServiceNote")}</DialogTitle>
              <DialogDescription className="sr-only">{tp("editServiceNote")}</DialogDescription>
            </DashDialogHeader>
            <div className="space-y-2 py-2">
              <Label htmlFor={`svc-note-${sid}`}>{tp("userNoteLabel")}</Label>
              <Input
                id={`svc-note-${sid}`}
                value={noteDraft}
                maxLength={512}
                placeholder={tp("serviceNotePlaceholder")}
                onChange={(e) => setNoteDraft(e.target.value)}
              />
            </div>
            <DashDialogFooter>
              <Button type="button" variant="outline" onClick={() => setNoteOpen(false)}>
                {tp("dlgCancel")}
              </Button>
              <Button
                type="button"
                disabled={busy}
                onClick={() => {
                  onSetServiceNote(noteDraft.trim())
                  setNoteOpen(false)
                }}
              >
                {tp("saveServiceNote")}
              </Button>
            </DashDialogFooter>
          </DashDialogContent>
        </Dialog>
      ) : null}

      <CardContent className="space-y-4 text-start text-sm">
        <div className="grid gap-3 sm:grid-cols-2">
          <InfoRow icon={Package} label={tp("svcPlan")}
        >
            {planName ? (
              <>
                {planName}
                {pricingType === "per_gb" ? (
                  <span className="ms-1 text-muted-foreground" dir={isFa ? undefined : "ltr"}>
                    ({formatNumber(planPpg, isFa)} / {isFa ? gbSuffix : "GB"})
                  </span>
                ) : (
                  <span className="ms-1 text-muted-foreground" dir={isFa ? undefined : "ltr"}>
                    ({tp("basePrice")}: {formatNumber(planPrice, isFa)})
                  </span>
                )}
              </>
            ) : (
              <span dir="ltr">#{formatPlainLatinInt(num(svc.plan_id))}</span>
            )}
          </InfoRow>
          <InfoRow icon={Radio} label={tp("svcStatus")}
        >
            <Badge variant={statusVariant(subState)} className="font-normal">
              {tp(`subscription_${subState}`, { defaultValue: subState })}
            </Badge>
          </InfoRow>
          <InfoRow icon={HardDrive} label={tp("svcVolume")} valueDir={valueDir}>
            <div className="space-y-1">
              <span className="inline-block">{quotaLine}</span>
              {quotaGb > 0 ? <Progress value={usedPct} className="h-1.5" /> : null}
            </div>
          </InfoRow>
          <InfoRow icon={Calendar} label={tp("svcExpires")} valueDir={valueDir}>
            <span className="inline-block">{expiryLine}</span>
          </InfoRow>
          <InfoRow icon={Users} label={tp("svcUserCap")}
        valueDir="ltr">
            <span className="inline-block" dir="ltr">
              {limitCached != null && limitCached > 0 ? formatPlainLatinInt(limitCached) : "—"}
            </span>
          </InfoRow>
          <InfoRow icon={Hash} label="ID"
        valueDir="ltr">
            <span className="inline-block" dir="ltr">{formatPlainLatinInt(sid)}</span>
          </InfoRow>
          {panelRemark && panelRemark !== subscriptionName ? (
            <InfoRow icon={StickyNote} label={tp("noteLabel")} valueDir={isFa ? "rtl" : "ltr"}>
              <div className="space-y-0.5 text-xs">
                <p>
                  <span className="text-muted-foreground">{tp("notePanel")}: </span>
                  {panelRemark}
                </p>
                {!serviceNote ? (
                  <p className="text-muted-foreground">{tp("noteSyncHint")}</p>
                ) : null}
              </div>
            </InfoRow>
          ) : null}
        </div>

        {!isL2tp ? (
          <Collapsible>
            <CollapsibleTrigger
              className="flex w-full items-center justify-between rounded-md border border-border/60 px-2 py-1.5 text-start text-xs font-medium hover:bg-muted/40"
            >
              {tp("notificationsSection")}
              <ChevronDown className="size-4" />
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-2 space-y-2 rounded-md border border-border/50 p-2">
              <div className="grid gap-2 sm:grid-cols-2">
                {(
                  [
                    ["alerts_enabled", tp("alertToggleMaster")],
                    ["alerts_volume", tp("alertToggleVolume")],
                    ["alerts_expiry", tp("alertToggleExpiry")],
                    ["alerts_users", tp("alertToggleUsers")],
                  ] as const
                ).map(([k, label]) => (
                  <label key={k} className="flex cursor-pointer items-center gap-2 text-xs">
                    <input
                      type="checkbox"
                      className="size-4 rounded border-input"
                      checked={num(svc[k]) === 1}
                      disabled={busy}
                      onChange={(e) => onPatchAlert(k, e.target.checked)}
                    />
                    <span>{label}</span>
                  </label>
                ))}
              </div>
              {ipRows.length > 0 ? (
                <>
                  <Separator />
                  <p className="text-xs font-medium text-muted-foreground">{tp("ipLogTitle")}</p>
                  <ul className="max-h-20 space-y-0.5 overflow-y-auto font-mono text-xs">
                    {ipRows.map((ipr) => (
                      <li key={num(ipr.id)} dir="ltr">
                        {String(ipr.ip ?? "")}{" "}
                        <span className="text-muted-foreground">×{formatPlainLatinInt(num(ipr.hit_count))}</span>
                      </li>
                    ))}
                  </ul>
                </>
              ) : null}
            </CollapsibleContent>
          </Collapsible>
        ) : null}

        <div className="space-y-2">
          <p className="text-xs font-medium text-muted-foreground">{tp("serviceActions")}</p>
          <div className="space-y-2">
            <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{tp("actionGroupBilling")}</p>
            <div className="flex flex-wrap gap-1.5">
              {actionBtn(tp("actionRenew"), "renew", RefreshCw, "secondary")}
              {actionBtn(tp("actionTraffic"), "traffic", HardDrive)}
              {actionBtn(tp("actionDays"), "days", Calendar)}
              {!isL2tp ? actionBtn(tp("actionUsers"), "users", Users) : null}
            </div>
            {!isL2tp ? (
              <>
                <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{tp("actionGroupPanel")}</p>
                <div className="flex flex-wrap gap-1.5">
                  {onToggleEnable ? (
                    <Button
                      type="button"
                      size="sm"
                      variant={panelEnabled ? "secondary" : "outline"}
                      className="h-8 gap-1.5 text-xs"
                      disabled={busy}
                      onClick={() => onToggleEnable(!panelEnabled)}
                    >
                      <Power className="size-3.5 shrink-0" />
                      {panelEnabled ? tp("actionDisable") : tp("actionEnable")}
                    </Button>
                  ) : null}
                  {actionBtn(tp("actionRegenUuid"), "regen", KeyRound)}
                  {actionBtn(tp("actionRegenSub"), "regenSub", Link2)}
                  {actionBtn(tp("actionRefreshInbound"), "refresh", Server)}
                  {actionBtn(tp("actionSyncMeta"), "sync", RefreshCw)}
                  {actionBtn(tp("actionSetLimitIp"), "limitIp", Users)}
                </div>
              </>
            ) : null}
            <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{tp("actionGroupDanger")}</p>
            <div className="flex flex-wrap gap-1.5">
              {actionBtn(tp("actionTransfer"), "transfer", Send)}
              {!isL2tp ? actionBtn(tp("actionDeletePanel"), "deletePanel", Trash2, "destructive") : null}
              {actionBtn(tp("actionDeleteService"), "deleteService", Archive, "destructive")}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
