"use client"

import type { ReactNode } from "react"
import { useTranslation } from "react-i18next"
import { formatNumber, formatServiceExpiryLine } from "@/lib/format-locale"
import { broadcastRowStatusLabel } from "@/lib/broadcast-labels"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

import { overviewAccentOutlineBtn } from "@/lib/chart-accent"
import {
  formatOverviewAmount,
  formatOverviewDate,
  overviewNum,
  receiptAmount,
  receiptStatusBadgeVariant,
  userDisplayLabel,
  userStatusBadgeVariant,
  type DashRecord,
} from "@/lib/overview-rows"
import { receiptSelectedService } from "@/lib/format-receipt"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"

export function OverviewSectionCard({
  title,
  description,
  viewAllLabel,
  onViewAll,
  children,
  className,
}: {
  title: string
  description?: string
  viewAllLabel: string
  onViewAll: () => void
children: ReactNode
  className?: string
}) {

  return (
    <Card className={cn("overflow-hidden border-border/80 shadow-sm", className)}>
      <CardHeader className="border-b border-border/60 bg-muted/20 pb-3">
        <div
          className={cn(
            "flex flex-wrap items-start justify-between gap-2"
          )}
        >
          <div className={cn("min-w-0 space-y-0.5")}>
            <CardTitle className="text-base">{title}</CardTitle>
            {description ? (
              <CardDescription className="text-pretty">{description}</CardDescription>
            ) : null}
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className={cn("shrink-0", overviewAccentOutlineBtn)}
            onClick={onViewAll}
          >
            {viewAllLabel}
          </Button>
        </div>
      </CardHeader>
      <CardContent className="p-0">{children}</CardContent>
    </Card>
  )
}

function OverviewEmpty({ message }: { message: string }) {
  return (
    <p className="px-4 py-8 text-center text-sm text-muted-foreground">
      {message}
    </p>
  )
}

function OverviewDataTable({
  headers,
  rows,
}: {
  headers: string[]
  rows: ReactNode[]
}) {
  if (rows.length === 0) return null
  return (
    <Table>
      <TableHeader>
        <TableRow className="hover:bg-transparent">
          {headers.map((h) => (
            <TableHead key={h} className="text-start">
              {h}
            </TableHead>
          ))}
        </TableRow>
      </TableHeader>
      <TableBody>{rows}</TableBody>
    </Table>
  )
}

function ClickableRow({
  onClick,
  children,
}: {
  onClick: () => void
  children: ReactNode
}) {
  return (
    <TableRow
      className="cursor-pointer hover:bg-primary/5"
      onClick={onClick}
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault()
          onClick()
        }
      }}
      tabIndex={0}
      role="button"
    >
      {children}
    </TableRow>
  )
}

export function OverviewRecentUsers({
  rows,
  onViewAll,
  onOpenUser,
}: {
  rows: DashRecord[]
onViewAll: () => void
  onOpenUser: (id: number) => void
}) {
  const { isFa } = useDashLocale()
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)
  const statusLabel = (st: string) =>
    t(`usersAdmin.status_${st}`, { defaultValue: st || "—" })

  const tableRows = rows.map((u) => {
    const id = overviewNum(u.id)
    const st = String(u.status ?? "")
    return (
      <ClickableRow key={id} onClick={() => id > 0 && onOpenUser(id)}>
        <TableCell className={cn("font-medium")}>
          {userDisplayLabel(u)}
        </TableCell>
        <TableCell className="text-start">
          <Badge variant={userStatusBadgeVariant(st)} className="font-normal">
            {statusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell
          className={cn("text-muted-foreground tabular-nums text-xs")}
        >
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(u.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("recentUsers")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
        >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")}
        />
      ) : (
        <OverviewDataTable
        headers={[tp("colUser"), tp("colStatus"), tp("colDate")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewRecentReceipts({
  rows,
  onViewAll,
  onOpenReceipt,
}: {
  rows: DashRecord[]
onViewAll: () => void
  onOpenReceipt: (row: DashRecord) => void
}) {
  const { isFa } = useDashLocale()
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)
  const rt = (k: string) => t(`receiptsAdmin.${k}`, { defaultValue: k })

  const receiptStatusLabel = (st: string) => {
    if (st === "pending") return rt("statusPending")
    if (st === "processing") return rt("statusProcessing")
    if (st === "approved") return rt("statusApproved")
    if (st === "rejected") return rt("statusRejected")
    return st || "—"
  }

  const tableRows = rows.map((r) => {
    const id = overviewNum(r.id)
    const st = String(r.status ?? "").toLowerCase()
    const label = String(r.user_label ?? r.user_name ?? "").trim() || userDisplayLabel(r)
    const amt = receiptAmount(r)
    return (
      <ClickableRow key={id} onClick={() => onOpenReceipt(r)}>
        <TableCell className={cn("max-w-[10rem] truncate font-medium")}>
          {label}
        </TableCell>
        <TableCell className={cn("max-w-[10rem] truncate text-sm")}>
          {receiptSelectedService(r)}
        </TableCell>
        <TableCell className={cn("tabular-nums")}>
          <span dir="ltr" className="inline-block">
            {formatOverviewAmount(amt, isFa, rt("amountFree"))}
          </span>
        </TableCell>
        <TableCell className="text-start">
          <Badge variant={receiptStatusBadgeVariant(st)} className="font-normal">
            {receiptStatusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell
          className={cn("text-muted-foreground tabular-nums text-xs")}
        >
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(r.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("recentReceipts")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
        >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")}
        />
      ) : (
        <OverviewDataTable
        headers={[tp("colUser"), rt("colSelectedService"), tp("colAmount"), tp("colStatus"), tp("colDate")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewPendingUsers({
  rows,
  onViewAll,
  onOpenUser,
}: {
  rows: DashRecord[]
onViewAll: () => void
  onOpenUser: (id: number) => void
}) {
  const { isFa } = useDashLocale()
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)

  const tableRows = rows.map((u) => {
    const id = overviewNum(u.id)
    return (
      <ClickableRow key={id} onClick={() => id > 0 && onOpenUser(id)}>
        <TableCell className={cn("font-medium")}>
          {userDisplayLabel(u)}
        </TableCell>
        <TableCell
          className={cn("text-muted-foreground tabular-nums text-xs")}
        >
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(u.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("pendingApprovals")}
      description={tp("pendingApprovalsHint")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
        >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")}
        />
      ) : (
        <OverviewDataTable
        headers={[tp("colUser"), tp("colDate")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewRecentResellers({
  rows,
  onViewAll,
  onOpenReseller,
}: {
  rows: DashRecord[]
onViewAll: () => void
  onOpenReseller: (id: number) => void
}) {
  const { isFa } = useDashLocale()
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)
  const statusLabel = (st: string) =>
    t(`usersAdmin.status_${st}`, { defaultValue: st || "—" })

  const tableRows = rows.map((u) => {
    const id = overviewNum(u.id)
    const st = String(u.status ?? "")
    return (
      <ClickableRow key={id} onClick={() => id > 0 && onOpenReseller(id)}>
        <TableCell className={cn("font-medium")}>
          {userDisplayLabel(u)}
        </TableCell>
        <TableCell className="text-start">
          <Badge variant={userStatusBadgeVariant(st)} className="font-normal">
            {statusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell className={cn("tabular-nums")}>
          <span dir="ltr" className="inline-block">
            {formatNumber(overviewNum(u.svc_count), isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("recentResellers")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
        >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")}
        />
      ) : (
        <OverviewDataTable
        headers={[tp("colUser"), tp("colStatus"), tp("colServices")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewRecentBroadcasts({
  rows,
  onViewAll,
  onOpenBroadcast,
}: {
  rows: DashRecord[]
onViewAll: () => void
  onOpenBroadcast: () => void
}) {
  const { isFa } = useDashLocale()
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)

  const tableRows = rows.map((b) => {
    const id = overviewNum(b.id)
    const title = String(b.title ?? b.name ?? "").trim() || `#${id}`
    const st = String(b.status ?? "")
    return (
      <ClickableRow key={id} onClick={onOpenBroadcast}>
        <TableCell className={cn("max-w-[14rem] truncate font-medium")}>
          {title}
        </TableCell>
        <TableCell className="text-start">
          <Badge variant="secondary" className="font-normal">
            {broadcastRowStatusLabel(st, t)}
          </Badge>
        </TableCell>
        <TableCell className={cn("text-muted-foreground text-xs")}>
          {b.created_at ? formatServiceExpiryLine(String(b.created_at), isFa) : "—"}
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("recentBroadcasts")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
        >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")}
        />
      ) : (
        <OverviewDataTable
        headers={[tp("colTitle"), tp("colStatus"), tp("colDate")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewPreviewGrid({
  isReseller,
  allowTab,
  recentUsers,
  recentReceipts,
  pendingUsersPreview,
  recentResellers,
  recentBroadcasts,
  onSelectTab,
  onOpenUserDetail,
  onOpenResellerWorkspace,
  onReceiptsFilterNavigate,
}: {
isReseller: boolean
  allowTab: (tab: string) => boolean
  recentUsers: DashRecord[]
  recentReceipts: DashRecord[]
  pendingUsersPreview: DashRecord[]
  recentResellers: DashRecord[]
  recentBroadcasts: DashRecord[]
  onSelectTab: (tabKey: string) => void
  onOpenUserDetail: (id: number) => void
  onOpenResellerWorkspace?: (id: number) => void
  onReceiptsFilterNavigate: (status?: string) => void
}) {
  const showUsers = allowTab("users")
  const showReceipts = allowTab("receipts")
  const showResellers =
    !isReseller && allowTab("resellers") && typeof onOpenResellerWorkspace === "function"
  const showBroadcast = allowTab("broadcast")

  if (!showUsers && !showReceipts && !showResellers && !showBroadcast) return null

  return (
    <section className="space-y-4">
      <div className="grid gap-4 lg:grid-cols-2">
        {showUsers ? (
          <OverviewRecentUsers
            rows={recentUsers}
        onViewAll={() => onSelectTab("users")}
            onOpenUser={onOpenUserDetail}
          />
        ) : null}
        {showReceipts ? (
          <OverviewRecentReceipts
            rows={recentReceipts}
        onViewAll={() => onReceiptsFilterNavigate()}
            onOpenReceipt={(row) => {
              const st = String(row.status ?? "").toLowerCase()
              onReceiptsFilterNavigate(st === "pending" || st === "processing" ? st : undefined)
            }}
          />
        ) : null}
        {showUsers ? (
          <OverviewPendingUsers
            rows={pendingUsersPreview}
        onViewAll={() => onSelectTab("users")}
            onOpenUser={onOpenUserDetail}
          />
        ) : null}
        {showResellers ? (
          <OverviewRecentResellers
            rows={recentResellers}
        onViewAll={() => onSelectTab("resellers")}
            onOpenReseller={(id) => onOpenResellerWorkspace?.(id)}
          />
        ) : null}
      </div>
      {showBroadcast && recentBroadcasts.length > 0 ? (
        <OverviewRecentBroadcasts
          rows={recentBroadcasts}
        onViewAll={() => onSelectTab("broadcast")}
          onOpenBroadcast={() => onSelectTab("broadcast")}
        />
      ) : null}
    </section>
  )
}
