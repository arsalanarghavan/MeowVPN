"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useRouter } from "next/navigation"
import { useLocale, useTranslations } from "next-intl"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { DashSelect } from "@/components/dash-select"
import { DashSheetContent } from "@/components/dash-sheet-content"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Sheet, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

const PROVIDERS = ["frp", "gost", "xray_reverse", "wireguard"]

const emptyHostForm = () => ({
  edit_id: 0,
  remark: "",
  address: "",
  port: "0",
  inbound_id: 0,
  sni: "",
  host: "",
  path: "",
  fingerprint: "chrome",
})

const emptyTunnelForm = () => ({
  edit_id: 0,
  label: "",
  provider: "frp",
  public_ip: "",
  ssh_host: "",
  ssh_port: "22",
  ssh_user: "root",
  config_json:
    '{"frps_port":7000,"proxies":[{"name":"vless443","type":"tcp","local_port":443,"remote_port":8443}]}',
})

const emptyInboundForm = () => ({
  edit_id: 0,
  tag: "",
  remark: "",
  protocol: "vless",
  port: "443",
  settings_json: '{"clients":[],"decryption":"none"}',
  stream_settings_json: '{"network":"tcp","security":"reality"}',
})

export function VpnServerAdminClient({
  defaultTab,
}: {
  defaultTab?: "overview" | "inbounds" | "hosts" | "tunnels" | "clients"
} = {}) {
  const t = useTranslations("vpnServerAdmin")
  const tSidebar = useTranslations("sidebar")
  const locale = useLocale()
  const router = useRouter()
  const tCommon = useTranslations("backupAdmin")
  const [data, setData] = useState<DashRecord>({})
  const [overview, setOverview] = useState<DashRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const [settingsOpen, setSettingsOpen] = useState(false)
  const [settingsForm, setSettingsForm] = useState({ label: "", public_ip: "" })

  const [hostOpen, setHostOpen] = useState(false)
  const [hostForm, setHostForm] = useState(emptyHostForm)

  const [inboundOpen, setInboundOpen] = useState(false)
  const [inboundForm, setInboundForm] = useState(emptyInboundForm)

  const [tunnelOpen, setTunnelOpen] = useState(false)
  const [tunnelForm, setTunnelForm] = useState(emptyTunnelForm)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      setData(await getAdminState("vpn_server"))
    } catch {
      setError(t("loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  const refreshOverview = useCallback(async () => {
    try {
      const res = await postAdminMutate("vpn_server_overview", {})
      if (res && typeof res === "object" && res.ok !== false) {
        setOverview(res as DashRecord)
      }
    } catch {
      /* ignore */
    }
  }, [])

  useEffect(() => {
    void load()
    void refreshOverview()
  }, [load, refreshOverview])

  const nodes = Array.isArray(data.xrayNodes) ? (data.xrayNodes as DashRecord[]) : []
  const hosts = Array.isArray(data.xrayHosts) ? (data.xrayHosts as DashRecord[]) : []
  const inbounds = Array.isArray(data.xrayInbounds) ? (data.xrayInbounds as DashRecord[]) : []
  const tunnels = Array.isArray(data.tunnels)
    ? (data.tunnels as DashRecord[])
    : Array.isArray(data.tunnelEndpoints)
      ? (data.tunnelEndpoints as DashRecord[])
      : Array.isArray(data.endpoints)
        ? (data.endpoints as DashRecord[])
        : []
  const core = data.xrayCore && typeof data.xrayCore === "object" ? (data.xrayCore as DashRecord) : {}
  const xrayCoreEnabled = data.xrayCoreEnabled !== false
  const tunnelEnabled = data.tunnelEnabled === true || bool(data.tunnelEnabled)

  const localNode = useMemo(
    () => nodes.find((n) => n.is_local === true || n.is_local === 1) ?? nodes[0] ?? null,
    [nodes]
  )

  const runOp = async (op: string, payload: DashRecord = {}) => {
    setBusy(true)
    setMessage(null)
    try {
      const res = await postAdminMutate(op, payload)
      if (!res.ok) {
        setMessage(res.message || t("mutateError"))
        return
      }
      setMessage(t("saved"))
      await load()
      await refreshOverview()
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
    setMessage(null)
    try {
      const res = await postAdminMutate("vpn_server_update", settingsForm)
      if (!res.ok) {
        setMessage(res.message || t("mutateError"))
        return
      }
      setMessage(t("saved"))
      setSettingsOpen(false)
      await load()
      await refreshOverview()
    } finally {
      setBusy(false)
    }
  }

  const saveHost = async () => {
    setBusy(true)
    setMessage(null)
    try {
      const op = hostForm.edit_id > 0 ? "xray_host_update" : "xray_host_add"
      const res = await postAdminMutate(op, {
        ...hostForm,
        id: hostForm.edit_id || undefined,
        port: num(hostForm.port),
        inbound_id: hostForm.inbound_id || undefined,
      })
      if (!res.ok) {
        setMessage(res.message || t("mutateError"))
        return
      }
      setMessage(t("saved"))
      setHostOpen(false)
      setHostForm(emptyHostForm())
      await load()
    } finally {
      setBusy(false)
    }
  }

  const saveInbound = async () => {
    setBusy(true)
    setMessage(null)
    try {
      const op = inboundForm.edit_id > 0 ? "xray_inbound_update" : "xray_inbound_add"
      const res = await postAdminMutate(op, {
        ...inboundForm,
        id: inboundForm.edit_id || undefined,
        port: num(inboundForm.port),
      })
      if (!res.ok) {
        setMessage(res.message || t("mutateError"))
        return
      }
      setMessage(t("saved"))
      setInboundOpen(false)
      await load()
      await refreshOverview()
    } finally {
      setBusy(false)
    }
  }

  const saveTunnel = async () => {
    setBusy(true)
    setMessage(null)
    try {
      const op = tunnelForm.edit_id > 0 ? "tunnel_update" : "tunnel_add"
      let cfg: unknown = tunnelForm.config_json
      try {
        cfg = JSON.parse(tunnelForm.config_json)
      } catch {
        /* keep string */
      }
      const res = await postAdminMutate(op, {
        ...tunnelForm,
        config_json: cfg,
        id: tunnelForm.edit_id || undefined,
        ssh_port: num(tunnelForm.ssh_port) || 22,
      })
      if (!res.ok) {
        setMessage(res.message || t("mutateError"))
        return
      }
      setMessage(t("saved"))
      setTunnelOpen(false)
      setTunnelForm(emptyTunnelForm())
      await load()
    } finally {
      setBusy(false)
    }
  }

  const nodeData = (overview?.node as DashRecord | undefined) ?? localNode ?? {}
  const healthStatus = String(
    (overview?.health as DashRecord | undefined)?.status ?? localNode?.last_health_status ?? core.status ?? "unknown"
  )
  const inboundCount = num(overview?.inbound_count ?? inbounds.filter((i) => i.active !== false).length)
  const clientCount = num(overview?.client_count)

  const resolvedDefaultTab = useMemo(() => {
    const fallback = xrayCoreEnabled ? "overview" : tunnelEnabled ? "tunnels" : "clients"
    if (!defaultTab) return fallback
    if (defaultTab === "clients") return "clients"
    if (defaultTab === "tunnels" && !tunnelEnabled) return fallback
    if (defaultTab !== "tunnels" && !xrayCoreEnabled) return tunnelEnabled ? "tunnels" : "clients"
    return defaultTab
  }, [defaultTab, xrayCoreEnabled, tunnelEnabled])

  const [hubTab, setHubTab] = useState(resolvedDefaultTab)
  useEffect(() => {
    setHubTab(resolvedDefaultTab)
  }, [resolvedDefaultTab])

  const syncHubTabUrl = useCallback(
    (tab: string) => {
      setHubTab(tab as typeof resolvedDefaultTab)
      const map: Record<string, string> = {
        overview: "xray_core",
        inbounds: "xray_inbounds",
        hosts: "xray_hosts",
        tunnels: "tunnel_nodes",
        clients: "vpn_server",
      }
      const slug = map[tab] ?? "vpn_server"
      router.replace(`/${locale}/dashboard/${slug}`)
    },
    [locale, router]
  )

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <Button type="button" variant="outline" size="sm" disabled={loading || busy} onClick={() => void load()}>
          {t("refresh")}
        </Button>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {message ? <p className="text-sm text-muted-foreground">{message}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}

      <Tabs value={hubTab} onValueChange={syncHubTabUrl} key={resolvedDefaultTab}>
        <TabsList variant="line" className="h-auto flex-wrap">
          {xrayCoreEnabled ? <TabsTrigger value="overview">{t("tabOverview")}</TabsTrigger> : null}
          {xrayCoreEnabled ? <TabsTrigger value="inbounds">{t("tabInbounds")}</TabsTrigger> : null}
          {xrayCoreEnabled ? <TabsTrigger value="hosts">{t("tabHosts")}</TabsTrigger> : null}
          {tunnelEnabled ? <TabsTrigger value="tunnels">{t("tabTunnels")}</TabsTrigger> : null}
          <TabsTrigger value="clients">{t("tabClients")}</TabsTrigger>
        </TabsList>

        {xrayCoreEnabled ? (
          <TabsContent value="overview" className="mt-4 space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t("coreTitle")}</CardTitle>
                <CardDescription>{t("coreHint")}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-medium">{String(nodeData.label ?? t("localNode"))}</span>
                  <Badge variant="outline">{healthStatus}</Badge>
                  <Badge variant="secondary">
                    {t("coreVersion")}: {String(core.version ?? "—")}
                  </Badge>
                </div>
                <div className="grid gap-2 text-sm text-muted-foreground sm:grid-cols-2">
                  <div>
                    {t("publicIp")}: {String(nodeData.public_ip ?? "—")}
                  </div>
                  <div>
                    {t("inbounds")}: {inboundCount}
                  </div>
                  <div>
                    {t("clients")}: {clientCount}
                  </div>
                  <div>
                    {t("agent")}: {String(overview?.agent_url ?? "env")}
                  </div>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => void runOp("vpn_server_health")}>
                    {t("health")}
                  </Button>
                  <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => void runOp("vpn_server_apply")}>
                    {t("applyConfig")}
                  </Button>
                  <Button type="button" size="sm" disabled={busy} onClick={() => void runOp("vpn_server_restart")}>
                    {t("restart")}
                  </Button>
                  <Button type="button" size="sm" variant="secondary" disabled={busy} onClick={openSettings}>
                    {t("editSettings")}
                  </Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        ) : null}

        {xrayCoreEnabled ? (
          <TabsContent value="inbounds" className="mt-4 space-y-4">
            <div className="flex justify-end">
              <Button
                type="button"
                size="sm"
                onClick={() => {
                  setInboundForm(emptyInboundForm())
                  setInboundOpen(true)
                }}
              >
                {t("add")}
              </Button>
            </div>
            <div className="overflow-x-auto rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("tag")}</TableHead>
                    <TableHead>{t("protocol")}</TableHead>
                    <TableHead>{t("port")}</TableHead>
                    <TableHead>{t("remark")}</TableHead>
                    <TableHead>{t("actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {inbounds.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-muted-foreground">
                        {t("emptyInbounds")}
                      </TableCell>
                    </TableRow>
                  ) : (
                    inbounds.map((row, i) => (
                      <TableRow key={String(row.id ?? i)}>
                        <TableCell>{String(row.tag ?? "—")}</TableCell>
                        <TableCell>{String(row.protocol ?? "—")}</TableCell>
                        <TableCell dir="ltr">{String(row.port ?? "—")}</TableCell>
                        <TableCell>{String(row.remark ?? row.tag ?? "—")}</TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={busy}
                              onClick={() => {
                                setInboundForm({
                                  edit_id: num(row.id),
                                  tag: String(row.tag ?? ""),
                                  remark: String(row.remark ?? ""),
                                  protocol: String(row.protocol ?? "vless"),
                                  port: String(row.port ?? "443"),
                                  settings_json: String(row.settings_json ?? "{}"),
                                  stream_settings_json: String(row.stream_settings_json ?? "{}"),
                                })
                                setInboundOpen(true)
                              }}
                            >
                              {t("edit")}
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="destructive"
                              disabled={busy}
                              onClick={() => void runOp("xray_inbound_delete", { id: num(row.id) })}
                            >
                              {t("delete")}
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </div>
          </TabsContent>
        ) : null}

        {xrayCoreEnabled ? (
          <TabsContent value="hosts" className="mt-4 space-y-4">
            <div className="flex justify-end">
              <Button
                type="button"
                size="sm"
                onClick={() => {
                  setHostForm({
                    ...emptyHostForm(),
                    address: String(localNode?.public_ip ?? ""),
                    inbound_id: num(inbounds[0]?.id),
                  })
                  setHostOpen(true)
                }}
              >
                {t("addHost")}
              </Button>
            </div>
            <div className="overflow-x-auto rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("remark")}</TableHead>
                    <TableHead>{t("address")}</TableHead>
                    <TableHead>{t("port")}</TableHead>
                    <TableHead>{t("inbound")}</TableHead>
                    <TableHead>{t("sni")}</TableHead>
                    <TableHead>{t("actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {hosts.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center text-muted-foreground">
                        {t("emptyHosts")}
                      </TableCell>
                    </TableRow>
                  ) : (
                    hosts.map((row, i) => (
                      <TableRow key={String(row.id ?? i)}>
                        <TableCell>{String(row.remark ?? "—")}</TableCell>
                        <TableCell dir="ltr">{String(row.address ?? "—")}</TableCell>
                        <TableCell dir="ltr">{num(row.port) || t("auto")}</TableCell>
                        <TableCell className="tabular-nums" dir="ltr">
                          #{num(row.inbound_id) || "—"}
                        </TableCell>
                        <TableCell dir="ltr">{String(row.sni ?? "—")}</TableCell>
                        <TableCell>
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={busy}
                            onClick={() => {
                              setHostForm({
                                edit_id: num(row.id),
                                remark: String(row.remark ?? ""),
                                address: String(row.address ?? ""),
                                port: String(row.port ?? "0"),
                                inbound_id: num(row.inbound_id),
                                sni: String(row.sni ?? ""),
                                host: String(row.host ?? ""),
                                path: String(row.path ?? ""),
                                fingerprint: String(row.fingerprint ?? "chrome"),
                              })
                              setHostOpen(true)
                            }}
                          >
                            {t("edit")}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </div>
          </TabsContent>
        ) : null}

        {tunnelEnabled ? (
          <TabsContent value="tunnels" className="mt-4 space-y-4">
            <div className="flex justify-end">
              <Button
                type="button"
                size="sm"
                onClick={() => {
                  setTunnelForm(emptyTunnelForm())
                  setTunnelOpen(true)
                }}
              >
                {t("add")}
              </Button>
            </div>
            <div className="overflow-x-auto rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("label")}</TableHead>
                    <TableHead>{t("provider")}</TableHead>
                    <TableHead>{t("sshHost")}</TableHead>
                    <TableHead>{t("publicIp")}</TableHead>
                    <TableHead>{t("status")}</TableHead>
                    <TableHead>{t("actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {tunnels.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center text-muted-foreground">
                        {t("emptyTunnels")}
                      </TableCell>
                    </TableRow>
                  ) : (
                    tunnels.map((row, i) => (
                      <TableRow key={String(row.id ?? i)}>
                        <TableCell>{String(row.name ?? row.label ?? row.remark ?? "—")}</TableCell>
                        <TableCell>{String(row.provider ?? "—")}</TableCell>
                        <TableCell dir="ltr">{String(row.ssh_host ?? row.endpoint ?? "—")}</TableCell>
                        <TableCell dir="ltr">{String(row.public_ip ?? row.address ?? "—")}</TableCell>
                        <TableCell>
                          <Badge variant="secondary">{String(row.health_status ?? row.status ?? "—")}</Badge>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={busy}
                              onClick={() => {
                                const cfg =
                                  typeof row.config_json === "string"
                                    ? row.config_json
                                    : row.config_json && typeof row.config_json === "object"
                                      ? JSON.stringify(row.config_json)
                                      : emptyTunnelForm().config_json
                                setTunnelForm({
                                  edit_id: num(row.id),
                                  label: String(row.label ?? row.name ?? ""),
                                  provider: String(row.provider ?? "frp"),
                                  public_ip: String(row.public_ip ?? ""),
                                  ssh_host: String(row.ssh_host ?? ""),
                                  ssh_port: String(row.ssh_port ?? "22"),
                                  ssh_user: String(row.ssh_user ?? "root"),
                                  config_json: String(cfg),
                                })
                                setTunnelOpen(true)
                              }}
                            >
                              {t("edit")}
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              disabled={busy}
                              onClick={() => void runOp("tunnel_deploy", { id: num(row.id) })}
                            >
                              {t("deploy")}
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              variant="destructive"
                              disabled={busy}
                              onClick={() => void runOp("tunnel_delete", { id: num(row.id) })}
                            >
                              {t("delete")}
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </div>
          </TabsContent>
        ) : null}

        <TabsContent value="clients" className="mt-4 space-y-4" data-testid="vpn-server-clients-tab">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("tabClients")}</CardTitle>
              <CardDescription>{t("clientsHint")}</CardDescription>
            </CardHeader>
            <CardContent>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => router.push(`/${locale}/dashboard/configs`)}
              >
                {tSidebar("items.configs")}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Sheet open={settingsOpen} onOpenChange={setSettingsOpen}>
        <DashSheetContent>
          <SheetHeader>
            <SheetTitle>{t("localSettings")}</SheetTitle>
          </SheetHeader>
          <div className="grid gap-3 py-4">
            <div className="space-y-1.5">
              <Label>{t("label")}</Label>
              <Input
                value={settingsForm.label}
                onChange={(e) => setSettingsForm((f) => ({ ...f, label: e.target.value }))}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("publicIp")}</Label>
              <Input
                dir="ltr"
                value={settingsForm.public_ip}
                onChange={(e) => setSettingsForm((f) => ({ ...f, public_ip: e.target.value }))}
              />
            </div>
          </div>
          <SheetFooter className="flex-row gap-2">
            <Button type="button" variant="outline" onClick={() => setSettingsOpen(false)}>
              {tCommon("cancel")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void saveSettings()}>
              {t("save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

      <Sheet open={hostOpen} onOpenChange={setHostOpen}>
        <DashSheetContent>
          <SheetHeader>
            <SheetTitle>{hostForm.edit_id > 0 ? t("edit") : t("addHost")}</SheetTitle>
          </SheetHeader>
          <div className="grid gap-3 py-4">
            <div className="space-y-1.5">
              <Label>{t("inbound")}</Label>
              <DashSelect
                value={String(hostForm.inbound_id || inbounds[0]?.id || "")}
                onValueChange={(v) => setHostForm((f) => ({ ...f, inbound_id: num(v) }))}
                options={inbounds.map((i) => ({
                  value: String(i.id),
                  label: String(i.tag ?? i.id),
                }))}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("remark")}</Label>
              <Input value={hostForm.remark} onChange={(e) => setHostForm((f) => ({ ...f, remark: e.target.value }))} />
            </div>
            <div className="space-y-1.5">
              <Label>{t("address")}</Label>
              <Input
                dir="ltr"
                value={hostForm.address}
                onChange={(e) => setHostForm((f) => ({ ...f, address: e.target.value }))}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("portHint")}</Label>
              <Input
                dir="ltr"
                value={hostForm.port}
                onChange={(e) => setHostForm((f) => ({ ...f, port: e.target.value }))}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("sni")}</Label>
              <Input dir="ltr" value={hostForm.sni} onChange={(e) => setHostForm((f) => ({ ...f, sni: e.target.value }))} />
            </div>
            <div className="space-y-1.5">
              <Label>{t("host")}</Label>
              <Input dir="ltr" value={hostForm.host} onChange={(e) => setHostForm((f) => ({ ...f, host: e.target.value }))} />
            </div>
            <div className="space-y-1.5">
              <Label>{t("path")}</Label>
              <Input dir="ltr" value={hostForm.path} onChange={(e) => setHostForm((f) => ({ ...f, path: e.target.value }))} />
            </div>
            <div className="space-y-1.5">
              <Label>{t("fingerprint")}</Label>
              <Input
                dir="ltr"
                value={hostForm.fingerprint}
                onChange={(e) => setHostForm((f) => ({ ...f, fingerprint: e.target.value }))}
              />
            </div>
          </div>
          <SheetFooter className="flex-row gap-2">
            <Button type="button" variant="outline" onClick={() => setHostOpen(false)}>
              {tCommon("cancel")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void saveHost()}>
              {t("save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

      <Sheet open={inboundOpen} onOpenChange={setInboundOpen}>
        <DashSheetContent>
          <SheetHeader>
            <SheetTitle>{inboundForm.edit_id > 0 ? t("edit") : t("add")}</SheetTitle>
          </SheetHeader>
          <div className="grid gap-3 py-4">
            <Input placeholder={t("tag")} value={inboundForm.tag} onChange={(e) => setInboundForm((f) => ({ ...f, tag: e.target.value }))} />
            <Input
              placeholder={t("remark")}
              value={inboundForm.remark}
              onChange={(e) => setInboundForm((f) => ({ ...f, remark: e.target.value }))}
            />
            <Input
              placeholder={t("protocol")}
              value={inboundForm.protocol}
              onChange={(e) => setInboundForm((f) => ({ ...f, protocol: e.target.value }))}
            />
            <Input
              placeholder={t("port")}
              dir="ltr"
              value={inboundForm.port}
              onChange={(e) => setInboundForm((f) => ({ ...f, port: e.target.value }))}
            />
            <div className="space-y-1.5">
              <Label>{t("settingsJson")}</Label>
              <Textarea
                value={inboundForm.settings_json}
                onChange={(e) => setInboundForm((f) => ({ ...f, settings_json: e.target.value }))}
                rows={3}
                dir="ltr"
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("streamJson")}</Label>
              <Textarea
                value={inboundForm.stream_settings_json}
                onChange={(e) => setInboundForm((f) => ({ ...f, stream_settings_json: e.target.value }))}
                rows={3}
                dir="ltr"
              />
            </div>
          </div>
          <SheetFooter className="flex-row gap-2">
            <Button type="button" variant="outline" onClick={() => setInboundOpen(false)}>
              {tCommon("cancel")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void saveInbound()}>
              {t("save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

      <Sheet open={tunnelOpen} onOpenChange={setTunnelOpen}>
        <DashSheetContent>
          <SheetHeader>
            <SheetTitle>{tunnelForm.edit_id > 0 ? t("edit") : t("add")}</SheetTitle>
          </SheetHeader>
          <div className="grid gap-3 py-4">
            <div className="space-y-1.5">
              <Label>{t("label")}</Label>
              <Input
                value={tunnelForm.label}
                onChange={(e) => setTunnelForm((f) => ({ ...f, label: e.target.value }))}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("provider")}</Label>
              <DashSelect
                value={tunnelForm.provider}
                onValueChange={(v) => setTunnelForm((f) => ({ ...f, provider: v }))}
                options={PROVIDERS.map((p) => ({ value: p, label: p }))}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("publicIp")}</Label>
              <Input
                dir="ltr"
                value={tunnelForm.public_ip}
                onChange={(e) => setTunnelForm((f) => ({ ...f, public_ip: e.target.value }))}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("sshHost")}</Label>
              <Input
                dir="ltr"
                value={tunnelForm.ssh_host}
                onChange={(e) => setTunnelForm((f) => ({ ...f, ssh_host: e.target.value }))}
              />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>{t("sshPort")}</Label>
                <Input
                  dir="ltr"
                  value={tunnelForm.ssh_port}
                  onChange={(e) => setTunnelForm((f) => ({ ...f, ssh_port: e.target.value }))}
                />
              </div>
              <div className="space-y-1.5">
                <Label>{t("sshUser")}</Label>
                <Input
                  dir="ltr"
                  value={tunnelForm.ssh_user}
                  onChange={(e) => setTunnelForm((f) => ({ ...f, ssh_user: e.target.value }))}
                />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>{t("configJson")}</Label>
              <Textarea
                dir="ltr"
                rows={4}
                value={tunnelForm.config_json}
                onChange={(e) => setTunnelForm((f) => ({ ...f, config_json: e.target.value }))}
              />
            </div>
          </div>
          <SheetFooter className="flex-row gap-2">
            <Button type="button" variant="outline" onClick={() => setTunnelOpen(false)}>
              {tCommon("cancel")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void saveTunnel()}>
              {t("save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>
    </div>
  )
}
