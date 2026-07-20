"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { adminMutateErrorText, postAdminMutate } from "@/lib/dash-admin-mutate"

type DashRecord = Record<string, unknown>

type PlanRow = {
  id: number
  name: string
  category: string
  active: number
  inbound_id: number
  orphan?: boolean
}

type MergePreview = {
  source_panel_id: number
  target_panel_id: number
  merge_mode: "db_only" | "full_transfer"
  source_plans: PlanRow[]
  target_plans: PlanRow[]
  service_counts: Record<string, number>
  plans_in_use?: PlanRow[]
  required_plan_ids?: number[]
  suggested_plan_map: Record<string, number>
  total_services: number
}

const MAP_NONE = "__none__"

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function formatApiError(
  message: string | undefined,
  data: unknown,
  t: (key: string, values?: Record<string, string | number>) => string
): string {
  if (message === "unmapped_plans" && data && typeof data === "object") {
    const ids = (data as { unmapped_plan_ids?: unknown[] }).unmapped_plan_ids
    if (Array.isArray(ids) && ids.length > 0) {
      if (ids.some((raw) => num(raw) === 0)) return t("errUnmappedNoPlan")
      return t("errUnmappedPlans", { ids: ids.map(String).join(", ") })
    }
    return t("errUnmappedPlans", { ids: "?" })
  }
  if (message === "provider_mismatch") return t("errProviderMismatch")
  return message ?? t("executeFailed")
}

export function PanelMergeDialog({
  open,
  sourcePanel,
  panels,
  onOpenChange,
  onCompleted,
}: {
  open: boolean
  sourcePanel: DashRecord | null
  panels: DashRecord[]
  onOpenChange: (open: boolean) => void
  onCompleted?: () => void
}) {
  const t = useTranslations("panelMerge")
  const locale = useLocale()
  const isFa = locale === "fa"
  const sourceId = num(sourcePanel?.id)
  const sourceProvider = String(sourcePanel?.panel_provider ?? "xui")

  const [targetPanelId, setTargetPanelId] = useState("")
  const [preview, setPreview] = useState<MergePreview | null>(null)
  const [planMap, setPlanMap] = useState<Record<number, number>>({})
  const [loading, setLoading] = useState(false)
  const [executing, setExecuting] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [progress, setProgress] = useState<string | null>(null)
  const [ack, setAck] = useState(false)

  const candidates = useMemo(
    () =>
      panels.filter(
        (panel) =>
          num(panel.id) > 0 &&
          num(panel.id) !== sourceId &&
          String(panel.panel_provider ?? "xui") === sourceProvider
      ),
    [panels, sourceId, sourceProvider]
  )

  const applySuggested = useCallback((data: MergePreview) => {
    const next: Record<number, number> = {}
    for (const [k, v] of Object.entries(data.suggested_plan_map ?? {})) {
      const id = num(k)
      const tgt = num(v)
      if (id >= 0 && tgt > 0) next[id] = tgt
    }
    setPlanMap(next)
  }, [])

  const loadPreview = useCallback(async () => {
    const tid = num(targetPanelId)
    if (sourceId < 1 || tid < 1) {
      setPreview(null)
      return
    }
    setLoading(true)
    setErr(null)
    try {
      const res = await postAdminMutate("panel_merge_preview", {
        source_panel_id: sourceId,
        target_panel_id: tid,
      })
      if (!res.ok || !res.data || typeof res.data !== "object") {
        setErr(adminMutateErrorText(res, t("previewFailed")))
        setPreview(null)
        return
      }
      const data = res.data as MergePreview
      setPreview(data)
      applySuggested(data)
    } finally {
      setLoading(false)
    }
  }, [applySuggested, sourceId, t, targetPanelId])

  useEffect(() => {
    if (!open) {
      setTargetPanelId("")
      setPreview(null)
      setPlanMap({})
      setErr(null)
      setProgress(null)
      setAck(false)
      return
    }
  }, [open])

  useEffect(() => {
    if (!open || num(targetPanelId) < 1) return
    void loadPreview()
  }, [loadPreview, open, targetPanelId])

  const plansWithServices = useMemo((): PlanRow[] => {
    if (!preview) return []
    if (Array.isArray(preview.plans_in_use) && preview.plans_in_use.length > 0) {
      return preview.plans_in_use
    }
    const counts = preview.service_counts ?? {}
    const required = preview.required_plan_ids ?? Object.keys(counts).map((k) => num(k))
    const byId = new Map(preview.source_plans.map((p) => [p.id, p]))
    return required
      .filter((id) => num(counts[String(id)] ?? counts[id as unknown as string]) > 0)
      .map((id) => {
        const found = byId.get(id)
        if (found) return found
        return { id, name: id === 0 ? "" : `#${id}`, category: "", active: 0, inbound_id: 0, orphan: true }
      })
  }, [preview])

  const requiredPlanIds = useMemo(() => {
    if (!preview) return [] as number[]
    if (Array.isArray(preview.required_plan_ids) && preview.required_plan_ids.length > 0) {
      return preview.required_plan_ids
    }
    return plansWithServices.map((p) => p.id)
  }, [plansWithServices, preview])

  const unmappedPlans = useMemo(
    () => requiredPlanIds.filter((id) => !planMap[id] || planMap[id] < 1),
    [planMap, requiredPlanIds]
  )

  const activeTargetPlans = useMemo(() => {
    if (!preview) return [] as PlanRow[]
    return preview.target_plans.filter((p) => p.active !== 0)
  }, [preview])

  const allPlansMapped = useMemo(() => {
    if (!preview || num(preview.total_services) < 1) return true
    return unmappedPlans.length < 1
  }, [preview, unmappedPlans.length])

  const runExecute = useCallback(async () => {
    const tid = num(targetPanelId)
    if (!preview || tid < 1 || !allPlansMapped || !ack) return
    setExecuting(true)
    setProgress(null)
    setErr(null)
    try {
      let remaining = 1
      let totalOk = 0
      while (remaining > 0) {
        const r = await postAdminMutate("panel_merge_execute", {
          source_panel_id: sourceId,
          target_panel_id: tid,
          plan_map: planMap,
          deactivate_source: true,
          delete_source_after: false,
        })
        if (!r.ok && r.message !== "partial_batch" && r.message !== "partial") {
          setErr(formatApiError(r.message, r.data, t))
          return
        }
        const data = r.data as { succeeded?: number; remaining?: number; failed?: unknown[] } | undefined
        const failed = Array.isArray(data?.failed) ? data.failed.length : 0
        if (failed > 0) {
          setErr(t("partialFailed", { n: failed }))
          return
        }
        totalOk += num(data?.succeeded)
        remaining = num(data?.remaining)
        setProgress(t("progress", { ok: totalOk, remaining, failed }))
        if (remaining < 1) break
      }
      onOpenChange(false)
      onCompleted?.()
    } finally {
      setExecuting(false)
    }
  }, [ack, allPlansMapped, onCompleted, onOpenChange, planMap, preview, sourceId, t, targetPanelId])

  const busy = loading || executing
  const canRun =
    num(targetPanelId) > 0 &&
    preview !== null &&
    allPlansMapped &&
    ack &&
    !busy &&
    !(num(preview?.total_services) > 0 && activeTargetPlans.length < 1)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t("titleMerge")}</DialogTitle>
          <DialogDescription>{t("hintMerge")}</DialogDescription>
        </DialogHeader>
        <div className="max-h-[70vh] space-y-4 overflow-y-auto pe-1">
          <div className="rounded-md border bg-muted/30 p-3 text-sm">
            <p className="font-medium">
              #{sourceId || "-"} · {String(sourcePanel?.label ?? "-")}
            </p>
            {preview ? (
              <p className="mt-1 text-xs text-muted-foreground">
                {preview.merge_mode === "db_only" ? t("modeDbOnly") : t("modeFullTransfer")}
                {" · "}
                {t("serviceCount", { n: num(preview.total_services) })}
              </p>
            ) : null}
          </div>

          <div className="space-y-2">
            <Label htmlFor="panel-merge-target">{t("pickTargetPanel")}</Label>
            <select
              id="panel-merge-target"
              className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
              value={targetPanelId}
              onChange={(e) => {
                setTargetPanelId(e.target.value)
                setPreview(null)
                setErr(null)
                setAck(false)
              }}
              disabled={busy}
              dir={isFa ? "rtl" : "ltr"}
            >
              <option value="">{t("pickTargetPanel")}</option>
              {candidates.map((panel) => (
                <option key={num(panel.id)} value={num(panel.id)}>
                  #{num(panel.id)} · {String(panel.label ?? "")}
                </option>
              ))}
            </select>
            {candidates.length < 1 ? (
              <p className="text-sm text-destructive">{t("errProviderMismatch")}</p>
            ) : null}
          </div>

          {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}
          {err ? <p className="text-sm text-destructive">{err}</p> : null}
          {progress ? <p className="text-sm text-muted-foreground">{progress}</p> : null}

          {preview ? (
            <div className="space-y-2">
              <div className="flex items-center justify-between gap-2">
                <Label className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("planMap")}
                </Label>
                <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => applySuggested(preview)}>
                  {t("autoMatch")}
                </Button>
              </div>

              {num(preview.total_services) > 0 && activeTargetPlans.length < 1 ? (
                <p className="text-sm text-amber-600 dark:text-amber-400">{t("noTargetPlans")}</p>
              ) : null}

              {plansWithServices.length > 0 ? (
                <div className="space-y-2 rounded-md border p-2">
                  {plansWithServices.map((plan) => {
                    const count = num(preview.service_counts[String(plan.id)] ?? preview.service_counts[plan.id as unknown as string])
                    const planLabel = plan.id === 0 ? t("noPlanLabel") : plan.name || `#${plan.id}`
                    const mapped = planMap[plan.id]
                    return (
                      <div key={`plan-${plan.id}`} className="grid gap-1 border-b border-border/40 pb-2 last:border-b-0 last:pb-0">
                        <div className="flex flex-wrap items-baseline justify-between gap-1 text-sm">
                          <span className="font-medium">
                            {planLabel}
                            {plan.orphan && plan.id !== 0 ? (
                              <span className="ms-1 text-xs text-amber-600 dark:text-amber-400">({t("orphanPlan")})</span>
                            ) : null}
                          </span>
                          <span className="text-xs text-muted-foreground">{t("planServices", { n: count })}</span>
                        </div>
                        <select
                          className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                          value={mapped && mapped > 0 ? String(mapped) : MAP_NONE}
                          disabled={busy}
                          onChange={(e) => {
                            const n = e.target.value === MAP_NONE ? 0 : parseInt(e.target.value || "0", 10) || 0
                            setPlanMap((prev) => {
                              const next = { ...prev }
                              if (n < 1) delete next[plan.id]
                              else next[plan.id] = n
                              return next
                            })
                          }}
                        >
                          <option value={MAP_NONE}>{t("mapNone")}</option>
                          {activeTargetPlans.map((tp) => (
                            <option key={tp.id} value={tp.id}>
                              {tp.name || `#${tp.id}`}
                            </option>
                          ))}
                        </select>
                      </div>
                    )
                  })}
                </div>
              ) : num(preview.total_services) > 0 ? (
                <p className="text-sm text-muted-foreground">{t("mustMapPlans")}</p>
              ) : null}

              {unmappedPlans.length > 0 ? (
                <p className="text-xs text-amber-600 dark:text-amber-400">
                  {t("unmappedPlans", { n: unmappedPlans.length })}
                </p>
              ) : null}

              <label className="flex items-start gap-2 text-sm">
                <input
                  type="checkbox"
                  className="mt-1 size-4"
                  checked={ack}
                  onChange={(e) => setAck(e.target.checked)}
                />
                <span>{t("ackMerge")}</span>
              </label>
            </div>
          ) : null}
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" disabled={busy} onClick={() => onOpenChange(false)}>
            {t("cancel")}
          </Button>
          <Button type="button" disabled={!canRun} onClick={() => void runExecute()}>
            {executing ? t("loading") : t("runMerge")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
