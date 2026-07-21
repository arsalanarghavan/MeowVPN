"use client"

import Link from "next/link"
import type { ReactNode } from "react"
import { useTranslations } from "next-intl"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { overviewAccentOutlineBtn } from "@/lib/chart-accent"
import { receiptSelectedService } from "@/lib/format-receipt"
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
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

function OverviewSectionCard({
  title,
  description,
  viewAllHref,
  viewAllLabel,
  children,
  className,
}: {
  title: string
  description?: string
  viewAllHref: string
  viewAllLabel: string
  children: ReactNode
  className?: string
}) {
  return (
    <Card className={cn("overflow-hidden border-border/80 shadow-sm", className)}>
      <CardHeader className="border-b border-border/60 bg-muted/20 pb-3">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0 space-y-0.5">
            <CardTitle className="text-base">{title}</CardTitle>
            {description ? <CardDescription className="text-pretty">{description}</CardDescription> : null}
          </div>
          <Button
            render={<Link href={viewAllHref} />}
            type="button"
            variant="outline"
            size="sm"
            className={cn("shrink-0", overviewAccentOutlineBtn)}
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
  return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{message}</p>
}

function OverviewDataTable({ headers, rows }: { headers: string[]; rows: ReactNode[] }) {
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

function ClickableRow({ href, children }: { href: string; children: ReactNode }) {
  return (
    <TableRow className="cursor-pointer hover:bg-primary/5">
      <Link href={href} className="contents">
        {children}
      </Link>
    </TableRow>
  )
}

export function OverviewPreviewGrid({
  dashboardBaseUrl,
  allowTab,
  recentUsers,
  recentReceipts,
  pendingUsersPreview,
  recentResellers,
  recentBroadcasts,
  isReseller = false,
}: {
  dashboardBaseUrl: string
  allowTab: (tab: string) => boolean
  recentUsers: DashRecord[]
  recentReceipts: DashRecord[]
  pendingUsersPreview: DashRecord[]
  recentResellers: DashRecord[]
  recentBroadcasts: DashRecord[]
  isReseller?: boolean
}) {
  const t = useTranslations("dashboardOverview")
  const tUsers = useTranslations("usersAdmin")
  const tReceipts = useTranslations("receiptsAdmin")
  const tBroadcast = useTranslations("broadcastAdmin")
  const { isFa } = useDashLocale()
  const base = dashboardBaseUrl.replace(/\/?$/, "")

  const showUsers = allowTab("users")
  const showReceipts = allowTab("receipts") || allowTab("payments")
  const showResellers = !isReseller && allowTab("resellers")
  const showBroadcast = allowTab("broadcast")

  if (!showUsers && !showReceipts && !showResellers && !showBroadcast) return null

  const userStatusLabel = (st: string) => {
    const key = `status_${st}` as "status_pending"
    const translated = tUsers(key)
    return translated !== key ? translated : st || "—"
  }

  const receiptStatusLabel = (st: string) => {
    if (st === "pending") return tReceipts("statusPending")
    if (st === "processing") return tReceipts("statusProcessing")
    if (st === "approved") return tReceipts("statusApproved")
    if (st === "rejected") return tReceipts("statusRejected")
    return st || "—"
  }

  const broadcastStatusLabel = (st: string) => {
    const key = `status_${st}` as "status_draft"
    const translated = tBroadcast(key)
    return translated !== key ? translated : st || "—"
  }

  const userRows = recentUsers.slice(0, 8).map((u) => {
    const id = overviewNum(u.id)
    const st = String(u.status ?? "")
    return (
      <ClickableRow key={id} href={`${base}/users/u/${id}/`}>
        <TableCell className="font-medium">{userDisplayLabel(u)}</TableCell>
        <TableCell className="text-start">
          <Badge variant={userStatusBadgeVariant(st)} className="font-normal">
            {userStatusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell className="text-muted-foreground text-xs tabular-nums">
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(u.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  const receiptRows = recentReceipts.slice(0, 8).map((r) => {
    const id = overviewNum(r.id)
    const st = String(r.status ?? "").toLowerCase()
    const label = String(r.user_label ?? r.user_name ?? "").trim() || userDisplayLabel(r)
    const amt = receiptAmount(r)
    const paymentsHref =
      st === "pending" || st === "processing"
        ? `${base}/payments/?payments_view=receipts&receipts_status=${st}`
        : `${base}/payments/?payments_view=receipts`
    return (
      <ClickableRow key={id} href={paymentsHref}>
        <TableCell className="max-w-[10rem] truncate font-medium">{label}</TableCell>
        <TableCell className="max-w-[10rem] truncate text-sm">{receiptSelectedService(r)}</TableCell>
        <TableCell className="tabular-nums">
          <span dir="ltr" className="inline-block">
            {formatOverviewAmount(amt, isFa, tReceipts("amountFree"))}
          </span>
        </TableCell>
        <TableCell className="text-start">
          <Badge variant={receiptStatusBadgeVariant(st)} className="font-normal">
            {receiptStatusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell className="text-muted-foreground text-xs tabular-nums">
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(r.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  const pendingRows = pendingUsersPreview.slice(0, 8).map((u) => {
    const id = overviewNum(u.id)
    return (
      <ClickableRow key={id} href={`${base}/users/u/${id}/`}>
        <TableCell className="font-medium">{userDisplayLabel(u)}</TableCell>
        <TableCell className="text-muted-foreground text-xs tabular-nums">
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(u.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  const resellerRows = recentResellers.slice(0, 8).map((u) => {
    const id = overviewNum(u.id)
    const st = String(u.status ?? "")
    return (
      <ClickableRow key={id} href={`${base}/reseller_workspace/${id}`}>
        <TableCell className="font-medium">{userDisplayLabel(u)}</TableCell>
        <TableCell className="text-start">
          <Badge variant={userStatusBadgeVariant(st)} className="font-normal">
            {userStatusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell className="tabular-nums">
          <span dir="ltr" className="inline-block">
            {formatOverviewAmount(overviewNum(u.svc_count), isFa, "0")}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  const broadcastRows = recentBroadcasts.slice(0, 5).map((b) => {
    const id = overviewNum(b.id)
    const st = String(b.status ?? "")
    return (
      <ClickableRow key={id} href={`${base}/broadcast/`}>
        <TableCell className="font-medium">{String(b.title ?? b.subject ?? `#${id}`)}</TableCell>
        <TableCell className="text-start">
          <Badge variant="secondary" className="font-normal">
            {broadcastStatusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell className="text-muted-foreground text-xs">
          {formatOverviewDate(b.created_at, isFa)}
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <section className="space-y-4">
      <div className="grid gap-4 lg:grid-cols-2">
        {showUsers ? (
          <OverviewSectionCard
            title={t("recentUsers")}
            viewAllHref={`${base}/users/`}
            viewAllLabel={t("viewAll")}
          >
            {userRows.length === 0 ? (
              <OverviewEmpty message={t("emptyPreview")} />
            ) : (
              <OverviewDataTable headers={[t("colUser"), t("colStatus"), t("colDate")]} rows={userRows} />
            )}
          </OverviewSectionCard>
        ) : null}

        {showReceipts ? (
          <OverviewSectionCard
            title={t("recentReceipts")}
            viewAllHref={`${base}/payments/?payments_view=receipts`}
            viewAllLabel={t("viewAll")}
          >
            {receiptRows.length === 0 ? (
              <OverviewEmpty message={t("emptyPreview")} />
            ) : (
              <OverviewDataTable
                headers={[t("colUser"), tReceipts("colSelectedService"), t("colAmount"), t("colStatus"), t("colDate")]}
                rows={receiptRows}
              />
            )}
          </OverviewSectionCard>
        ) : null}

        {showUsers ? (
          <OverviewSectionCard
            title={t("pendingApprovals")}
            description={t("pendingApprovalsHint")}
            viewAllHref={`${base}/users/`}
            viewAllLabel={t("viewAll")}
          >
            {pendingRows.length === 0 ? (
              <OverviewEmpty message={t("emptyPreview")} />
            ) : (
              <OverviewDataTable headers={[t("colUser"), t("colDate")]} rows={pendingRows} />
            )}
          </OverviewSectionCard>
        ) : null}

        {showResellers ? (
          <OverviewSectionCard
            title={t("recentResellers")}
            viewAllHref={`${base}/resellers/`}
            viewAllLabel={t("viewAll")}
          >
            {resellerRows.length === 0 ? (
              <OverviewEmpty message={t("emptyPreview")} />
            ) : (
              <OverviewDataTable
                headers={[t("colUser"), t("colStatus"), t("colServices")]}
                rows={resellerRows}
              />
            )}
          </OverviewSectionCard>
        ) : null}
      </div>

      {showBroadcast && broadcastRows.length > 0 ? (
        <OverviewSectionCard
          title={t("recentBroadcasts")}
          viewAllHref={`${base}/broadcast/`}
          viewAllLabel={t("viewAll")}
        >
          <OverviewDataTable headers={[t("colTitle"), t("colStatus"), t("colDate")]} rows={broadcastRows} />
        </OverviewSectionCard>
      ) : null}
    </section>
  )
}
