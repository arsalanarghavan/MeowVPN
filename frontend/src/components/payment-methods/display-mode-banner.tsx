"use client"

import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { DashSelect } from "@/components/dash-select"
import { cn } from "@/lib/utils"

export type CardsDisplayMode = "list" | "sequential" | "random"

export function DisplayModeBanner({
  label,
  displayMode,
  onDisplayModeChange,
  onSaveDisplayMode,
  savingDisplayMode,
  canEditDisplayMode,
  c2cEnabled,
  onC2cEnabledChange,
  onSaveC2cToggle,
  savingC2cToggle,
  canSaveC2cToggle,
  saveDisplayModeLabel,
  saveTogglesLabel,
  tp,
}: {
  label: string
  displayMode: CardsDisplayMode
  onDisplayModeChange: (mode: CardsDisplayMode) => void
  onSaveDisplayMode: () => void
  savingDisplayMode: boolean
  canEditDisplayMode: boolean
  c2cEnabled: boolean
  onC2cEnabledChange: (checked: boolean) => void
  onSaveC2cToggle: () => void
  savingC2cToggle: boolean
  canSaveC2cToggle: boolean
  saveDisplayModeLabel: string
  saveTogglesLabel: string
  tp: (k: string) => string
}) {
  return (
    <div
      className={cn(
        "flex flex-col gap-3 rounded-lg border border-border/60 bg-muted/30 px-3 py-2.5 sm:flex-row sm:flex-wrap sm:items-center"
      )}
    >
      {canEditDisplayMode ? (
        <>
          <Label className="shrink-0 text-xs text-muted-foreground sm:min-w-[8rem]">{label}</Label>
          <DashSelect
            triggerClassName="h-8 w-full min-w-[10rem] sm:w-auto"
            value={displayMode}
            onValueChange={(v) => onDisplayModeChange((v as CardsDisplayMode) || "list")}
            options={[
              { value: "list", label: tp("displayModeList") },
              { value: "sequential", label: tp("displayModeSequential") },
              { value: "random", label: tp("displayModeRandom") },
            ]}
          />
          <Button type="button" size="sm" variant="secondary" disabled={savingDisplayMode} onClick={onSaveDisplayMode}>
            {saveDisplayModeLabel}
          </Button>
        </>
      ) : null}
      {canSaveC2cToggle ? (
        <div className="flex flex-wrap items-center gap-2 sm:ms-auto">
          <Label htmlFor="pm-c2c-banner" className="text-xs font-medium">
            {tp("paymentMethod_c2c")}
          </Label>
          <Switch
            id="pm-c2c-banner"
            checked={c2cEnabled}
            onCheckedChange={onC2cEnabledChange}
            disabled={savingC2cToggle}
          />
          <Button type="button" size="sm" variant="outline" disabled={savingC2cToggle} onClick={onSaveC2cToggle}>
            {saveTogglesLabel}
          </Button>
        </div>
      ) : null}
    </div>
  )
}
