"use client"

import { useCallback, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Sheet, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { DashSheetContent } from "@/components/dash-sheet-content"
import { DashSelect } from "@/components/dash-select"
import { postAdminMutate } from "@/lib/dash-admin-mutate"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

type Props = {
  nodes: DashRecord[]
  inbounds: DashRecord[]
  onMutateSuccess?: () => void
}

export function DashboardXrayInboundsAdmin({ nodes, inbounds, onMutateSuccess }: Props) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({
    edit_id: 0,
    node_id: 0,
    tag: "",
    remark: "",
    protocol: "vless",
    port: 443,
    settings_json: '{"clients":[],"decryption":"none"}',
    stream_settings_json: '{"network":"tcp","security":"reality"}',
  })
  const [busy, setBusy] = useState(false)

  const save = useCallback(async () => {
    setBusy(true)
    try {
      const op = form.edit_id > 0 ? "xray_inbound_update" : "xray_inbound_add"
      await postAdminMutate(op, { ...form, id: form.edit_id || undefined })
      setOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }, [form, onMutateSuccess])

  return (
    <DashPage>
      <DashboardPageHeader
        title={t("sidebar.tabs.xray_inbounds", { defaultValue: "Inbounds" })}
        actions={
          <Button onClick={() => setOpen(true)}>
            {t("common.add", { defaultValue: "Add" })}
          </Button>
        }
      />
      <div className="space-y-2">
        {inbounds.map((row) => (
          <div key={num(row.id)} className="rounded-lg border p-3 text-sm">
            <div className="font-medium">{String(row.tag)} — {String(row.protocol)}:{num(row.port)}</div>
            <div className="text-muted-foreground">{String(row.remark ?? "")} · node #{num(row.node_id)}</div>
          </div>
        ))}
      </div>
      <Sheet open={open} onOpenChange={setOpen}>
        <DashSheetContent>
          <SheetHeader><SheetTitle>{t("common.add")}</SheetTitle></SheetHeader>
          <div className="grid gap-3 py-4">
            <div>
              <Label>Node</Label>
              <DashSelect
                value={String(form.node_id || nodes[0]?.id || "")}
                onValueChange={(v) => setForm({ ...form, node_id: num(v) })}
                options={nodes.map((n) => ({ value: String(n.id), label: String(n.label ?? n.id) }))}
              />
            </div>
            <div><Label>Tag</Label><Input value={form.tag} onChange={(e) => setForm({ ...form, tag: e.target.value })} /></div>
            <div><Label>Protocol</Label><Input value={form.protocol} onChange={(e) => setForm({ ...form, protocol: e.target.value })} /></div>
            <div><Label>Port</Label><Input type="number" value={form.port} onChange={(e) => setForm({ ...form, port: num(e.target.value) })} /></div>
            <div><Label>Settings JSON</Label><Input value={form.settings_json} onChange={(e) => setForm({ ...form, settings_json: e.target.value })} /></div>
            <div><Label>Stream JSON</Label><Input value={form.stream_settings_json} onChange={(e) => setForm({ ...form, stream_settings_json: e.target.value })} /></div>
          </div>
          <SheetFooter><Button onClick={save} disabled={busy}>{t("common.save")}</Button></SheetFooter>
        </DashSheetContent>
      </Sheet>
    </DashPage>
  )
}
