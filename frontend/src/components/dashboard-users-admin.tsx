"use client"

import { useCallback, useEffect, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { Search } from "lucide-react"

import { Badge } from "@/components/ui/badge"
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
import { Separator } from "@/components/ui/separator"
import { DataPagination } from "@/components/data-pagination"
import { DashboardDateTimePicker } from "@/components/dashboard-datetime-picker"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { DashPage } from "@/components/dash-page"
import { DashSelect } from "@/components/dash-select"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber, formatPlainLatinInt } from "@/lib/format-locale"
import { cn } from "@/lib/utils"
import type { BotPlatformId } from "@/config/bot-platforms"
import { BOT_PLATFORMS } from "@/config/bot-platforms"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { DashboardUserMergeAdmin } from "@/components/dashboard-user-merge-admin"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogTitle, DialogTrigger } from "@/components/ui/dialog"

const USERS_TABLE_COLS = ["7%", "20%", "11%", "9%", "14%", "14%", "10%"]

export type UsersListFilters = {
  status: string
  role: string
  platform: string
  segment: string
  sort: string
  dateFrom: string
  dateTo: string
  minSvc: string
  maxSvc: string
}

export const DEFAULT_USERS_LIST_FILTERS: UsersListFilters = {
  status: "all",
  role: "all",
  platform: "all",
  segment: "all",
  sort: "created_desc",
  dateFrom: "",
  dateTo: "",
  minSvc: "",
  maxSvc: "",
}

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function displayName(u: DashRecord): string {
  const fn = String(u.first_name ?? "").trim()
  const ln = String(u.last_name ?? "").trim()
  const combined = `${fn} ${ln}`.trim()
  if (combined) return combined
  const un = String(u.username ?? "").trim()
  return un || "—"
}

function formatAtUsername(raw: unknown): string {
  const s = String(raw ?? "").trim()
  if (!s) return ""
  return s.startsWith("@") ? s : `@${s}`
}

/** Match backend digit normalization: numeric id / phone queries can be 1+ chars. */
function isDigitOnlyQuery(raw: string): boolean {
  const t = raw.replace(/\s/g, "")
  if (!t) return false
  return /^[\d۰-۹٠-٩]+$/.test(t)
}

function statusBadgeVariant(
  st: string
): "default" | "secondary" | "destructive" | "outline" {
  if (st === "approved") return "default"
  if (st === "pending") return "secondary"
  if (st === "rejected") return "destructive"
  if (st === "blocked") return "outline"
  return "outline"
}

function IdsCell({ numericId, username, emptyLabel }: { numericId: number; username: string; emptyLabel: string }) {
  if (numericId <= 0) return <span className="text-muted-foreground">{emptyLabel}</span>
  const at = formatAtUsername(username)
  return (
    <div className="flex w-full min-w-0 flex-col gap-0.5 text-start">
      <div dir="ltr" className="block w-full font-mono text-xs tabular-nums">
        {formatPlainLatinInt(numericId)}
      </div>
      {at ? (
        <div dir="ltr" className="block w-full break-all text-xs text-muted-foreground">
          {at}
        </div>
      ) : null}
    </div>
  )
}

export function DashboardUsersAdmin({
  users,
  pending,
  usersPagination,
  pendingPagination,
  usersSearchQuery,
  onUsersSearchQueryChange,
  listFilters,
  onListFiltersChange,
  onMutateSuccess,
  onUsersPageChange,
  onUsersPerPageChange,
  onPendingPageChange,
  onPendingPerPageChange,
  onOpenUserDetail,
  isReseller = false,
  actorPermissions,
  enabledPlatforms = BOT_PLATFORMS.map((p) => p.id),
}: {
  users: DashRecord[]
  pending: DashRecord[]
  usersPagination: PaginationMeta | null
  pendingPagination: PaginationMeta | null
usersSearchQuery: string
  onUsersSearchQueryChange: (q: string) => void
  listFilters: UsersListFilters
  onListFiltersChange: (patch: Partial<UsersListFilters>) => void
  onMutateSuccess?: () => void
  onUsersPageChange: (page: number) => void
  onUsersPerPageChange: (perPage: number) => void
  onPendingPageChange: (page: number) => void
  onPendingPerPageChange: (perPage: number) => void
  onOpenUserDetail: (id: number) => void
  isReseller?: boolean
  actorPermissions?: Record<string, boolean>
  enabledPlatforms?: BotPlatformId[]
}) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const tp = (k: string) => t(`usersAdmin.${k}`)
  const statusLabel = (st: string) => t(`usersAdmin.status_${st}`, { defaultValue: st })

  const [busyId, setBusyId] = useState<number | null>(null)
  const [alertText, setAlertText] = useState<string | null>(null)
  const [mergeOpen, setMergeOpen] = useState(false)
  const [createOpen, setCreateOpen] = useState(false)
  const [createUsername, setCreateUsername] = useState("")
  const [createFirstName, setCreateFirstName] = useState("")
  const [createBusy, setCreateBusy] = useState(false)
  const [searchDraft, setSearchDraft] = useState(usersSearchQuery)
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const canManageUsers = !isReseller || actorPermissions?.["users.manage"] !== false
  const showTg = enabledPlatforms.includes("telegram")
  const showBale = enabledPlatforms.includes("bale")
  const canMergeUsers = !isReseller

  const hasActiveFilters =
    listFilters.status !== "all" ||
    listFilters.role !== "all" ||
    listFilters.platform !== "all" ||
    listFilters.segment !== "all" ||
    listFilters.sort !== "created_desc" ||
    listFilters.dateFrom.trim() !== "" ||
    listFilters.dateTo.trim() !== "" ||
    listFilters.minSvc.trim() !== "" ||
    listFilters.maxSvc.trim() !== ""

  const clearFilters = useCallback(() => {
    onListFiltersChange({ ...DEFAULT_USERS_LIST_FILTERS })
  }, [onListFiltersChange])

  useEffect(() => {
    setSearchDraft(usersSearchQuery)
  }, [usersSearchQuery])

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      const next = searchDraft.trim()
      const effective =
        next !== "" && !isDigitOnlyQuery(next) && next.length < 2 ? "" : next
      if (effective !== usersSearchQuery.trim()) {
        onUsersSearchQueryChange(effective)
      }
    }, 580)
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
  }, [searchDraft, usersSearchQuery, onUsersSearchQueryChange])

  const runMembership = useCallback(
    async (userId: number, action: "approve" | "reject") => {
      setBusyId(userId)
      setAlertText(null)
      try {
        const res = await postAdminMutate("membership", {
          membership_user_id: userId,
          svp_user_membership_action: action,
        })
        if (!res.ok) {
          const parts = [res.message, res.reason].filter(Boolean)
          setAlertText(parts.length ? parts.join(" — ") : tp("mutateError"))
          return
        }
        onMutateSuccess?.()
      } finally {
        setBusyId(null)
      }
    },
    [onMutateSuccess, tp]
  )

  const uname = (u: DashRecord) => String(u.username ?? "")

  const renderUserCard = (u: DashRecord, showActions: boolean, showManage?: boolean) => {
    const id = num(u.id)
    const st = String(u.status ?? "")
    const tg = num(u.tg_user_id)
    const bl = num(u.bale_user_id)
    return (
      <Card>
        <CardHeader className="space-y-1 pb-2">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
              <CardTitle className="text-base">{displayName(u)}</CardTitle>
              <CardDescription dir="ltr" className="font-mono text-xs">
                #{formatPlainLatinInt(id)}
              </CardDescription>
            </div>
            <Badge variant={statusBadgeVariant(st)}>{statusLabel(st)}</Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span>
              {tp("colServices")}: {formatNumber(num(u.svc_count), isFa)}
            </span>
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            {showTg ? (
            <div>
              <div className="mb-0.5 text-xs font-medium text-muted-foreground">{tp("colTelegram")}</div>
              <IdsCell numericId={tg} username={uname(u)} emptyLabel="—" />
            </div>
            ) : null}
            {showBale ? (
            <div>
              <div className="mb-0.5 text-xs font-medium text-muted-foreground">{tp("colBale")}</div>
              <IdsCell numericId={bl} username={uname(u)} emptyLabel="—" />
            </div>
            ) : null}
          </div>
          {showActions ? (
            <div className="flex flex-wrap gap-2 pt-2">
              <Button
                type="button"
                size="sm"
                disabled={busyId === id}
                onClick={() => void runMembership(id, "approve")}
              >
                {tp("approve")}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={busyId === id}
                onClick={() => void runMembership(id, "reject")}
              >
                {tp("reject")}
              </Button>
            </div>
          ) : null}
          {showManage ? (
            <div className="pt-2">
              <Button type="button" size="sm" variant="outline" onClick={() => onOpenUserDetail(id)}>
                {tp("manage")}
              </Button>
            </div>
          ) : null}
        </CardContent>
      </Card>
    )
  }

  return (
    <DashPage className={"space-y-8"} data-testid="dash-users-tab">
      <DashboardPageHeader
        title={tp("title")}
        description={tp("subtitle")}
        actions={
          <div className="flex flex-wrap gap-2">
            {canManageUsers ? (
              <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogTrigger asChild>
                  <Button type="button" variant="default" size="sm" data-testid="dash-users-manual-create-open">
                    {tp("manualCreate")}
                  </Button>
                </DialogTrigger>
                <DashDialogContent className="sm:max-w-md">
                  <DashDialogHeader>
                    <DialogTitle>{tp("manualCreate")}</DialogTitle>
                  </DashDialogHeader>
                  <div className="space-y-3">
                    <div className="space-y-1">
                      <Label htmlFor="manual_create_username">{tp("username")}</Label>
                      <Input
                        id="manual_create_username"
                        data-testid="dash-users-manual-create-username"
                        value={createUsername}
                        onChange={(e) => setCreateUsername(e.target.value)}
                        dir="ltr"
                      />
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="manual_create_first">{tp("firstName")}</Label>
                      <Input
                        id="manual_create_first"
                        data-testid="dash-users-manual-create-first"
                        value={createFirstName}
                        onChange={(e) => setCreateFirstName(e.target.value)}
                      />
                    </div>
                    <Button
                      type="button"
                      data-testid="dash-users-manual-create-submit"
                      disabled={createBusy || !createUsername.trim()}
                      onClick={async () => {
                        setCreateBusy(true)
                        setAlertText(null)
                        try {
                          const res = await postAdminMutate("user_manual_create", {
                            username: createUsername.trim(),
                            first_name: createFirstName.trim() || createUsername.trim(),
                          })
                          if (!res.ok) {
                            setAlertText(String(res.message ?? tp("actionFailed")))
                            return
                          }
                          setCreateOpen(false)
                          setCreateUsername("")
                          setCreateFirstName("")
                          onMutateSuccess?.()
                        } finally {
                          setCreateBusy(false)
                        }
                      }}
                    >
                      {createBusy ? tp("loading") : tp("manualCreate")}
                    </Button>
                  </div>
                </DashDialogContent>
              </Dialog>
            ) : null}
            {canMergeUsers ? (
            <Dialog open={mergeOpen} onOpenChange={setMergeOpen}>
              <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                  {tp("mergeUsers")}
                </Button>
              </DialogTrigger>
              <DashDialogContent className="sm:max-w-3xl">
                <DashDialogHeader>
                  <DialogTitle>{tp("mergeUsers")}</DialogTitle>
                </DashDialogHeader>
                <DashboardUserMergeAdmin
        onMutateSuccess={() => {
                    onMutateSuccess?.()
                    setMergeOpen(false)
                  }}
                />
              </DashDialogContent>
            </Dialog>
            ) : null}
          </div>
        }
      />

      {alertText ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {alertText}
        </div>
      ) : null}

      <section className="space-y-3">
        <h3 className="text-base font-medium">{tp("pendingSection")}</h3>
        {pending.length === 0 ? (
          <p className="text-sm text-muted-foreground">{tp("pendingEmpty")}</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {pending.map((u) => (
              <li key={String(u.id ?? "")}>{renderUserCard(u, canManageUsers, true)}</li>
            ))}
          </ul>
        )}
        <DataPagination
          meta={pendingPagination}
        onPageChange={onPendingPageChange}
          onPerPageChange={onPendingPerPageChange}
        />
      </section>

      <Separator />

      <section className="space-y-3">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h3 className="text-base font-medium">{tp("allUsersSection")}</h3>
          {usersPagination ? (
            <p className="text-xs text-muted-foreground">
              {t("usersAdmin.listPaginationHint", { total: formatNumber(usersPagination.total, isFa) })}
            </p>
          ) : null}
        </div>
        <div className={cn("space-y-1.5")}>
          <div className="relative max-w-md">
            <Search
              className={cn(
                "pointer-events-none absolute top-1/2 size-4 -translate-y-1/2 text-muted-foreground",
                isFa ? "end-3" : "start-3"
              )}
              aria-hidden
            />
            <Input
              type="search"
              value={searchDraft}
              onChange={(e) => setSearchDraft(e.target.value)}
              placeholder={tp("searchPlaceholder")}
              className={cn(isFa ? "pe-9" : "ps-9")}
              autoComplete="off"
            />
          </div>
          <p className="text-xs text-muted-foreground">{tp("searchHint")}</p>
        </div>
        <div className="space-y-3 rounded-lg border border-border/60 bg-muted/20 p-3">
          <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-2">
              <Label className="text-xs text-muted-foreground">{tp("filterStatus")}</Label>
              <DashSelect
                triggerClassName="w-auto min-w-[10rem]"
                value={listFilters.status}
                onValueChange={(v) => onListFiltersChange({ status: v })}
                options={[
                  { value: "all", label: tp("filterAll") },
                  { value: "pending", label: statusLabel("pending") },
                  { value: "approved", label: statusLabel("approved") },
                  { value: "rejected", label: statusLabel("rejected") },
                  { value: "blocked", label: statusLabel("blocked") },
                ]}
              />
            </div>
            <div className="space-y-2">
              <Label className="text-xs text-muted-foreground">{tp("filterRole")}</Label>
              <DashSelect
                triggerClassName="w-auto min-w-[10rem]"
                value={listFilters.role}
                onValueChange={(v) => onListFiltersChange({ role: v })}
                options={[
                  { value: "all", label: tp("filterAll") },
                  { value: "user", label: tp("filterRoleUser") },
                  { value: "reseller", label: tp("filterRoleReseller") },
                  { value: "admin", label: tp("filterRoleAdmin") },
                ]}
              />
            </div>
            <div className="space-y-2">
              <Label className="text-xs text-muted-foreground">{tp("filterPlatform")}</Label>
              <DashSelect
                triggerClassName="w-auto min-w-[10rem]"
                value={listFilters.platform}
                onValueChange={(v) => onListFiltersChange({ platform: v })}
                options={[
                  { value: "all", label: tp("filterAll") },
                  ...(showTg ? [{ value: "telegram", label: tp("filterPlatformTelegram") }] : []),
                  ...(showBale ? [{ value: "bale", label: tp("filterPlatformBale") }] : []),
                  ...(showTg && showBale ? [{ value: "both", label: tp("filterPlatformBoth") }] : []),
                  { value: "none", label: tp("filterPlatformNone") },
                ]}
              />
            </div>
            <div className="space-y-2">
              <Label className="text-xs text-muted-foreground">{tp("filterSegment")}</Label>
              <DashSelect
                triggerClassName="w-auto min-w-[12rem]"
                value={listFilters.segment}
                onValueChange={(v) => onListFiltersChange({ segment: v })}
                options={[
                  { value: "all", label: tp("filterSegmentAll") },
                  { value: "churned", label: tp("filterSegment_churned") },
                  { value: "never_purchased", label: tp("filterSegment_never_purchased") },
                  { value: "abandoned_checkout", label: tp("filterSegment_abandoned_checkout") },
                  { value: "stale_buy_funnel", label: tp("filterSegment_stale_buy_funnel") },
                  { value: "expiring_renew", label: tp("filterSegment_expiring_renew") },
                ]}
              />
            </div>
            <div className="space-y-2">
              <Label className="text-xs text-muted-foreground">{tp("sortLabel")}</Label>
              <DashSelect
                triggerClassName="w-auto min-w-[10rem]"
                value={listFilters.sort}
                onValueChange={(v) => onListFiltersChange({ sort: v })}
                options={[
                  { value: "created_desc", label: tp("sortCreatedDesc") },
                  { value: "created_asc", label: tp("sortCreatedAsc") },
                  { value: "id_desc", label: tp("sortIdDesc") },
                  { value: "id_asc", label: tp("sortIdAsc") },
                  { value: "services_desc", label: tp("sortServicesDesc") },
                  { value: "services_asc", label: tp("sortServicesAsc") },
                  { value: "status_asc", label: tp("sortStatusAsc") },
                  { value: "status_desc", label: tp("sortStatusDesc") },
                  { value: "name_asc", label: tp("sortNameAsc") },
                  { value: "name_desc", label: tp("sortNameDesc") },
                ]}
              />
            </div>
            <div className="min-w-[11rem] flex-1">
              <DashboardDateTimePicker
                label={tp("dateFrom")}
                value={listFilters.dateFrom}
                onChange={(v) => onListFiltersChange({ dateFrom: v })}
              />
            </div>
            <div className="min-w-[11rem] flex-1">
              <DashboardDateTimePicker
                label={tp("dateTo")}
                value={listFilters.dateTo}
                onChange={(v) => onListFiltersChange({ dateTo: v })}
              />
            </div>
            <div className="space-y-2">
              <Label className="text-xs text-muted-foreground">{tp("svcMin")}</Label>
              <Input
                dir="ltr"
                className="w-28 font-mono"
                value={listFilters.minSvc}
                onChange={(e) => onListFiltersChange({ minSvc: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label className="text-xs text-muted-foreground">{tp("svcMax")}</Label>
              <Input
                dir="ltr"
                className="w-28 font-mono"
                value={listFilters.maxSvc}
                onChange={(e) => onListFiltersChange({ maxSvc: e.target.value })}
              />
            </div>
            {hasActiveFilters ? (
              <Button type="button" variant="ghost" size="sm" className="h-9" onClick={clearFilters}>
                {tp("filterClear")}
              </Button>
            ) : null}
          </div>
        </div>
        {users.length === 0 ? (
          <p className="text-sm text-muted-foreground">{tp("usersEmpty")}</p>
        ) : (
          <DashTableShell
        minWidth="42rem" colWidths={USERS_TABLE_COLS}>
            <thead>
              <tr className="bg-muted/40">
                <DashTh>{tp("colId")}</DashTh>
                <DashTh>{tp("colName")}</DashTh>
                <DashTh>{tp("colStatus")}</DashTh>
                <DashTh>{tp("colServices")}</DashTh>
{showTg ? <DashTh>{tp("colTelegram")}</DashTh> : null}
                {showBale ? <DashTh>{tp("colBale")}</DashTh> : null}
                <DashTh>{tp("colActions")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {users.map((u) => {
                const id = num(u.id)
                const st = String(u.status ?? "")
                const tg = num(u.tg_user_id)
                const bl = num(u.bale_user_id)
                return (
                  <tr key={id}>
                    <DashTd dir="ltr" className="font-mono text-xs tabular-nums">
                      {formatPlainLatinInt(id)}
                    </DashTd>
                    <DashTd>
                      <div className="flex flex-wrap items-center gap-1">
                        <span>{displayName(u)}</span>
                        {u.marketing_open_offer ? (
                          <Badge variant="outline" className="text-xs font-normal">
                            {tp("badgeOpenOffer")}
                          </Badge>
                        ) : null}
                      </div>
                    </DashTd>
                    <DashTd>
                      <Badge variant={statusBadgeVariant(st)} className="font-normal">
                        {statusLabel(st)}
                      </Badge>
                    </DashTd>
                    <DashTd className="tabular-nums">{formatNumber(num(u.svc_count), isFa)}</DashTd>
                    {showTg ? (
                    <DashTd>
                      <IdsCell numericId={tg} username={uname(u)} emptyLabel="—" />
                    </DashTd>
                    ) : null}
                    {showBale ? (
                    <DashTd>
                      <IdsCell numericId={bl} username={uname(u)} emptyLabel="—" />
                    </DashTd>
                    ) : null}
                    <DashTd>
                      <Button type="button" size="sm" variant="outline" onClick={() => onOpenUserDetail(id)}>
                        {tp("manage")}
                      </Button>
                    </DashTd>
                  </tr>
                )
              })}
            </tbody>
          </DashTableShell>
        )}
        <DataPagination
          meta={usersPagination}
        onPageChange={onUsersPageChange}
          onPerPageChange={onUsersPerPageChange}
        />
      </section>
    </DashPage>
  )
}
