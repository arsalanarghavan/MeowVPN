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
  inbounds: DashRecord[]
  hosts: DashRecord[]
  onMutateSuccess?: () => void
}

export function DashboardXrayHostsAdmin({ inbounds, hosts, onMutateSuccess }: Props) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({
    edit_id: 0,
    inbound_id: 0,
    address: "",
    port: 0,
    sni: "",
    host: "",
    path: "",
    fingerprint: "chrome",
  })
  const [busy, setBusy] = useState(false)

  const save = useCallback(async () => {
    setBusy(true)
    try {
      await postAdminMutate("xray_host_add", { ...form, id: form.edit_id || undefined })
      setOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }, [form, onMutateSuccess])

  return (
    <DashPage>
      <DashboardPageHeader
        title={t("sidebar.tabs.xray_hosts", { defaultValue: "Hosts" })}
        actions={<Button onClick={() => setOpen(true)}>{t("common.add")}</Button>}
      />
      <div className="space-y-2">
        {hosts.map((h) => (
          <div key={num(h.id)} className="rounded-lg border p-3 text-sm">
            {String(h.address)}:{num(h.port) || "auto"} · inbound #{num(h.inbound_id)} · SNI {String(h.sni ?? "")}
          </div>
        ))}
      </div>
      <Sheet open={open} onOpenChange={setOpen}>
        <DashSheetContent>
          <SheetHeader><SheetTitle>{t("common.add")}</SheetTitle></SheetHeader>
          <div className="grid gap-3 py-4">
            <div>
              <Label>Inbound</Label>
              <DashSelect
                value={String(form.inbound_id || inbounds[0]?.id || "")}
                onValueChange={(v) => setForm({ ...form, inbound_id: num(v) })}
                options={inbounds.map((i) => ({ value: String(i.id), label: String(i.tag ?? i.id) }))}
              />
            </div>
            <div><Label>Address</Label><Input value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} /></div>
            <div><Label>Port (0 = inbound)</Label><Input type="number" value={form.port} onChange={(e) => setForm({ ...form, port: num(e.target.value) })} /></div>
            <div><Label>SNI</Label><Input value={form.sni} onChange={(e) => setForm({ ...form, sni: e.target.value })} /></div>
            <div><Label>Host</Label><Input value={form.host} onChange={(e) => setForm({ ...form, host: e.target.value })} /></div>
            <div><Label>Path</Label><Input value={form.path} onChange={(e) => setForm({ ...form, path: e.target.value })} /></div>
          </div>
          <SheetFooter><Button onClick={save} disabled={busy}>{t("common.save")}</Button></SheetFooter>
        </DashSheetContent>
      </Sheet>
    </DashPage>
  )
}
