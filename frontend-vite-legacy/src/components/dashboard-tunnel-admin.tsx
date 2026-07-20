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
import { DashSelect } from "@/components/dash-select"
import { postAdminMutate } from "@/lib/dash-admin-mutate"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

const PROVIDERS = ["frp", "gost", "xray_reverse", "wireguard"]

type Props = {
  nodes: DashRecord[]
  endpoints: DashRecord[]
  onMutateSuccess?: () => void
}

export function DashboardTunnelAdmin({ nodes, endpoints, onMutateSuccess }: Props) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({
    edit_id: 0,
    node_id: 0,
    label: "",
    provider: "frp",
    public_ip: "",
    ssh_host: "",
    ssh_port: 22,
    ssh_user: "root",
    config_json: '{"frps_addr":"central.example.com","frps_port":7000,"proxies":[{"name":"vless443","type":"tcp","local_port":443,"remote_port":8443}]}',
  })
  const [busy, setBusy] = useState(false)

  const save = useCallback(async () => {
    setBusy(true)
    try {
      const op = form.edit_id > 0 ? "tunnel_update" : "tunnel_add"
      let cfg: unknown = form.config_json
      try {
        cfg = JSON.parse(form.config_json)
      } catch {
        /* keep string */
      }
      await postAdminMutate(op, { ...form, config_json: cfg, id: form.edit_id || undefined })
      setOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }, [form, onMutateSuccess])

  const deploy = async (id: number) => {
    setBusy(true)
    try {
      await postAdminMutate("tunnel_deploy", { id })
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  return (
    <DashPage>
      <DashboardPageHeader
        title={t("sidebar.tabs.tunnel_nodes", { defaultValue: "Edge tunnels" })}
        actions={<Button onClick={() => setOpen(true)}>{t("common.add")}</Button>}
      />
      <div className="space-y-3">
        {endpoints.map((e) => (
          <div key={num(e.id)} className="flex flex-wrap items-center gap-2 rounded-lg border p-4">
            <div className="flex-1">
              <div className="font-medium">{String(e.label ?? e.id)}</div>
              <div className="text-sm text-muted-foreground">{String(e.provider)} · {String(e.ssh_host)}</div>
            </div>
            <Badge variant="outline">{String(e.health_status ?? "pending")}</Badge>
            <Button size="sm" disabled={busy} onClick={() => deploy(num(e.id))}>Deploy</Button>
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
            <div><Label>{t("common.label")}</Label><Input value={form.label} onChange={(ev) => setForm({ ...form, label: ev.target.value })} /></div>
            <div>
              <Label>Provider</Label>
              <DashSelect
                value={form.provider}
                onValueChange={(v) => setForm({ ...form, provider: v })}
                options={PROVIDERS.map((p) => ({ value: p, label: p }))}
              />
            </div>
            <div><Label>SSH host</Label><Input value={form.ssh_host} onChange={(ev) => setForm({ ...form, ssh_host: ev.target.value })} /></div>
            <div><Label>Config JSON</Label><Input value={form.config_json} onChange={(ev) => setForm({ ...form, config_json: ev.target.value })} /></div>
          </div>
          <SheetFooter><Button onClick={save} disabled={busy}>{t("common.save")}</Button></SheetFooter>
        </DashSheetContent>
      </Sheet>
    </DashPage>
  )
}
