"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { postAdminMutate } from "@/lib/dash-admin-mutate"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

const PROVIDERS = ["frp", "gost", "xray_reverse", "wireguard"]

type Props = {
  nodes: DashRecord[]
  inbounds: DashRecord[]
  hosts: DashRecord[]
  endpoints: DashRecord[]
  xrayCoreEnabled: boolean
  tunnelEnabled: boolean
  onMutateSuccess?: () => void
  onOpenConfigs?: () => void
}

export function DashboardVpnServerAdmin({
  nodes,
  inbounds,
  hosts,
  endpoints,
  xrayCoreEnabled,
  tunnelEnabled,
  onMutateSuccess,
  onOpenConfigs,
}: Props) {
  const { t } = useTranslation()
  const localNode = useMemo(
    () => nodes.find((n) => n.is_local === true || n.is_local === 1) ?? nodes[0] ?? null,
    [nodes]
  )

  const [busy, setBusy] = useState(false)
  const [overview, setOverview] = useState<DashRecord | null>(null)
  const [settingsOpen, setSettingsOpen] = useState(false)
  const [settingsForm, setSettingsForm] = useState({ label: "", public_ip: "" })

  const [inboundOpen, setInboundOpen] = useState(false)
  const [inboundForm, setInboundForm] = useState({
    edit_id: 0,
    tag: "",
    remark: "",
    protocol: "vless",
    port: 443,
    settings_json: '{"clients":[],"decryption":"none"}',
    stream_settings_json: '{"network":"tcp","security":"reality"}',
  })

  const [hostOpen, setHostOpen] = useState(false)
  const [hostForm, setHostForm] = useState({
    edit_id: 0,
    inbound_id: 0,
    address: "",
    port: 0,
    sni: "",
    host: "",
    path: "",
    fingerprint: "chrome",
  })

  const [tunnelOpen, setTunnelOpen] = useState(false)
  const [tunnelForm, setTunnelForm] = useState({
    edit_id: 0,
    label: "",
    provider: "frp",
    public_ip: "",
    ssh_host: "",
    ssh_port: 22,
    ssh_user: "root",
    config_json: '{"frps_port":7000,"proxies":[{"name":"vless443","type":"tcp","local_port":443,"remote_port":8443}]}',
  })

  const refreshOverview = useCallback(async () => {
    if (!xrayCoreEnabled) return
    try {
      const res = await postAdminMutate("vpn_server_overview", {})
      if (res && typeof res === "object" && (res as DashRecord).ok !== false) {
        setOverview(res as DashRecord)
      }
    } catch {
      /* ignore */
    }
  }, [xrayCoreEnabled])

  useEffect(() => {
    void refreshOverview()
  }, [refreshOverview])

  const runOp = async (op: string, payload: DashRecord = {}) => {
    setBusy(true)
    try {
      await postAdminMutate(op, payload)
      await refreshOverview()
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const openSettings = () => {
    setSettingsForm({
      label: String(localNode?.label ?? ""),
      public_ip: String(localNode?.public_ip ?? ""),
    })
    setSettingsOpen(true)
  }

  const saveSettings = async () => {
    setBusy(true)
    try {
      await postAdminMutate("vpn_server_update", settingsForm)
      setSettingsOpen(false)
      onMutateSuccess?.()
      await refreshOverview()
    } finally {
      setBusy(false)
    }
  }

  const saveInbound = async () => {
    setBusy(true)
    try {
      const op = inboundForm.edit_id > 0 ? "xray_inbound_update" : "xray_inbound_add"
      await postAdminMutate(op, { ...inboundForm, id: inboundForm.edit_id || undefined })
      setInboundOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const saveHost = async () => {
    setBusy(true)
    try {
      const op = hostForm.edit_id > 0 ? "xray_host_update" : "xray_host_add"
      await postAdminMutate(op, { ...hostForm, id: hostForm.edit_id || undefined })
      setHostOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const saveTunnel = async () => {
    setBusy(true)
    try {
      const op = tunnelForm.edit_id > 0 ? "tunnel_update" : "tunnel_add"
      let cfg: unknown = tunnelForm.config_json
      try {
        cfg = JSON.parse(tunnelForm.config_json)
      } catch {
        /* keep string */
      }
      await postAdminMutate(op, { ...tunnelForm, config_json: cfg, id: tunnelForm.edit_id || undefined })
      setTunnelOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const healthStatus = String(
    (overview?.health as DashRecord | undefined)?.status ??
      localNode?.last_health_status ??
      "unknown"
  )
  const nodeData = (overview?.node as DashRecord | undefined) ?? localNode ?? {}
  const inboundCount = num(overview?.inbound_count ?? inbounds.filter((i) => i.active).length)
  const clientCount = num(overview?.client_count)

  return (
    <DashPage>
      <DashboardPageHeader
        title={t("sidebar.tabs.vpn_server", { defaultValue: "VPN Server" })}
        description={t("vpnServer.desc", {
          defaultValue: "Local Xray on bot host — inbounds, hosts, edge tunnels.",
        })}
      />

      <Tabs defaultValue="overview" className="space-y-4">
        <TabsList className="flex h-auto flex-wrap gap-1">
          {xrayCoreEnabled && (
            <TabsTrigger value="overview">{t("vpnServer.tabOverview", { defaultValue: "Overview" })}</TabsTrigger>
          )}
          {xrayCoreEnabled && (
            <TabsTrigger value="inbounds">{t("vpnServer.tabInbounds", { defaultValue: "Inbounds" })}</TabsTrigger>
          )}
          {xrayCoreEnabled && (
            <TabsTrigger value="hosts">{t("vpnServer.tabHosts", { defaultValue: "Hosts" })}</TabsTrigger>
          )}
          {tunnelEnabled && (
            <TabsTrigger value="tunnels">{t("vpnServer.tabTunnels", { defaultValue: "Edge Tunnels" })}</TabsTrigger>
          )}
          <TabsTrigger value="clients">{t("vpnServer.tabClients", { defaultValue: "Clients" })}</TabsTrigger>
        </TabsList>

        {xrayCoreEnabled && (
          <TabsContent value="overview" className="space-y-4">
            <div className="rounded-lg border p-4 space-y-3">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">{String(nodeData.label ?? "Bot host (local)")}</span>
                <Badge variant="outline">{healthStatus}</Badge>
              </div>
              <div className="text-sm text-muted-foreground grid gap-1 sm:grid-cols-2">
                <div>Public IP: {String(nodeData.public_ip ?? "—")}</div>
                <div>Inbounds: {inboundCount}</div>
                <div>Native clients: {clientCount}</div>
                <div>Agent: {String(overview?.agent_url ?? "env")}</div>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" disabled={busy} onClick={() => runOp("vpn_server_health")}>
                  Health
                </Button>
                <Button size="sm" variant="outline" disabled={busy} onClick={() => runOp("vpn_server_apply")}>
                  Apply config
                </Button>
                <Button size="sm" disabled={busy} onClick={() => runOp("vpn_server_restart")}>
                  Restart
                </Button>
                <Button size="sm" variant="secondary" disabled={busy} onClick={openSettings}>
                  {t("common.edit", { defaultValue: "Edit" })}
                </Button>
              </div>
            </div>
          </TabsContent>
        )}

        {xrayCoreEnabled && (
          <TabsContent value="inbounds" className="space-y-3">
            <div className="flex justify-end">
              <Button
                onClick={() => {
                  setInboundForm({
                    edit_id: 0,
                    tag: "",
                    remark: "",
                    protocol: "vless",
                    port: 443,
                    settings_json: '{"clients":[],"decryption":"none"}',
                    stream_settings_json: '{"network":"tcp","security":"reality"}',
                  })
                  setInboundOpen(true)
                }}
              >
                {t("common.add", { defaultValue: "Add" })}
              </Button>
            </div>
            {inbounds.map((row) => (
              <div key={num(row.id)} className="rounded-lg border p-3 text-sm flex flex-wrap gap-2 items-center">
                <div className="flex-1 min-w-0">
                  <div className="font-medium">
                    {String(row.tag)} — {String(row.protocol)}:{num(row.port)}
                  </div>
                  <div className="text-muted-foreground">{String(row.remark ?? "")}</div>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={busy}
                  onClick={() => {
                    setInboundForm({
                      edit_id: num(row.id),
                      tag: String(row.tag ?? ""),
                      remark: String(row.remark ?? ""),
                      protocol: String(row.protocol ?? "vless"),
                      port: num(row.port) || 443,
                      settings_json: String(row.settings_json ?? "{}"),
                      stream_settings_json: String(row.stream_settings_json ?? "{}"),
                    })
                    setInboundOpen(true)
                  }}
                >
                  {t("common.edit")}
                </Button>
                <Button
                  size="sm"
                  variant="destructive"
                  disabled={busy}
                  onClick={() => runOp("xray_inbound_delete", { id: num(row.id) })}
                >
                  {t("common.delete", { defaultValue: "Delete" })}
                </Button>
              </div>
            ))}
          </TabsContent>
        )}

        {xrayCoreEnabled && (
          <TabsContent value="hosts" className="space-y-3">
            <div className="flex justify-end">
              <Button
                onClick={() => {
                  setHostForm({
                    edit_id: 0,
                    inbound_id: num(inbounds[0]?.id),
                    address: String(localNode?.public_ip ?? ""),
                    port: 0,
                    sni: "",
                    host: "",
                    path: "",
                    fingerprint: "chrome",
                  })
                  setHostOpen(true)
                }}
              >
                {t("common.add")}
              </Button>
            </div>
            {hosts.map((h) => (
              <div key={num(h.id)} className="rounded-lg border p-3 text-sm">
                {String(h.address)}:{num(h.port) || "auto"} · inbound #{num(h.inbound_id)} · SNI{" "}
                {String(h.sni ?? "")}
              </div>
            ))}
          </TabsContent>
        )}

        {tunnelEnabled && (
          <TabsContent value="tunnels" className="space-y-3">
            <div className="flex justify-end">
              <Button
                onClick={() => {
                  setTunnelForm({
                    edit_id: 0,
                    label: "",
                    provider: "frp",
                    public_ip: "",
                    ssh_host: "",
                    ssh_port: 22,
                    ssh_user: "root",
                    config_json:
                      '{"frps_port":7000,"proxies":[{"name":"vless443","type":"tcp","local_port":443,"remote_port":8443}]}',
                  })
                  setTunnelOpen(true)
                }}
              >
                {t("common.add")}
              </Button>
            </div>
            {endpoints.map((e) => (
              <div key={num(e.id)} className="flex flex-wrap items-center gap-2 rounded-lg border p-4">
                <div className="flex-1">
                  <div className="font-medium">{String(e.label ?? e.id)}</div>
                  <div className="text-sm text-muted-foreground">
                    {String(e.provider)} · {String(e.ssh_host)} · {String(e.public_ip ?? "")}
                  </div>
                </div>
                <Badge variant="outline">{String(e.health_status ?? "pending")}</Badge>
                <Button size="sm" disabled={busy} onClick={() => runOp("tunnel_deploy", { id: num(e.id) })}>
                  Deploy
                </Button>
              </div>
            ))}
          </TabsContent>
        )}

        <TabsContent value="clients" className="space-y-3">
          <p className="text-sm text-muted-foreground">
            {t("vpnServer.clientsHint", {
              defaultValue: "Native clients are managed via the Configs tab (panel_driver=native).",
            })}
          </p>
          {onOpenConfigs && (
            <Button variant="outline" onClick={onOpenConfigs}>
              {t("sidebar.tabs.configs", { defaultValue: "Configs" })}
            </Button>
          )}
        </TabsContent>
      </Tabs>

      <Sheet open={settingsOpen} onOpenChange={setSettingsOpen}>
        <DashSheetContent>
          <SheetHeader>
            <SheetTitle>{t("vpnServer.localSettings", { defaultValue: "Local node settings" })}</SheetTitle>
          </SheetHeader>
          <div className="grid gap-3 py-4">
            <div>
              <Label>{t("common.label")}</Label>
              <Input
                value={settingsForm.label}
                onChange={(e) => setSettingsForm({ ...settingsForm, label: e.target.value })}
              />
            </div>
            <div>
              <Label>Public IP</Label>
              <Input
                value={settingsForm.public_ip}
                onChange={(e) => setSettingsForm({ ...settingsForm, public_ip: e.target.value })}
              />
            </div>
          </div>
          <SheetFooter>
            <Button onClick={saveSettings} disabled={busy}>
              {t("common.save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

      <Sheet open={inboundOpen} onOpenChange={setInboundOpen}>
        <DashSheetContent>
          <SheetHeader>
            <SheetTitle>{inboundForm.edit_id ? t("common.edit") : t("common.add")}</SheetTitle>
          </SheetHeader>
          <div className="grid gap-3 py-4">
            <div>
              <Label>Tag</Label>
              <Input value={inboundForm.tag} onChange={(e) => setInboundForm({ ...inboundForm, tag: e.target.value })} />
            </div>
            <div>
              <Label>Remark</Label>
              <Input
                value={inboundForm.remark}
                onChange={(e) => setInboundForm({ ...inboundForm, remark: e.target.value })}
              />
            </div>
            <div>
              <Label>Protocol</Label>
              <Input
                value={inboundForm.protocol}
                onChange={(e) => setInboundForm({ ...inboundForm, protocol: e.target.value })}
              />
            </div>
            <div>
              <Label>Port</Label>
              <Input
                type="number"
                value={inboundForm.port}
                onChange={(e) => setInboundForm({ ...inboundForm, port: num(e.target.value) })}
              />
            </div>
            <div>
              <Label>Settings JSON</Label>
              <Input
                value={inboundForm.settings_json}
                onChange={(e) => setInboundForm({ ...inboundForm, settings_json: e.target.value })}
              />
            </div>
            <div>
              <Label>Stream JSON</Label>
              <Input
                value={inboundForm.stream_settings_json}
                onChange={(e) => setInboundForm({ ...inboundForm, stream_settings_json: e.target.value })}
              />
            </div>
          </div>
          <SheetFooter>
            <Button onClick={saveInbound} disabled={busy}>
              {t("common.save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

      <Sheet open={hostOpen} onOpenChange={setHostOpen}>
        <DashSheetContent>
          <SheetHeader>
            <SheetTitle>{t("common.add")}</SheetTitle>
          </SheetHeader>
          <div className="grid gap-3 py-4">
            <div>
              <Label>Inbound</Label>
              <DashSelect
                value={String(hostForm.inbound_id || inbounds[0]?.id || "")}
                onValueChange={(v) => setHostForm({ ...hostForm, inbound_id: num(v) })}
                options={inbounds.map((i) => ({ value: String(i.id), label: String(i.tag ?? i.id) }))}
              />
            </div>
            <div>
              <Label>Address</Label>
              <Input
                value={hostForm.address}
                onChange={(e) => setHostForm({ ...hostForm, address: e.target.value })}
              />
            </div>
            <div>
              <Label>Port (0 = inbound)</Label>
              <Input
                type="number"
                value={hostForm.port}
                onChange={(e) => setHostForm({ ...hostForm, port: num(e.target.value) })}
              />
            </div>
            <div>
              <Label>SNI</Label>
              <Input value={hostForm.sni} onChange={(e) => setHostForm({ ...hostForm, sni: e.target.value })} />
            </div>
          </div>
          <SheetFooter>
            <Button onClick={saveHost} disabled={busy}>
              {t("common.save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

      <Sheet open={tunnelOpen} onOpenChange={setTunnelOpen}>
        <DashSheetContent>
          <SheetHeader>
            <SheetTitle>{t("common.add")}</SheetTitle>
          </SheetHeader>
          <div className="grid gap-3 py-4">
            <div>
              <Label>{t("common.label")}</Label>
              <Input value={tunnelForm.label} onChange={(e) => setTunnelForm({ ...tunnelForm, label: e.target.value })} />
            </div>
            <div>
              <Label>Provider</Label>
              <DashSelect
                value={tunnelForm.provider}
                onValueChange={(v) => setTunnelForm({ ...tunnelForm, provider: v })}
                options={PROVIDERS.map((p) => ({ value: p, label: p }))}
              />
            </div>
            <div>
              <Label>Edge public IP</Label>
              <Input
                value={tunnelForm.public_ip}
                onChange={(e) => setTunnelForm({ ...tunnelForm, public_ip: e.target.value })}
              />
            </div>
            <div>
              <Label>SSH host</Label>
              <Input
                value={tunnelForm.ssh_host}
                onChange={(e) => setTunnelForm({ ...tunnelForm, ssh_host: e.target.value })}
              />
            </div>
            <div>
              <Label>Config JSON</Label>
              <Input
                value={tunnelForm.config_json}
                onChange={(e) => setTunnelForm({ ...tunnelForm, config_json: e.target.value })}
              />
            </div>
          </div>
          <SheetFooter>
            <Button onClick={saveTunnel} disabled={busy}>
              {t("common.save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>
    </DashPage>
  )
}
