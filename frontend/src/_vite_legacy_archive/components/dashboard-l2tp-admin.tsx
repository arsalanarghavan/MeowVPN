"use client"

import { EllipsisVerticalIcon } from "lucide-react"
import { useCallback, useState } from "react"
import { useTranslation } from "react-i18next"

import { Badge } from "@/components/ui/badge"
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
import {
  Sheet,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { DashSheetContent } from "@/components/dash-sheet-content"
import { DataPagination } from "@/components/data-pagination"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { DashSelect } from "@/components/dash-select"
import { formatNumber } from "@/lib/format-locale"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isActiveRow(r: DashRecord): boolean {
  return r.active === true || r.active === 1 || r.active === "1"
}

function hasSecret(v: unknown): boolean {
  if (v == null) return false
  const s = String(v).trim()
  return s.length > 0
}

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
    active: isActiveRow(r),
    ssh_password: "",
    ssh_private_key: "",
    ssh_key_passphrase: "",
    l2tp_psk: "",
  }
}

export function DashboardL2tpAdmin({
  servers,
  pagination,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  servers: DashRecord[]
  pagination: PaginationMeta | null
onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const tp = (k: string) => t(`l2tpAdmin.${k}`)

  const [sheetOpen, setSheetOpen] = useState(false)
  const [mode, setMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<FormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)

  const run = useCallback(
    async (op: string, params: Record<string, unknown>) => {
      setSaving(true)
      setError(null)
      try {
        const res = await postAdminMutate(op, params)
        if (!res.ok) {
          setError(res.message || tp("mutateError"))
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

  const payloadFromForm = (): Record<string, unknown> => ({
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
    if (mode === "add") {
      void run("l2tp_add", payloadFromForm())
      return
    }
    void run("l2tp_update", { edit_id: form.edit_id, ...payloadFromForm() })
  }

  return (
    <DashPage data-testid="dash-l2tp-tab">
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

      {servers.length === 0 ? (
        <p className="text-sm text-muted-foreground">{tp("empty")}</p>
      ) : (
        <div className="w-full max-w-full overflow-x-auto rounded-md border border-border">
          <table
            className={cn(
              "w-full min-w-[40rem] border-collapse text-sm [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border",
              "text-start"
            )}
          >
            <thead>
              <tr className="bg-muted/40">
                <th className="p-2 font-medium">#</th>
                <th className="p-2 font-medium">{tp("colLabel")}</th>
                <th className="p-2 font-medium">{tp("colSsh")}</th>
                <th className="p-2 font-medium">{tp("colL2tp")}</th>
                <th className="p-2 font-medium">{tp("colAuth")}</th>
                <th className="p-2 font-medium">{tp("colSecrets")}</th>
                <th className="p-2 font-medium">{tp("colActive")}</th>
                <th className="p-2 w-10" />
              </tr>
            </thead>
            <tbody>
              {servers.map((r) => {
                const id = num(r.id)
                const sec =
                  hasSecret(r.ssh_password_enc) ||
                  hasSecret(r.ssh_private_key_enc) ||
                  hasSecret(r.l2tp_psk_enc)
                return (
                  <tr key={id}>
                    <td className="p-2 font-mono text-xs tabular-nums">{formatNumber(id, isFa)}</td>
                    <td className="p-2">{String(r.label ?? "")}</td>
                    <td className="p-2 font-mono text-xs">
                      {String(r.ssh_user ?? "")}@{String(r.ssh_host ?? "")}:{formatNumber(num(r.ssh_port) || 22, isFa)}
                    </td>
                    <td className="p-2 font-mono text-xs">{String(r.l2tp_host ?? "")}</td>
                    <td className="p-2 text-xs">{String(r.ssh_auth ?? "")}</td>
                    <td className="p-2 text-xs">{sec ? tp("secretsSet") : "—"}</td>
                    <td className="p-2">
                      <Badge variant={isActiveRow(r) ? "default" : "secondary"}>
                        {isActiveRow(r) ? tp("active") : tp("inactive")}
                      </Badge>
                    </td>
                    <td className="p-2">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button type="button" variant="ghost" size="icon" className="h-8 w-8">
                            <EllipsisVerticalIcon className="size-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align={isFa ? "start" : "end"}>
                          <DropdownMenuItem onClick={() => openEdit(r)}>{tp("edit")}</DropdownMenuItem>
                          <DropdownMenuItem className="text-destructive" onClick={() => setDeleteTarget(r)}>
                            {tp("delete")}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      <p className="text-xs text-muted-foreground">{tp("secretsHint")}</p>

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
          <div className="flex-1 space-y-3 overflow-y-auto px-4 pb-4">
            <div className="space-y-2">
              <Label>{tp("fieldLabel")}</Label>
              <Input value={form.label} onChange={(e) => setForm((f) => ({ ...f, label: e.target.value }))} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>{tp("fieldSshHost")}</Label>
                <Input value={form.ssh_host} onChange={(e) => setForm((f) => ({ ...f, ssh_host: e.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label>{tp("fieldSshPort")}</Label>
                <Input
                  type="number"
                  min={1}
                  value={form.ssh_port}
                  onChange={(e) => setForm((f) => ({ ...f, ssh_port: num(e.target.value) }))}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldSshUser")}</Label>
              <Input value={form.ssh_user} onChange={(e) => setForm((f) => ({ ...f, ssh_user: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldSshAuth")}</Label>
              <DashSelect
                value={form.ssh_auth}
                onValueChange={(v) =>
                  setForm((f) => ({ ...f, ssh_auth: v === "password" ? "password" : "key" }))
                }
                options={[
                  { value: "key", label: tp("authKey") },
                  { value: "password", label: tp("authPassword") },
                ]}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldSshPassword")}</Label>
              <Input
                type="password"
                autoComplete="off"
                value={form.ssh_password}
                onChange={(e) => setForm((f) => ({ ...f, ssh_password: e.target.value }))}
                placeholder={mode === "edit" ? tp("secretReplaceHint") : ""}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldPrivateKey")}</Label>
              <textarea
                className="flex min-h-[5rem] w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                value={form.ssh_private_key}
                onChange={(e) => setForm((f) => ({ ...f, ssh_private_key: e.target.value }))}
                placeholder={mode === "edit" ? tp("secretReplaceHint") : ""}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldKeyPassphrase")}</Label>
              <Input
                type="password"
                autoComplete="off"
                value={form.ssh_key_passphrase}
                onChange={(e) => setForm((f) => ({ ...f, ssh_key_passphrase: e.target.value }))}
                placeholder={mode === "edit" ? tp("secretReplaceHint") : ""}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldL2tpHost")}</Label>
              <Input value={form.l2tp_host} onChange={(e) => setForm((f) => ({ ...f, l2tp_host: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldPsk")}</Label>
              <Input
                type="password"
                autoComplete="off"
                value={form.l2tp_psk}
                onChange={(e) => setForm((f) => ({ ...f, l2tp_psk: e.target.value }))}
                placeholder={mode === "edit" ? tp("secretReplaceHint") : ""}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldChap")}</Label>
              <Input value={form.chap_path} onChange={(e) => setForm((f) => ({ ...f, chap_path: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldReload")}</Label>
              <Input value={form.reload_cmd} onChange={(e) => setForm((f) => ({ ...f, reload_cmd: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldUsageTpl")}</Label>
              <Input
                value={form.usage_cmd_template}
                onChange={(e) => setForm((f) => ({ ...f, usage_cmd_template: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldNote")}</Label>
              <textarea
                className="flex min-h-[4rem] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                value={form.apps_note}
                onChange={(e) => setForm((f) => ({ ...f, apps_note: e.target.value }))}
              />
            </div>
            <label className={cn("flex items-center gap-2 text-sm")}>
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.active}
                onChange={(e) => setForm((f) => ({ ...f, active: e.target.checked }))}
              />
              {tp("fieldActive")}
            </label>
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
              onClick={() => deleteTarget && void run("l2tp_delete", { id: num(deleteTarget.id) })}
            >
              {tp("delete")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </DashPage>
  )
}
