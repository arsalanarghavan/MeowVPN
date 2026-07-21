"use client"

import { useCallback, useEffect, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { EllipsisVertical } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { DataPagination } from "@/components/data-pagination"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"

type DashRecord = Record<string, unknown>
type Pagination = { page: number; perPage: number; total: number }

type FormState = {
  edit_id: number
  label: string
  ssh_host: string
  ssh_port: number
  ssh_user: string
  ssh_auth: "key" | "password"
  l2tp_host: string
  chap_path: string
  reload_cmd: string
  usage_cmd_template: string
  apps_note: string
  active: boolean
  ssh_password: string
  ssh_private_key: string
  ssh_key_passphrase: string
  l2tp_psk: string
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function active(r: DashRecord): boolean {
  return r.active === true || r.active === 1 || r.active === "1"
}

function hasSecret(v: unknown): boolean {
  return String(v ?? "").trim().length > 0
}

function emptyForm(): FormState {
  return {
    edit_id: 0,
    label: "",
    ssh_host: "",
    ssh_port: 22,
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
  }
}

function formFromRow(r: DashRecord): FormState {
  return {
    ...emptyForm(),
    edit_id: num(r.id),
    label: String(r.label ?? ""),
    ssh_host: String(r.ssh_host ?? ""),
    ssh_port: Math.max(1, num(r.ssh_port) || 22),
    ssh_user: String(r.ssh_user ?? "svpbot"),
    ssh_auth: r.ssh_auth === "password" ? "password" : "key",
    l2tp_host: String(r.l2tp_host ?? ""),
    chap_path: String(r.chap_path ?? "/etc/ppp/chap-secrets"),
    reload_cmd: String(r.reload_cmd ?? "sudo /bin/systemctl reload xl2tpd"),
    usage_cmd_template: String(r.usage_cmd_template ?? ""),
    apps_note: String(r.apps_note ?? ""),
    active: active(r),
  }
}

function parsePagination(raw: unknown): Pagination | null {
  if (!raw || typeof raw !== "object") return null
  const r = raw as Record<string, unknown>
  const page = num(r.page)
  const perPage = num(r.perPage ?? r.per_page)
  const total = num(r.total)
  return page > 0 && perPage > 0 ? { page, perPage, total } : null
}

export function L2tpServersAdminClient() {
  const t = useTranslations("l2tpAdmin")
  const locale = useLocale()
  const isFa = locale === "fa"

  const [servers, setServers] = useState<DashRecord[]>([])
  const [pagination, setPagination] = useState<Pagination | null>(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(20)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [mode, setMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<FormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await getAdminState("l2tp_servers", { l2tp_page: page, l2tp_per_page: perPage })
      setServers(Array.isArray(data.l2tpServers) ? (data.l2tpServers as DashRecord[]) : [])
      setPagination(parsePagination((data.pagination as Record<string, unknown> | undefined)?.l2tpServers))
    } catch {
      setError(t("mutateError"))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, t])

  useEffect(() => {
    void load()
  }, [load])

  const openAdd = () => {
    setError(null)
    setMode("add")
    setForm(emptyForm())
    setSheetOpen(true)
  }

  const openEdit = (row: DashRecord) => {
    setError(null)
    setMode("edit")
    setForm(formFromRow(row))
    setSheetOpen(true)
  }

  const payload = (): Record<string, unknown> => ({
    label: form.label.trim(),
    ssh_host: form.ssh_host.trim(),
    ssh_port: form.ssh_port,
    ssh_user: form.ssh_user.trim(),
    ssh_auth: form.ssh_auth,
    l2tp_host: form.l2tp_host.trim(),
    chap_path: form.chap_path.trim(),
    reload_cmd: form.reload_cmd.trim(),
    usage_cmd_template: form.usage_cmd_template.trim(),
    apps_note: form.apps_note.trim(),
    active: form.active ? 1 : 0,
    ssh_password: form.ssh_password,
    ssh_private_key: form.ssh_private_key,
    ssh_key_passphrase: form.ssh_key_passphrase,
    l2tp_psk: form.l2tp_psk,
  })

  const run = useCallback(
    async (op: string, params: Record<string, unknown>) => {
      setSaving(true)
      setError(null)
      try {
        const res = await postAdminMutate(op, params)
        if (!res.ok) {
          setError(res.message || res.reason || t("mutateError"))
          return
        }
        setSheetOpen(false)
        setDeleteTarget(null)
        await load()
      } catch {
        setError(t("mutateError"))
      } finally {
        setSaving(false)
      }
    },
    [load, t]
  )

  const save = () => {
    const body = mode === "edit" ? { edit_id: form.edit_id, ...payload() } : payload()
    void run(mode === "edit" ? "l2tp_update" : "l2tp_add", body)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <div className="flex gap-2">
          <Button type="button" size="sm" onClick={openAdd}>{t("add")}</Button>
          <Button type="button" size="sm" variant="outline" disabled={loading} onClick={() => void load()}>
            {t("refresh")}
          </Button>
        </div>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("title")}</CardTitle>
          <CardDescription>{t("secretsHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>#</TableHead>
                  <TableHead>{t("colLabel")}</TableHead>
                  <TableHead>{t("colSsh")}</TableHead>
                  <TableHead>{t("colL2tp")}</TableHead>
                  <TableHead>{t("colAuth")}</TableHead>
                  <TableHead>{t("colSecrets")}</TableHead>
                  <TableHead>{t("colActive")}</TableHead>
                  <TableHead className="w-12" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {!loading && servers.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={8} className="text-center text-muted-foreground">{t("empty")}</TableCell>
                  </TableRow>
                ) : null}
                {servers.map((row) => {
                  const id = num(row.id)
                  const secrets = hasSecret(row.ssh_password_enc) || hasSecret(row.ssh_private_key_enc) || hasSecret(row.l2tp_psk_enc)
                  return (
                    <TableRow key={id}>
                      <TableCell className="tabular-nums" dir="ltr">#{formatNumber(id, isFa)}</TableCell>
                      <TableCell>{String(row.label ?? "")}</TableCell>
                      <TableCell className="font-mono text-xs" dir="ltr">
                        {String(row.ssh_user ?? "")}@{String(row.ssh_host ?? "")}:{num(row.ssh_port) || 22}
                      </TableCell>
                      <TableCell className="font-mono text-xs" dir="ltr">{String(row.l2tp_host ?? "")}</TableCell>
                      <TableCell>{String(row.ssh_auth ?? "")}</TableCell>
                      <TableCell>{secrets ? t("secretsSet") : "—"}</TableCell>
                      <TableCell>
                        <Badge variant={active(row) ? "default" : "secondary"}>{active(row) ? t("active") : t("inactive")}</Badge>
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger
                            render={
                              <Button type="button" variant="ghost" size="icon" className="h-8 w-8">
                                <EllipsisVertical className="size-4" />
                              </Button>
                            }
                          />
                          <DropdownMenuContent align={isFa ? "start" : "end"}>
                            <DropdownMenuItem onClick={() => openEdit(row)}>{t("edit")}</DropdownMenuItem>
                            <DropdownMenuItem className="text-destructive" onClick={() => setDeleteTarget(row)}>
                              {t("delete")}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </div>

          <DataPagination
            meta={pagination}
            onPageChange={setPage}
            onPerPageChange={(n) => {
              setPerPage(n)
              setPage(1)
            }}
          />
        </CardContent>
      </Card>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
          <SheetHeader>
            <SheetTitle>{mode === "add" ? t("sheetAdd") : t("sheetEdit")}</SheetTitle>
          </SheetHeader>
          <div className="space-y-3 px-4">
            <Field label={t("fieldLabel")} value={form.label} onChange={(v) => setForm((f) => ({ ...f, label: v }))} />
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label={t("fieldSshHost")} value={form.ssh_host} onChange={(v) => setForm((f) => ({ ...f, ssh_host: v }))} />
              <Field label={t("fieldSshPort")} type="number" value={String(form.ssh_port)} onChange={(v) => setForm((f) => ({ ...f, ssh_port: num(v) }))} />
            </div>
            <Field label={t("fieldSshUser")} value={form.ssh_user} onChange={(v) => setForm((f) => ({ ...f, ssh_user: v }))} />
            <div className="space-y-2">
              <Label>{t("fieldSshAuth")}</Label>
              <select className="h-9 w-full rounded-md border bg-background px-3 text-sm" value={form.ssh_auth} onChange={(e) => setForm((f) => ({ ...f, ssh_auth: e.target.value === "password" ? "password" : "key" }))}>
                <option value="key">{t("authKey")}</option>
                <option value="password">{t("authPassword")}</option>
              </select>
            </div>
            <Field label={t("fieldSshPassword")} type="password" value={form.ssh_password} placeholder={mode === "edit" ? t("secretReplaceHint") : ""} onChange={(v) => setForm((f) => ({ ...f, ssh_password: v }))} />
            <TextArea label={t("fieldPrivateKey")} value={form.ssh_private_key} placeholder={mode === "edit" ? t("secretReplaceHint") : ""} onChange={(v) => setForm((f) => ({ ...f, ssh_private_key: v }))} />
            <Field label={t("fieldKeyPassphrase")} type="password" value={form.ssh_key_passphrase} placeholder={mode === "edit" ? t("secretReplaceHint") : ""} onChange={(v) => setForm((f) => ({ ...f, ssh_key_passphrase: v }))} />
            <Field label={t("fieldL2tpHost")} value={form.l2tp_host} onChange={(v) => setForm((f) => ({ ...f, l2tp_host: v }))} />
            <Field label={t("fieldPsk")} type="password" value={form.l2tp_psk} placeholder={mode === "edit" ? t("secretReplaceHint") : ""} onChange={(v) => setForm((f) => ({ ...f, l2tp_psk: v }))} />
            <Field label={t("fieldChap")} value={form.chap_path} onChange={(v) => setForm((f) => ({ ...f, chap_path: v }))} />
            <Field label={t("fieldReload")} value={form.reload_cmd} onChange={(v) => setForm((f) => ({ ...f, reload_cmd: v }))} />
            <Field label={t("fieldUsageTpl")} value={form.usage_cmd_template} onChange={(v) => setForm((f) => ({ ...f, usage_cmd_template: v }))} />
            <TextArea label={t("fieldNote")} value={form.apps_note} onChange={(v) => setForm((f) => ({ ...f, apps_note: v }))} />
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" className="size-4 rounded border-input" checked={form.active} onChange={(e) => setForm((f) => ({ ...f, active: e.target.checked }))} />
              {t("fieldActive")}
            </label>
          </div>
          <SheetFooter className="flex-row gap-2">
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>{t("cancel")}</Button>
            <Button type="button" disabled={saving} onClick={save}>{t("save")}</Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      <Dialog open={Boolean(deleteTarget)} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
            <DialogDescription>{t("deleteDesc")}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>{t("cancel")}</Button>
            <Button type="button" variant="destructive" disabled={saving} onClick={() => deleteTarget && void run("l2tp_delete", { id: num(deleteTarget.id) })}>
              {t("delete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function Field({ label, value, onChange, type = "text", placeholder = "" }: { label: string; value: string; onChange: (value: string) => void; type?: string; placeholder?: string }) {
  return (
    <div className="space-y-2">
      <Label>{label}</Label>
      <Input type={type} value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} dir={type === "number" ? "ltr" : undefined} />
    </div>
  )
}

function TextArea({ label, value, onChange, placeholder = "" }: { label: string; value: string; onChange: (value: string) => void; placeholder?: string }) {
  return (
    <div className="space-y-2">
      <Label>{label}</Label>
      <textarea className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50" value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />
    </div>
  )
}
