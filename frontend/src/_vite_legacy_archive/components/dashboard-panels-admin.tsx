"use client"

import { EllipsisVerticalIcon } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { Badge } from "@/components/ui/badge"

const PANELS_TABLE_COLS = ["5%", "16%", "22%", "10%", "14%", "10%", "5%"]
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DashPage } from "@/components/dash-page"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import {
  Sheet,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { DashSheetContent } from "@/components/dash-sheet-content"
import { DataPagination } from "@/components/data-pagination"
import {
  DashboardPanelEconomicsSheet,
  type PanelEconomicsEntry,
} from "@/components/dashboard-panel-economics-sheet"
import { postAdminMutate, type AdminMutateResult } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

type DashRecord = Record<string, unknown>

type AuthMode = "bearer" | "cookie" | "incomplete"

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isActiveRow(r: DashRecord): boolean {
  return r.active === true || r.active === 1 || r.active === "1"
}

function panelAuthMode(r: DashRecord): AuthMode {
  const m = String(r.auth_mode ?? "")
  if (m === "bearer" || m === "cookie" || m === "incomplete") return m
  if (r.has_api_token === true || r.has_api_token === 1 || r.has_api_token === "1") return "bearer"
  return "incomplete"
}

type FormState = {
  xp_id: number
  xp_label: string
  xp_panel_url: string
  xp_panel_username: string
  xp_panel_password: string
  xp_panel_api_base: string
  xp_panel_login_secret: string
  xp_panel_api_token: string
  xp_subscription_public_base: string
  xp_sort_order: number
  xp_active: boolean
  xp_has_api_token: boolean
}

function emptyForm(): FormState {
  return {
    xp_id: 0,
    xp_label: "",
    xp_panel_url: "",
    xp_panel_username: "",
    xp_panel_password: "",
    xp_panel_api_base: "panel/api",
    xp_panel_login_secret: "",
    xp_panel_api_token: "",
    xp_subscription_public_base: "",
    xp_sort_order: 0,
    xp_active: true,
    xp_has_api_token: false,
  }
}

function formFromRow(r: DashRecord): FormState {
  const hasToken = r.has_api_token === true || r.has_api_token === 1 || r.has_api_token === "1"
  return {
    xp_id: num(r.id),
    xp_label: String(r.label ?? ""),
    xp_panel_url: String(r.panel_url ?? ""),
    xp_panel_username: String(r.panel_username ?? ""),
    xp_panel_password: "",
    xp_panel_api_base: String(r.panel_api_base ?? "panel/api"),
    xp_panel_login_secret: "",
    xp_panel_api_token: "",
    xp_subscription_public_base: String(r.subscription_public_base ?? ""),
    xp_sort_order: num(r.sort_order),
    xp_active: isActiveRow(r),
    xp_has_api_token: hasToken,
  }
}

function probeLabelKey(name: string): string {
  if (name === "server_status") return "probe_server_status"
  if (name === "inbounds_list") return "probe_inbounds_list"
  if (name === "inbounds_onlines") return "probe_inbounds_onlines"
  if (name === "clients_onlines") return "probe_clients_onlines"
  if (name === "clients_list") return "probe_clients_list"
  return name
}

function mainProbeKeys(apiFlavor: string): string[] {
  if (apiFlavor === "v3_clients") {
    return ["server_status", "inbounds_list", "clients_onlines", "clients_list"]
  }
  return ["server_status", "inbounds_list", "inbounds_onlines"]
}

function PanelTestResults({
  testRes,
  tp,
}: {
  testRes: AdminMutateResult
  tp: (k: string, opts?: Record<string, string | number>) => string
}) {
  const { isFa } = useDashLocale()
  const data = testRes.data as Record<string, unknown> | undefined
  const diag = (data?.diag ?? {}) as Record<string, unknown>
  const probes = (data?.probes ?? {}) as Record<string, Record<string, unknown>>
  const suggested = data?.suggested_base != null ? String(data.suggested_base) : ""

  const authMode = String(diag.auth_mode ?? "")
  const authLabel =
    authMode === "bearer"
      ? tp("authBearer")
      : authMode === "cookie"
        ? tp("authCookie")
        : authMode === "incomplete"
          ? tp("authIncomplete")
          : authMode

  const probeHintLabel = (hint: string) => {
    if (!hint) return "—"
    const key = `probe_${hint}`
    const translated = tp(key)
    return translated !== key ? translated : hint
  }

  const mainProbes = mainProbeKeys(String(diag.api_flavor ?? ""))

  return (
    <div className="space-y-3 text-sm">
      <p className={testRes.ok ? "text-emerald-600 dark:text-emerald-400" : "text-destructive"}>
        {testRes.ok ? tp("testOk") : testRes.message || tp("testFail")}
      </p>
      {authMode ? (
        <p className="text-muted-foreground">
          {tp("testAuthMode")}: <span className="font-medium text-foreground">{authLabel}</span>
        </p>
      ) : null}
      {diag.api_flavor ? (
        <p className="text-muted-foreground">
          {tp("testApiFlavor")}:{" "}
          <span className="font-medium text-foreground" dir="ltr">
            {String(diag.api_flavor)}
          </span>
        </p>
      ) : null}
      {suggested ? (
        <div className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs">
          <p className="font-medium">{tp("testSuggestedBase")}</p>
          <p className="mt-1 font-mono" dir="ltr">
            {suggested}
          </p>
        </div>
      ) : null}
      {Object.keys(probes).length > 0 ? (
        <div className="overflow-x-auto rounded-md border border-border">
          <table
            className={cn(
              "w-full min-w-[20rem] border-collapse text-xs [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border",
              "text-start"
            )}
          >
            <thead>
              <tr className="bg-muted/40">
                <th className="p-2 font-medium">{tp("testProbeName")}</th>
                <th className="p-2 font-medium">{tp("testProbeHttp")}</th>
                <th className="p-2 font-medium">{tp("testProbeHint")}</th>
                <th className="p-2 font-medium">{tp("testProbeMsg")}</th>
              </tr>
            </thead>
            <tbody>
              {mainProbes.map((key) => {
                const row = probes[key]
                if (!row || row.skipped) return null
                return (
                  <tr key={key}>
                    <td className="p-2">{tp(probeLabelKey(key))}</td>
                    <td className="p-2 font-mono tabular-nums" dir="ltr">
                      {num(row.http) > 0 ? formatNumber(num(row.http), isFa) : "—"}
                    </td>
                    <td className="p-2">
                      <Badge variant={row.ok ? "default" : "destructive"} className="font-normal">
                        {probeHintLabel(String(row.hint ?? ""))}
                      </Badge>
                    </td>
                    <td className="max-w-[10rem] truncate p-2 text-muted-foreground" title={String(row.msg ?? "")}>
                      {String(row.msg ?? "")}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      ) : null}
      {data != null ? (
        <details className="text-xs">
          <summary className="cursor-pointer text-muted-foreground hover:text-foreground">{tp("testRawJson")}</summary>
          <pre className="mt-2 max-h-40 overflow-auto rounded-md border border-border bg-muted/40 p-2" dir="ltr">
            {JSON.stringify(data, null, 2)}
          </pre>
        </details>
      ) : null}
    </div>
  )
}

export function DashboardPanelsAdmin({
  panels,
  pagination,
  panelEconomicsMap,
  globalEconomicsConfig,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  panels: DashRecord[]
  pagination: PaginationMeta | null
  panelEconomicsMap?: Record<string, PanelEconomicsEntry>
  globalEconomicsConfig?: {
    total_sold_volume_gb?: number
    selling_price_per_gb?: number
    volume_mode?: string
    volume_window_days?: number
  }
onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`panelsAdmin.${k}`, opts)

  const [sheetOpen, setSheetOpen] = useState(false)
  const [mode, setMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<FormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)
  const [testOpen, setTestOpen] = useState(false)
  const [testLoading, setTestLoading] = useState(false)
  const [testPanelId, setTestPanelId] = useState(0)
  const [testRes, setTestRes] = useState<AdminMutateResult | null>(null)
  const [economicsOpen, setEconomicsOpen] = useState(false)
  const [economicsPanelId, setEconomicsPanelId] = useState(0)
  const [economicsPanelLabel, setEconomicsPanelLabel] = useState("")
  const [localEconomicsMap, setLocalEconomicsMap] = useState<
    Record<string, PanelEconomicsEntry>
  >({})

  useEffect(() => {
    if (panelEconomicsMap) {
      setLocalEconomicsMap(panelEconomicsMap)
    }
  }, [panelEconomicsMap])

  useEffect(() => {
    if (typeof window === "undefined") return
    const m = window.location.search.match(/[?&]panel_costs=(\d+)/)
    const pid = m ? Number(m[1]) : 0
    if (pid < 1) return
    const row = panels.find((p) => num(p.id) === pid)
    if (row) {
      setEconomicsPanelId(pid)
      setEconomicsPanelLabel(String(row.label ?? ""))
      setEconomicsOpen(true)
    }
  }, [panels])

  const sharedLines = useMemo(
    () => localEconomicsMap["0"]?.lines ?? [],
    [localEconomicsMap]
  )

  const siteVolumeGb = useMemo(
    () => Math.max(0, Number(globalEconomicsConfig?.total_sold_volume_gb) || 0),
    [globalEconomicsConfig]
  )

  const economicsEntry = useMemo(() => {
    if (economicsPanelId < 1) return localEconomicsMap["0"]
    return localEconomicsMap[String(economicsPanelId)]
  }, [localEconomicsMap, economicsPanelId])

  const openEconomics = (r: DashRecord) => {
    setEconomicsPanelId(num(r.id))
    setEconomicsPanelLabel(String(r.label ?? ""))
    setEconomicsOpen(true)
  }

  const authBadge = (mode: AuthMode) => {
    if (mode === "bearer") return <Badge variant="default">{tp("authBearer")}</Badge>
    if (mode === "cookie") return <Badge variant="secondary">{tp("authCookie")}</Badge>
    return <Badge variant="outline">{tp("authIncomplete")}</Badge>
  }

  const apiFlavorBadge = (flavor: string) => {
    if (flavor === "v3_clients") return <Badge variant="secondary">{tp("apiFlavorV3")}</Badge>
    if (flavor === "legacy_inbound") return <Badge variant="outline">{tp("apiFlavorLegacy")}</Badge>
    return null
  }

  const run = useCallback(
    async (params: Record<string, unknown>) => {
      setSaving(true)
      setError(null)
      try {
        const res = await postAdminMutate("panel_xp", params)
        if (!res.ok) {
          setError(res.code || res.message || tp("mutateError"))
          return
        }
        setSheetOpen(false)
        setDeleteTarget(null)
        onMutateSuccess?.()
      } finally {
        setSaving(false)
      }
    },
    [onMutateSuccess, tp]
  )

  const runPanelTest = useCallback(async (panelId: number) => {
    setTestPanelId(panelId)
    setTestOpen(true)
    setTestLoading(true)
    setTestRes(null)
    try {
      const res = await postAdminMutate("panel_test", { panel_id: panelId })
      setTestRes(res)
    } finally {
      setTestLoading(false)
    }
  }, [])

  const openAdd = () => {
    setError(null)
    setMode("add")
    setForm(emptyForm())
    setSheetOpen(true)
  }

  const openEdit = (r: DashRecord) => {
    setError(null)
    setMode("edit")
    setForm(formFromRow(r))
    setSheetOpen(true)
  }

  const onSave = () => {
    const base = {
      xp_label: form.xp_label.trim(),
      xp_panel_url: form.xp_panel_url.trim(),
      xp_panel_username: form.xp_panel_username.trim(),
      xp_panel_api_base: form.xp_panel_api_base.trim() || "panel/api",
      xp_panel_login_secret: form.xp_panel_login_secret.trim(),
      xp_panel_api_token: form.xp_panel_api_token.trim(),
      xp_subscription_public_base: form.xp_subscription_public_base.trim(),
      xp_sort_order: form.xp_sort_order,
      xp_active: form.xp_active ? 1 : 0,
    }
    if (mode === "add") {
      void run({
        xp_action: "add",
        ...base,
        xp_panel_password: form.xp_panel_password,
        xp_panel_api_token: form.xp_panel_api_token,
      })
      return
    }
    const payload: Record<string, unknown> = {
      xp_action: "update",
      xp_id: form.xp_id,
      ...base,
    }
    if (form.xp_panel_password.trim() !== "") {
      payload.xp_panel_password = form.xp_panel_password
    }
    if (form.xp_panel_api_token.trim() !== "") {
      payload.xp_panel_api_token = form.xp_panel_api_token
    }
    void run(payload)
  }

  const tokenFilled = form.xp_panel_api_token.trim() !== ""
  const classicAuthClass = cn(tokenFilled && "opacity-60")

  return (
    <DashPage>
      <DashboardPageHeader
        title={tp("title")}
        description={tp("subtitle")}
        actions={
          <Button type="button" size="sm" onClick={openAdd}>
            {tp("add")}
          </Button>
        }
      />

      {error ? (
        <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </div>
      ) : null}

      {panels.length === 0 ? (
        <p className="text-sm text-muted-foreground">{tp("empty")}</p>
      ) : (
        <DashTableShell
        minWidth="40rem" colWidths={PANELS_TABLE_COLS}>
          <thead>
            <tr className="bg-muted/40">
              <DashTh>#</DashTh>
              <DashTh>{tp("colLabel")}</DashTh>
              <DashTh>{tp("colUrl")}</DashTh>
              <DashTh>{tp("colAuth")}</DashTh>
              <DashTh>{tp("colApiBase")}</DashTh>
              <DashTh>{tp("colActive")}</DashTh>
              <DashTh />
            </tr>
          </thead>
          <tbody>
            {panels.map((r) => {
              const id = num(r.id)
              const act = isActiveRow(r)
              const auth = panelAuthMode(r)
              return (
                <tr key={id}>
                  <DashTd className="font-mono text-xs tabular-nums">{formatNumber(id, isFa)}</DashTd>
                  <DashTd className="truncate">{String(r.label ?? "")}</DashTd>
                  <DashTd className="break-all text-xs">{String(r.panel_url ?? "—")}</DashTd>
                  <DashTd>
                    <div className="flex flex-wrap gap-1">
                      {authBadge(auth)}
                      {apiFlavorBadge(String(r.panel_api_flavor ?? ""))}
                    </div>
                  </DashTd>
                  <DashTd dir="ltr" className="truncate font-mono text-xs">
                    {String(r.panel_api_base ?? "panel/api")}
                  </DashTd>
                  <DashTd>
                    <Badge variant={act ? "default" : "secondary"}>
                      {act ? tp("statusActive") : tp("statusInactive")}
                    </Badge>
                  </DashTd>
                  <DashTd>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button type="button" variant="ghost" size="icon" className="h-8 w-8">
                          <EllipsisVerticalIcon className="size-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align={isFa ? "start" : "end"}>
                        <DropdownMenuItem onClick={() => void run({ xp_action: "toggle", xp_id: id })}>
                          {act ? tp("toggleDeactivate") : tp("toggleActivate")}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => void runPanelTest(id)}>
                          {tp("testConnection")}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openEdit(r)}>{tp("edit")}</DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openEconomics(r)}>
                          {t("panelEconomics.menuItem")}
                        </DropdownMenuItem>
                        <DropdownMenuItem className="text-destructive" onClick={() => setDeleteTarget(r)}>
                          {tp("delete")}
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </DashTd>
                </tr>
              )
            })}
          </tbody>
        </DashTableShell>
      )}

      <p className="text-xs text-muted-foreground">{tp("passwordHint")}</p>

      <DataPagination
        meta={pagination}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
      />

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <DashSheetContent className={cn("flex w-full flex-col sm:max-w-lg")}>
          <SheetHeader>
            <SheetTitle>{mode === "add" ? tp("sheetAdd") : tp("sheetEdit")}</SheetTitle>
          </SheetHeader>
          <div className="flex-1 space-y-4 overflow-y-auto px-4 pb-4">
            <div className="space-y-3">
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{tp("sectionGeneral")}</p>
              <div className="space-y-2">
                <Label>{tp("fieldLabel")}</Label>
                <Input value={form.xp_label} onChange={(e) => setForm((f) => ({ ...f, xp_label: e.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label>{tp("fieldUrl")}</Label>
                <Input value={form.xp_panel_url} onChange={(e) => setForm((f) => ({ ...f, xp_panel_url: e.target.value }))} />
                <p className="text-xs text-muted-foreground">{tp("urlWebBaseHint")}</p>
              </div>
            </div>

            <Separator />

            <div className="space-y-3">
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{tp("sectionAuth")}</p>
              <p className="text-xs text-muted-foreground">{tp("authEitherHint")}</p>
              <div className="space-y-2">
                <Label>{tp("fieldApiToken")}</Label>
                <Input
                  type="password"
                  autoComplete="off"
                  value={form.xp_panel_api_token}
                  onChange={(e) => setForm((f) => ({ ...f, xp_panel_api_token: e.target.value }))}
                  placeholder={mode === "edit" ? tp("apiTokenKeep") : ""}
                />
                <p className="text-xs text-muted-foreground">{tp("apiTokenHint")}</p>
                {mode === "edit" && form.xp_has_api_token && !tokenFilled ? (
                  <p className="text-xs text-emerald-600 dark:text-emerald-400">{tp("tokenConfigured")}</p>
                ) : null}
              </div>
              <div className={cn("space-y-3 rounded-md border border-border/60 p-3", classicAuthClass)}>
                <div className="space-y-2">
                  <Label>{tp("fieldUser")}</Label>
                  <Input
                    value={form.xp_panel_username}
                    onChange={(e) => setForm((f) => ({ ...f, xp_panel_username: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{tp("fieldPassword")}</Label>
                  <Input
                    type="password"
                    autoComplete="off"
                    value={form.xp_panel_password}
                    onChange={(e) => setForm((f) => ({ ...f, xp_panel_password: e.target.value }))}
                    placeholder={mode === "edit" ? tp("passwordKeep") : ""}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{tp("fieldLoginSecret")}</Label>
                  <Input
                    type="password"
                    autoComplete="off"
                    value={form.xp_panel_login_secret}
                    onChange={(e) => setForm((f) => ({ ...f, xp_panel_login_secret: e.target.value }))}
                  />
                </div>
              </div>
            </div>

            <Separator />

            <div className="space-y-3">
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{tp("sectionAdvanced")}</p>
              <div className="space-y-2">
                <Label>{tp("fieldApiBase")}</Label>
                <Input
                  value={form.xp_panel_api_base}
                  onChange={(e) => setForm((f) => ({ ...f, xp_panel_api_base: e.target.value }))}
                  dir="ltr"
                />
              </div>
              <div className="space-y-2">
                <Label>{tp("fieldSubBase")}</Label>
                <Input
                  value={form.xp_subscription_public_base}
                  onChange={(e) => setForm((f) => ({ ...f, xp_subscription_public_base: e.target.value }))}
                  dir="ltr"
                />
              </div>
              <div className="space-y-2">
                <Label>{tp("fieldSort")}</Label>
                <Input
                  type="number"
                  value={form.xp_sort_order}
                  onChange={(e) => setForm((f) => ({ ...f, xp_sort_order: num(e.target.value) }))}
                />
              </div>
              <label className={cn("flex items-center gap-2 text-sm")}>
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.xp_active}
                  onChange={(e) => setForm((f) => ({ ...f, xp_active: e.target.checked }))}
                />
                {tp("fieldActive")}
              </label>
            </div>
          </div>
          <SheetFooter className="flex-row gap-2 border-t p-4">
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>
              {tp("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void onSave()}>
              {tp("save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

      <Dialog open={testOpen} onOpenChange={setTestOpen}>
        <DashDialogContent className={cn("max-w-lg")}>
          <DashDialogHeader>
            <DialogTitle>{tp("testDialogTitle", { id: formatNumber(testPanelId, isFa) })}</DialogTitle>
            <DialogDescription>{tp("testDialogDesc")}</DialogDescription>
          </DashDialogHeader>
          {testLoading ? (
            <p className="text-sm text-muted-foreground">{tp("testRunning")}</p>
          ) : testRes ? (
            <PanelTestResults testRes={testRes} tp={tp}
        />
          ) : null}
          <DashDialogFooter>
            <Button type="button" variant="outline" onClick={() => setTestOpen(false)}>
              {tp("cancel")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={Boolean(deleteTarget)} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DashDialogContent className={cn()}>
          <DashDialogHeader>
            <DialogTitle>{tp("deleteTitle")}</DialogTitle>
            <DialogDescription>{tp("deleteDesc")}</DialogDescription>
          </DashDialogHeader>
          <DashDialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
              {tp("cancel")}
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={saving}
              onClick={() => deleteTarget && void run({ xp_action: "delete", xp_id: num(deleteTarget.id) })}
            >
              {tp("delete")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <DashboardPanelEconomicsSheet
        open={economicsOpen}
        onOpenChange={setEconomicsOpen}
        panelId={economicsPanelId}
        panelLabel={economicsPanelLabel}
        entry={economicsEntry}
        globalConfig={globalEconomicsConfig ?? {}}
        sharedLines={sharedLines}
        siteVolumeGb={siteVolumeGb}
        onSaved={({ panelId, entry }) => {
          setLocalEconomicsMap((m) => ({ ...m, [String(panelId)]: entry }))
          onMutateSuccess?.()
        }}
      />
    </DashPage>
  )
}
