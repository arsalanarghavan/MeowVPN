"use client"

import { useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import { Sheet, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { DashSheetContent } from "@/components/dash-sheet-content"
import { DashSelect } from "@/components/dash-select"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const x = Number(v)
  return Number.isFinite(x) ? x : 0
}

type TierForm = {
  sort_order: number
  price_per_gb: string
  min_total_gb: string
  min_total_toman: string
}

function emptyTiers(): TierForm[] {
  return [
    { sort_order: 0, price_per_gb: "", min_total_gb: "0", min_total_toman: "0" },
    { sort_order: 1, price_per_gb: "", min_total_gb: "0", min_total_toman: "" },
  ]
}

export function DashboardWholesaleLinesAdmin({
  catalog,
  panels,
  l2tpServers = [],
  onMutateSuccess,
}: {
  catalog: DashRecord[]
  panels: DashRecord[]
  l2tpServers?: DashRecord[]
  onMutateSuccess?: () => void
}) {
  const t = useTranslations("wholesaleLinesAdmin")
  const locale = useLocale()
  const isFa = locale === "fa"
  const l2tpEnabled = l2tpServers.length > 0

  const [sheetOpen, setSheetOpen] = useState(false)
  const [editingId, setEditingId] = useState(0)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
  const [label, setLabel] = useState("")
  const [badgeColor, setBadgeColor] = useState("#6366f1")
  const [panelId, setPanelId] = useState(() => num(panels[0]?.id) || 1)
  const [serviceType, setServiceType] = useState<"xray" | "l2tp">("xray")
  const [inboundId, setInboundId] = useState("")
  const [l2tpServerId, setL2tpServerId] = useState(0)
  const [sortOrder, setSortOrder] = useState(0)
  const [active, setActive] = useState(true)
  const [tiers, setTiers] = useState<TierForm[]>(emptyTiers)

  const sortedCatalog = useMemo(() => {
    return [...catalog].sort((a, b) => num(a.sort_order) - num(b.sort_order) || num(a.id) - num(b.id))
  }, [catalog])

  function openAdd() {
    setEditingId(0)
    setErr("")
    setLabel("")
    setBadgeColor("#6366f1")
    setPanelId(num(panels[0]?.id) || 1)
    setServiceType("xray")
    setInboundId("")
    setL2tpServerId(0)
    setSortOrder(0)
    setActive(true)
    setTiers(emptyTiers())
    setSheetOpen(true)
  }

  function openEdit(row: DashRecord) {
    setEditingId(num(row.id))
    setErr("")
    setLabel(String(row.label ?? ""))
    setBadgeColor(String(row.badge_color ?? "#6366f1"))
    setPanelId(num(row.panel_id) || 1)
    setServiceType(row.default_service_type === "l2tp" ? "l2tp" : "xray")
    setInboundId(String(num(row.default_inbound_id)))
    setL2tpServerId(num(row.default_l2tp_server_id))
    setSortOrder(num(row.sort_order))
    setActive(row.active === true || row.active === 1 || row.active === "1")
    const tr = Array.isArray(row.tiers) ? (row.tiers as DashRecord[]) : []
    setTiers(
      tr.length
        ? tr.map((x, i) => ({
            sort_order: num(x.sort_order) || i,
            price_per_gb: String(num(x.price_per_gb) || ""),
            min_total_gb: String(num(x.min_total_gb) || 0),
            min_total_toman: String(num(x.min_total_toman) || 0),
          }))
        : emptyTiers()
    )
    setSheetOpen(true)
  }

  async function saveLine() {
    setBusy(true)
    setErr("")
    try {
      const tierPayload = tiers.map((x, i) => ({
        sort_order: Number.isFinite(Number(x.sort_order)) ? Number(x.sort_order) : i,
        price_per_gb: Math.max(0, parseFloat(String(x.price_per_gb).replace(",", ".")) || 0),
        min_total_gb: Math.max(0, parseInt(String(x.min_total_gb).trim(), 10) || 0),
        min_total_toman: Math.max(0, parseFloat(String(x.min_total_toman).replace(",", ".")) || 0),
      }))
      const res = await postAdminMutate("wholesale_line_save", {
        line_id: editingId,
        label: label.trim(),
        badge_color: badgeColor.trim(),
        panel_id: panelId,
        default_service_type: l2tpEnabled ? serviceType : "xray",
        default_inbound_id: Math.max(0, parseInt(inboundId.trim(), 10) || 0),
        default_l2tp_server_id: l2tpEnabled && serviceType === "l2tp" ? l2tpServerId : 0,
        sort_order: sortOrder,
        active: active ? 1 : 0,
        tiers: tierPayload,
      })
      if (!res.ok) {
        setErr(res.message || res.code || t("saveError"))
        return
      }
      setSheetOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function deleteLine(id: number) {
    if (!id || !window.confirm(t("confirmDelete"))) return
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("wholesale_line_delete", { line_id: id })
      if (!res.ok) {
        setErr(res.message || t("saveError"))
        return
      }
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className={cn("space-y-6")}>
      <div className={cn("flex flex-wrap items-center justify-between gap-3")}>
        <div>
          <h3 className="text-lg font-semibold tracking-tight">{t("title")}</h3>
          <p className="mt-1 text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <Button type="button" onClick={openAdd}>
          {t("addLine")}
        </Button>
      </div>

      {err ? (
        <p className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {err}
        </p>
      ) : null}

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {sortedCatalog.length === 0 ? (
          <Card className="md:col-span-2 xl:col-span-3">
            <CardContent className="py-10 text-center text-sm text-muted-foreground">{t("empty")}</CardContent>
          </Card>
        ) : (
          sortedCatalog.map((row) => {
            const id = num(row.id)
            const tiersList = Array.isArray(row.tiers) ? (row.tiers as DashRecord[]) : []
            return (
              <Card key={id || String(row.label)} className="overflow-hidden">
                <CardHeader className="pb-2">
                  <div
                    className="h-2 w-full rounded-full"
                    style={{ backgroundColor: String(row.badge_color || "#6366f1") }}
                  />
                  <CardTitle className="text-base">{String(row.label ?? "—")}</CardTitle>
                  <CardDescription>
                    #{id} · {t("panel")} #{num(row.panel_id)} · {String(row.default_service_type ?? "xray")}
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                  <div className="space-y-1 rounded-md border bg-muted/30 px-2 py-2">
                    <p className="text-xs font-medium text-muted-foreground">{t("tiers")}</p>
                    <ul className="space-y-1 text-xs">
                      {tiersList.map((tr) => (
                        <li key={String(tr.id ?? tr.sort_order)}>
                          <span className="font-medium tabular-nums">{formatNumber(num(tr.price_per_gb), isFa)}</span>
                          {" / "}
                          {t("gb")} · min {formatNumber(num(tr.min_total_gb), isFa)} {t("gbShort")} ·{" "}
                          {formatNumber(num(tr.min_total_toman), isFa)} {t("toman")}
                        </li>
                      ))}
                    </ul>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="secondary" size="sm" onClick={() => openEdit(row)}>
                      {t("edit")}
                    </Button>
                    <Button
                      type="button"
                      variant="destructive"
                      size="sm"
                      disabled={busy}
                      onClick={() => void deleteLine(id)}
                    >
                      {t("delete")}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            )
          })
        )}
      </div>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <DashSheetContent side={isFa ? "left" : "right"} className="w-full overflow-y-auto sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>{editingId ? t("editLine") : t("addLine")}</SheetTitle>
          </SheetHeader>
          <div className="flex flex-col gap-4 px-4 pb-4">
            <div className="space-y-2">
              <Label>{t("label")}</Label>
              <Input value={label} onChange={(e) => setLabel(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t("badgeColor")}</Label>
              <Input
                type="color"
                className="h-10 w-24 p-1"
                value={badgeColor}
                onChange={(e) => setBadgeColor(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("panel")}</Label>
              <DashSelect
                value={String(panelId)}
                onValueChange={(v) => setPanelId(num(v))}
                options={panels.map((p) => ({
                  value: String(p.id),
                  label: `#${num(p.id)} ${String(p.label ?? p.name ?? "")}`,
                }))}
              />
            </div>
            {l2tpEnabled ? (
              <div className="space-y-2">
                <Label>{t("serviceType")}</Label>
                <DashSelect
                  value={serviceType}
                  onValueChange={(v) => setServiceType(v === "l2tp" ? "l2tp" : "xray")}
                  options={[
                    { value: "xray", label: "Xray" },
                    { value: "l2tp", label: "L2TP" },
                  ]}
                />
              </div>
            ) : null}
            {serviceType === "xray" ? (
              <div className="space-y-2">
                <Label>{t("inboundId")}</Label>
                <Input value={inboundId} onChange={(e) => setInboundId(e.target.value)} dir="ltr" className="font-mono" />
              </div>
            ) : (
              <div className="space-y-2">
                <Label>{t("l2tpServer")}</Label>
                <DashSelect
                  value={String(l2tpServerId)}
                  onValueChange={(v) => setL2tpServerId(num(v))}
                  options={[
                    { value: "0", label: "—" },
                    ...l2tpServers.map((s) => ({
                      value: String(s.id),
                      label: String(s.name ?? s.label ?? s.id),
                    })),
                  ]}
                />
              </div>
            )}
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label>{t("sortOrder")}</Label>
                <Input type="number" value={sortOrder} onChange={(e) => setSortOrder(num(e.target.value))} />
              </div>
              <div className="flex items-end gap-2 pb-2">
                <input type="checkbox" id="wl-active" checked={active} onChange={(e) => setActive(e.target.checked)} />
                <Label htmlFor="wl-active">{t("active")}</Label>
              </div>
            </div>
            <Separator />
            <div className="space-y-2">
              <div className="flex items-center justify-between gap-2">
                <Label>{t("tiers")}</Label>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() =>
                    setTiers((prev) => [
                      ...prev,
                      {
                        sort_order: prev.length,
                        price_per_gb: "",
                        min_total_gb: "0",
                        min_total_toman: "0",
                      },
                    ])
                  }
                >
                  {t("addTier")}
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">{t("tiersHint")}</p>
              <div className="space-y-3">
                {tiers.map((ti, idx) => (
                  <div key={idx} className="space-y-2 rounded-md border p-3">
                    <div className="grid grid-cols-2 gap-2">
                      <div className="space-y-1">
                        <Label className="text-xs">{t("tierSort")}</Label>
                        <Input
                          type="number"
                          value={ti.sort_order}
                          onChange={(e) =>
                            setTiers((prev) =>
                              prev.map((x, i) => (i === idx ? { ...x, sort_order: num(e.target.value) } : x))
                            )
                          }
                        />
                      </div>
                      <div className="space-y-1">
                        <Label className="text-xs">{t("pricePerGb")}</Label>
                        <Input
                          value={ti.price_per_gb}
                          onChange={(e) =>
                            setTiers((prev) =>
                              prev.map((x, i) => (i === idx ? { ...x, price_per_gb: e.target.value } : x))
                            )
                          }
                          dir="ltr"
                        />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                      <div className="space-y-1">
                        <Label className="text-xs">{t("minTotalGb")}</Label>
                        <Input
                          value={ti.min_total_gb}
                          onChange={(e) =>
                            setTiers((prev) =>
                              prev.map((x, i) => (i === idx ? { ...x, min_total_gb: e.target.value } : x))
                            )
                          }
                          dir="ltr"
                        />
                      </div>
                      <div className="space-y-1">
                        <Label className="text-xs">{t("minTotalToman")}</Label>
                        <Input
                          value={ti.min_total_toman}
                          onChange={(e) =>
                            setTiers((prev) =>
                              prev.map((x, i) => (i === idx ? { ...x, min_total_toman: e.target.value } : x))
                            )
                          }
                          dir="ltr"
                        />
                      </div>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="text-destructive"
                      onClick={() => setTiers((prev) => prev.filter((_, i) => i !== idx))}
                    >
                      {t("removeTier")}
                    </Button>
                  </div>
                ))}
              </div>
            </div>
          </div>
          <SheetFooter className="flex-row gap-2 border-t px-4 py-3">
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void saveLine()}>
              {busy ? "…" : t("save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>
    </div>
  )
}
