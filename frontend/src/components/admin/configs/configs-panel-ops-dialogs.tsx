"use client"

import { useEffect, useState } from "react"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogTitle } from "@/components/ui/dialog"

type Tl = (k: string, opts?: Record<string, string | number>) => string

export function ConfigsPanelOpConfirmDialog({
  open,
  onOpenChange,
  titleKey,
  hintKey,
  confirmKey,
  ackKey,
  busy,
  onConfirm,
  tl,
  contentClass,
  dialogHeaderClass,
  destructive = true,
}: {
  open: boolean
  onOpenChange: (v: boolean) => void
  titleKey: string
  hintKey: string
  confirmKey: string
  ackKey: string
  busy: boolean
  onConfirm: () => void
  tl: Tl
  contentClass: string
  dialogHeaderClass: string
  destructive?: boolean
}) {
  const [ack, setAck] = useState(false)
  const [typed, setTyped] = useState("")
  const valid = ack && typed.trim().toUpperCase() === "CONFIRM"

  return (
    <Dialog
      open={open}
      onOpenChange={(v) => {
        if (!v) {
          setAck(false)
          setTyped("")
        }
        onOpenChange(v)
      }}
    >
      <DashDialogContent className={contentClass}>
        <DashDialogHeader className={dialogHeaderClass}>
          <DialogTitle>{tl(titleKey)}</DialogTitle>
        </DashDialogHeader>
        <p className="text-sm text-muted-foreground">{tl(hintKey)}</p>
        <div className="grid gap-3">
          <div className="grid gap-2">
            <Label>{tl(confirmKey)}</Label>
            <Input value={typed} onChange={(e) => setTyped(e.target.value)} placeholder="CONFIRM" dir="ltr" />
          </div>
          <label className="flex items-start gap-2 text-sm">
            <input type="checkbox" className="mt-1 size-4" checked={ack} onChange={(e) => setAck(e.target.checked)} />
            <span>{tl(ackKey)}</span>
          </label>
        </div>
        <DashDialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {tl("cancel")}
          </Button>
          <Button
            type="button"
            variant={destructive ? "destructive" : "default"}
            disabled={busy || !valid}
            onClick={onConfirm}
          >
            {tl(titleKey)}
          </Button>
        </DashDialogFooter>
      </DashDialogContent>
    </Dialog>
  )
}

export type AttachInboundOption = { id: number; label: string }

export function ConfigsAttachInboundsDialog({
  open,
  onOpenChange,
  mode,
  email,
  emails,
  inbounds,
  busy,
  onConfirm,
  tl,
  contentClass,
  dialogHeaderClass,
}: {
  open: boolean
  onOpenChange: (v: boolean) => void
  mode: "single" | "bulk"
  email?: string
  emails?: string[]
  inbounds: AttachInboundOption[]
  busy: boolean
  onConfirm: (attachIds: number[], detachIds: number[]) => void
  tl: Tl
  contentClass: string
  dialogHeaderClass: string
}) {
  const [attachIds, setAttachIds] = useState<number[]>([])
  const [detachIds, setDetachIds] = useState<number[]>([])

  useEffect(() => {
    if (!open) {
      setAttachIds([])
      setDetachIds([])
    }
  }, [open])

  const toggle = (list: number[], id: number, checked: boolean) => {
    if (checked) return [...list, id]
    return list.filter((x) => x !== id)
  }

  const count = mode === "bulk" ? (emails?.length ?? 0) : 1
  const valid = (attachIds.length > 0 || detachIds.length > 0) && count > 0

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DashDialogContent className={contentClass}>
        <DashDialogHeader className={dialogHeaderClass}>
          <DialogTitle>{mode === "bulk" ? tl("attachInboundsBulkTitle") : tl("attachInboundsTitle")}</DialogTitle>
        </DashDialogHeader>
        <p className="text-sm text-muted-foreground">
          {mode === "bulk"
            ? tl("attachInboundsBulkHint", { n: count })
            : tl("attachInboundsHint", { email: email ?? "" })}
        </p>
        <div className="grid max-h-64 gap-4 overflow-y-auto">
          <div className="grid gap-2">
            <Label>{tl("attachInboundsAttach")}</Label>
            {inbounds.length < 1 ? (
              <p className="text-xs text-muted-foreground">{tl("noInbounds")}</p>
            ) : (
              <div className="space-y-1">
                {inbounds.map((inb) => {
                  const checked = attachIds.includes(inb.id)
                  return (
                    <label
                      key={`a-${inb.id}`}
                      className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-muted/50"
                    >
                      <input
                        type="checkbox"
                        className="size-4"
                        checked={checked}
                        onChange={(e) => setAttachIds((prev) => toggle(prev, inb.id, e.target.checked))}
                      />
                      <span className="truncate">{inb.label}</span>
                    </label>
                  )
                })}
              </div>
            )}
          </div>
          <div className="grid gap-2">
            <Label>{tl("attachInboundsDetach")}</Label>
            {inbounds.length < 1 ? (
              <p className="text-xs text-muted-foreground">{tl("noInbounds")}</p>
            ) : (
              <div className="space-y-1">
                {inbounds.map((inb) => {
                  const checked = detachIds.includes(inb.id)
                  return (
                    <label
                      key={`d-${inb.id}`}
                      className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-muted/50"
                    >
                      <input
                        type="checkbox"
                        className="size-4"
                        checked={checked}
                        onChange={(e) => setDetachIds((prev) => toggle(prev, inb.id, e.target.checked))}
                      />
                      <span className="truncate">{inb.label}</span>
                    </label>
                  )
                })}
              </div>
            )}
          </div>
        </div>
        <DashDialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {tl("cancel")}
          </Button>
          <Button type="button" disabled={busy || !valid} onClick={() => onConfirm(attachIds, detachIds)}>
            {tl("save")}
          </Button>
        </DashDialogFooter>
      </DashDialogContent>
    </Dialog>
  )
}

export function ConfigsExpiredOlderDeleteDialog({
  open,
  onOpenChange,
  count,
  minDays,
  busy,
  onConfirm,
  tl,
  contentClass,
  dialogHeaderClass,
}: {
  open: boolean
  onOpenChange: (v: boolean) => void
  count: number
  minDays: number
  busy: boolean
  onConfirm: () => void
  tl: Tl
  contentClass: string
  dialogHeaderClass: string
}) {
  const [ack, setAck] = useState(false)
  const [typed, setTyped] = useState("")
  const valid = ack && typed.trim() === String(count) && count > 0

  useEffect(() => {
    if (!open) {
      setAck(false)
      setTyped("")
    }
  }, [open])

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DashDialogContent className={contentClass}>
        <DashDialogHeader className={dialogHeaderClass}>
          <DialogTitle>{tl("expiredOlderDeleteAll")}</DialogTitle>
        </DashDialogHeader>
        <p className="text-sm text-muted-foreground">
          {tl("expiredOlderDeleteHint", { count, minDays })}
        </p>
        <div className="grid gap-3">
          <div className="grid gap-2">
            <Label>{tl("confirmCount")}</Label>
            <Input value={typed} onChange={(e) => setTyped(e.target.value)} dir="ltr" />
          </div>
          <label className="flex items-start gap-2 text-sm">
            <input type="checkbox" className="mt-1 size-4" checked={ack} onChange={(e) => setAck(e.target.checked)} />
            <span>{tl("expiredOlderDeleteAck")}</span>
          </label>
        </div>
        <DashDialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {tl("cancel")}
          </Button>
          <Button type="button" variant="destructive" disabled={busy || !valid} onClick={onConfirm}>
            {tl("expiredOlderDeleteAll")}
          </Button>
        </DashDialogFooter>
      </DashDialogContent>
    </Dialog>
  )
}
