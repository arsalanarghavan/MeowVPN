"use client"

import { useCallback, useEffect, useState } from "react"
import { useTranslations } from "next-intl"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

export function L2tpAdminClient() {
  const t = useTranslations("l2tpAdmin")
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [rows, setRows] = useState<DashRecord[]>([])
  const [formOpen, setFormOpen] = useState(false)
  const [mode, setMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState({
    edit_id: 0,
    label: "",
    ssh_host: "",
    ssh_port: "22",
    ssh_user: "svpbot",
    ssh_auth: "key",
    l2tp_host: "",
    chap_path: "/etc/ppp/chap-secrets",
    reload_cmd: "sudo /bin/systemctl reload xl2tpd",
    usage_cmd_template: "",
    apps_note: "",
    active: true,
    ssh_password: "",
    ssh_private_key: "",
    ssh_key_passphrase: "",
    l2tp_psk: "",
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await getAdminState("l2tp_servers")
      setRows(Array.isArray(data.servers) ? (data.servers as DashRecord[]) : Array.isArray(data.l2tpServers) ? (data.l2tpServers as DashRecord[]) : [])
    } catch {
      setError(t("loadError", { defaultValue: "Could not load L2TP servers." }))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const openAdd = () => {
    setMode("add")
    setForm({ ...form, edit_id: 0, label: "", ssh_host: "", l2tp_host: "", ssh_password: "", ssh_private_key: "", ssh_key_passphrase: "", l2tp_psk: "" })
    setFormOpen(true)
  }

  const openEdit = (row: DashRecord) => {
    setMode("edit")
    setForm({
      edit_id: num(row.id),
      label: String(row.label ?? ""),
      ssh_host: String(row.ssh_host ?? ""),
      ssh_port: String(row.ssh_port ?? 22),
      ssh_user: String(row.ssh_user ?? "svpbot"),
      ssh_auth: String(row.ssh_auth ?? "key"),
      l2tp_host: String(row.l2tp_host ?? ""),
      chap_path: String(row.chap_path ?? "/etc/ppp/chap-secrets"),
      reload_cmd: String(row.reload_cmd ?? "sudo /bin/systemctl reload xl2tpd"),
      usage_cmd_template: String(row.usage_cmd_template ?? ""),
      apps_note: String(row.apps_note ?? ""),
      active: row.active === true || row.active === 1 || row.active === "1",
      ssh_password: "",
      ssh_private_key: "",
      ssh_key_passphrase: "",
      l2tp_psk: "",
    })
    setFormOpen(true)
  }

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      const payload = {
        label: form.label,
        ssh_host: form.ssh_host,
        ssh_port: num(form.ssh_port),
        ssh_user: form.ssh_user,
        ssh_auth: form.ssh_auth,
        l2tp_host: form.l2tp_host,
        chap_path: form.chap_path,
        reload_cmd: form.reload_cmd,
        usage_cmd_template: form.usage_cmd_template,
        apps_note: form.apps_note,
        active: form.active ? 1 : 0,
        ssh_password: form.ssh_password,
        ssh_private_key: form.ssh_private_key,
        ssh_key_passphrase: form.ssh_key_passphrase,
        l2tp_psk: form.l2tp_psk,
      }
      const res = await postAdminMutate(mode === "add" ? "l2tp_add" : "l2tp_update", mode === "add" ? payload : { id: form.edit_id, ...payload })
      if (!res.ok) {
        setError(res.message || t("saveError", { defaultValue: "Save failed." }))
        return
      }
      setFormOpen(false)
      await load()
    } finally {
      setBusy(false)
    }
  }

  const del = async (id: number) => {
    if (!window.confirm(t("deleteConfirm", { defaultValue: "Delete this server?" }))) return
    setBusy(true)
    setError(null)
    try {
      const res = await postAdminMutate("l2tp_delete", { id })
      if (!res.ok) {
        setError(res.message || t("saveError"))
        return
      }
      await load()
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <div className="flex gap-2">
          <Button type="button" onClick={openAdd}>{t("add")}</Button>
          <Button type="button" variant="outline" disabled={loading} onClick={() => void load()}>{t("refresh", { defaultValue: "Refresh" })}</Button>
        </div>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>#</TableHead>
              <TableHead>{t("colLabel")}</TableHead>
              <TableHead>{t("colSsh")}</TableHead>
              <TableHead>{t("colL2tp")}</TableHead>
              <TableHead>{t("colActive")}</TableHead>
              <TableHead>{t("colActions")}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">{loading ? t("loading") : t("empty")}</TableCell>
              </TableRow>
            ) : (
              rows.map((row, idx) => (
                <TableRow key={String(row.id ?? idx)}>
                  <TableCell className="tabular-nums" dir="ltr">{num(row.id) || idx + 1}</TableCell>
                  <TableCell>{String(row.label ?? "—")}</TableCell>
                  <TableCell>{String(row.ssh_user ?? "")}@{String(row.ssh_host ?? "")}:{String(row.ssh_port ?? 22)}</TableCell>
                  <TableCell>{String(row.l2tp_host ?? "")}</TableCell>
                  <TableCell><Badge variant={row.active === false || row.active === 0 ? "secondary" : "default"}>{row.active === false || row.active === 0 ? t("inactive") : t("active")}</Badge></TableCell>
                  <TableCell>
                    <div className="flex flex-wrap gap-1">
                      <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => openEdit(row)}>{t("edit")}</Button>
                      <Button type="button" size="sm" variant="destructive" disabled={busy} onClick={() => void del(num(row.id))}>{t("delete")}</Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{mode === "add" ? t("sheetAdd") : t("sheetEdit")}</DialogTitle>
            <DialogDescription>{t("secretsHint")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 py-2">
            <div className="space-y-1"><Label className="text-xs">{t("fieldLabel")}</Label><Input value={form.label} onChange={(e) => setForm((curr) => ({ ...curr, label: e.target.value }))} /></div>
            <div className="grid grid-cols-2 gap-2">
              <div className="space-y-1"><Label className="text-xs">{t("fieldSshHost")}</Label><Input value={form.ssh_host} onChange={(e) => setForm((curr) => ({ ...curr, ssh_host: e.target.value }))} dir="ltr" /></div>
              <div className="space-y-1"><Label className="text-xs">{t("fieldSshPort")}</Label><Input value={form.ssh_port} onChange={(e) => setForm((curr) => ({ ...curr, ssh_port: e.target.value }))} dir="ltr" /></div>
            </div>
            <div className="space-y-1"><Label className="text-xs">{t("fieldSshUser")}</Label><Input value={form.ssh_user} onChange={(e) => setForm((curr) => ({ ...curr, ssh_user: e.target.value }))} dir="ltr" /></div>
            <div className="space-y-1"><Label className="text-xs">{t("fieldL2tpHost")}</Label><Input value={form.l2tp_host} onChange={(e) => setForm((curr) => ({ ...curr, l2tp_host: e.target.value }))} dir="ltr" /></div>
            <div className="space-y-1"><Label className="text-xs">{t("fieldPsk")}</Label><Input value={form.l2tp_psk} onChange={(e) => setForm((curr) => ({ ...curr, l2tp_psk: e.target.value }))} type="password" /></div>
            <div className="space-y-1"><Label className="text-xs">{t("fieldChap")}</Label><Input value={form.chap_path} onChange={(e) => setForm((curr) => ({ ...curr, chap_path: e.target.value }))} dir="ltr" /></div>
            <div className="space-y-1"><Label className="text-xs">{t("fieldReload")}</Label><Input value={form.reload_cmd} onChange={(e) => setForm((curr) => ({ ...curr, reload_cmd: e.target.value }))} dir="ltr" /></div>
            <div className="space-y-1"><Label className="text-xs">{t("fieldUsageTpl")}</Label><Input value={form.usage_cmd_template} onChange={(e) => setForm((curr) => ({ ...curr, usage_cmd_template: e.target.value }))} dir="ltr" /></div>
            <div className="space-y-1"><Label className="text-xs">{t("fieldNote")}</Label><Textarea value={form.apps_note} onChange={(e) => setForm((curr) => ({ ...curr, apps_note: e.target.value }))} className="min-h-24" /></div>
            <label className="flex items-center gap-2 rounded-md border p-3"><input type="checkbox" checked={form.active} onChange={(e) => setForm((curr) => ({ ...curr, active: e.target.checked }))} /><span>{t("fieldActive")}</span></label>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setFormOpen(false)} disabled={busy}>{t("cancel")}</Button>
            <Button type="button" onClick={() => void save()} disabled={busy}>{t("save")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
