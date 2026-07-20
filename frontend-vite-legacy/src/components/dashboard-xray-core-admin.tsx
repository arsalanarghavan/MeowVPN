"use client"

import { useCallback, useState } from "react"
import { useTranslation } from "react-i18next"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Sheet, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { DashSheetContent } from "@/components/dash-sheet-content"
import { postAdminMutate } from "@/lib/dash-admin-mutate"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

type Props = {
  nodes: DashRecord[]
  onMutateSuccess?: () => void
}

export function DashboardXrayCoreAdmin({ nodes, onMutateSuccess }: Props) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({
    edit_id: 0,
    label: "",
    public_ip: "",
    agent_url: "",
    active: true,
    is_primary: false,
  })
  const [busy, setBusy] = useState(false)

  const openNew = () => {
    setForm({ edit_id: 0, label: "", public_ip: "", agent_url: "", active: true, is_primary: false })
    setOpen(true)
  }

  const openEdit = (r: DashRecord) => {
    setForm({
      edit_id: num(r.id),
      label: String(r.label ?? ""),
      public_ip: String(r.public_ip ?? ""),
      agent_url: String(r.agent_url ?? ""),
      active: r.active === true || r.active === 1,
      is_primary: r.is_primary === true || r.is_primary === 1,
    })
    setOpen(true)
  }

  const save = useCallback(async () => {
    setBusy(true)
    try {
      const op = form.edit_id > 0 ? "xray_node_update" : "xray_node_add"
      await postAdminMutate(op, { ...form, id: form.edit_id || undefined })
      setOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }, [form, onMutateSuccess])

  const action = async (op: string, id: number) => {
    setBusy(true)
    try {
      await postAdminMutate(op, { id, node_id: id })
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  return (
    <DashPage>
      <DashboardPageHeader
        title={t("sidebar.tabs.xray_core", { defaultValue: "Xray Core" })}
        description={t("xray.coreDesc", { defaultValue: "Native Xray node — status, apply config, restart." })}
        actions={<Button onClick={openNew}>{t("common.add", { defaultValue: "Add" })}</Button>}
      />
      <div className="space-y-3">
        {nodes.map((n) => (
          <div key={num(n.id)} className="flex flex-wrap items-center gap-2 rounded-lg border p-4">
            <div className="min-w-0 flex-1">
              <div className="font-medium">{String(n.label ?? `#${n.id}`)}</div>
              <div className="text-sm text-muted-foreground">{String(n.agent_url ?? "")}</div>
              <div className="text-xs text-muted-foreground">{String(n.public_ip ?? "")}</div>
            </div>
            <Badge variant={n.active ? "default" : "secondary"}>{n.active ? "active" : "off"}</Badge>
            <Badge variant="outline">{String(n.last_health_status ?? "unknown")}</Badge>
            <Button size="sm" variant="outline" disabled={busy} onClick={() => openEdit(n)}>
              {t("common.edit", { defaultValue: "Edit" })}
            </Button>
            <Button size="sm" variant="outline" disabled={busy} onClick={() => action("xray_node_health", num(n.id))}>
              Health
            </Button>
            <Button size="sm" variant="outline" disabled={busy} onClick={() => action("xray_node_apply", num(n.id))}>
              Apply
            </Button>
            <Button size="sm" disabled={busy} onClick={() => action("xray_node_restart", num(n.id))}>
              Restart
            </Button>
          </div>
        ))}
        {nodes.length === 0 && (
          <p className="text-sm text-muted-foreground">{t("xray.noNodes", { defaultValue: "No Xray nodes yet." })}</p>
        )}
      </div>
      <Sheet open={open} onOpenChange={setOpen}>
        <DashSheetContent>
          <SheetHeader>
            <SheetTitle>{form.edit_id ? t("common.edit") : t("common.add")}</SheetTitle>
          </SheetHeader>
          <div className="grid gap-3 py-4">
            <div><Label>{t("common.label")}</Label><Input value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} /></div>
            <div><Label>Public IP</Label><Input value={form.public_ip} onChange={(e) => setForm({ ...form, public_ip: e.target.value })} /></div>
            <div><Label>Agent URL</Label><Input value={form.agent_url} onChange={(e) => setForm({ ...form, agent_url: e.target.value })} placeholder="https://node:8444" /></div>
          </div>
          <SheetFooter>
            <Button onClick={save} disabled={busy}>{t("common.save")}</Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>
    </DashPage>
  )
}
