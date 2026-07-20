"use client"

import {
  DndContext,
  PointerSensor,
  closestCenter,
  type DragEndEvent,
  useSensor,
  useSensors,
} from "@dnd-kit/core"
import {
  SortableContext,
  arrayMove,
  rectSortingStrategy,
  useSortable,
} from "@dnd-kit/sortable"
import { CSS } from "@dnd-kit/utilities"
import { EllipsisVerticalIcon, GripVertical } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { DashPage } from "@/components/dash-page"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
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
import { formatRedactedCardNumber } from "@/lib/redact-card-number"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"
import { mainEnabledPlatforms } from "@/lib/enabled-platforms"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

type DashRecord = Record<string, unknown>

const METHOD_KEYS = ["c2c", "crypto", "crypto_auto"] as const
const PAYMENT_METHOD_KEYS = [
  "c2c",
  "crypto",
  "crypto_auto",
  "bale_wallet",
  "site_wallet",
  "wallet_topup",
] as const
type PaymentMethodKey = (typeof PAYMENT_METHOD_KEYS)[number]

export type PaymentMethodsPayload = {
  effective: Record<string, boolean>
  site?: Record<string, boolean>
  resellerOverride?: Record<string, boolean>
}

function defaultPaymentMethodsMap(): Record<PaymentMethodKey, boolean> {
  return {
    c2c: true,
    crypto: true,
    crypto_auto: true,
    bale_wallet: true,
    site_wallet: true,
    wallet_topup: true,
  }
}

function parsePaymentMethodsMap(raw: unknown): Record<PaymentMethodKey, boolean> {
  const base = defaultPaymentMethodsMap()
  if (!raw || typeof raw !== "object") return base
  for (const k of PAYMENT_METHOD_KEYS) {
    if (k in (raw as Record<string, unknown>)) {
      base[k] = Boolean((raw as Record<string, unknown>)[k])
    }
  }
  return base
}
type CardsDisplayMode = "list" | "sequential" | "random"

function parseCardsDisplayMode(raw: unknown): CardsDisplayMode {
  const v = String(raw ?? "list")
  if (v === "sequential" || v === "random") return v
  return "list"
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isActiveRow(c: DashRecord): boolean {
  return c.active === true || c.active === 1 || c.active === "1"
}

type CardFormState = {
  edit_id: number
  card_number: string
  holder_name: string
  bank_name: string
  method_key: (typeof METHOD_KEYS)[number]
  daily_limit: number
  note: string
  active: boolean
}

function emptyForm(): CardFormState {
  return {
    edit_id: 0,
    card_number: "",
    holder_name: "",
    bank_name: "",
    method_key: "c2c",
    daily_limit: 0,
    note: "",
    active: true,
  }
}

function formFromRow(c: DashRecord): CardFormState {
  const mk = String(c.method_key ?? "c2c")
  const method_key = METHOD_KEYS.includes(mk as (typeof METHOD_KEYS)[number])
    ? (mk as (typeof METHOD_KEYS)[number])
    : "c2c"
  return {
    edit_id: num(c.id),
    card_number: String(c.card_number ?? ""),
    holder_name: String(c.holder_name ?? ""),
    bank_name: String(c.bank_name ?? ""),
    method_key,
    daily_limit: num(c.daily_limit),
    note: String(c.note ?? ""),
    active: isActiveRow(c),
  }
}

const CARD_PAYMENT_METHOD_KEYS = ["c2c", "crypto", "crypto_auto"] as const
const WALLET_PAYMENT_METHOD_KEYS = ["bale_wallet", "site_wallet", "wallet_topup"] as const

function shortenCryptoAddress(raw: unknown): string {
  const s = String(raw ?? "").trim()
  if (s.length <= 14) return s || "—"
  return `${s.slice(0, 6)}…${s.slice(-4)}`
}

function isCryptoMethodKey(mk: unknown): boolean {
  const k = String(mk ?? "")
  return k === "crypto" || k === "crypto_auto"
}

function formFieldLabels(
  methodKey: (typeof METHOD_KEYS)[number],
  tp: (k: string) => string
): {
  primary: string
  secondary: string
  tertiary: string
  note: string
  noteHint?: string
  cryptoAutoHint?: string
} {
  if (methodKey === "crypto") {
    return {
      primary: tp("field_walletAddress"),
      secondary: tp("field_network"),
      tertiary: tp("field_label"),
      note: tp("field_memo"),
      noteHint: tp("field_memoHint"),
    }
  }
  if (methodKey === "crypto_auto") {
    return {
      primary: tp("field_label"),
      secondary: tp("field_network"),
      tertiary: tp("field_cryptoAutoPlaceholder"),
      note: tp("note"),
      cryptoAutoHint: tp("field_cryptoAutoHint"),
    }
  }
  return {
    primary: tp("cardNumber"),
    secondary: tp("holderName"),
    tertiary: tp("bankName"),
    note: tp("note"),
  }
}

function formatMutateError(raw: string | undefined, tp: (k: string) => string): string {
  if (!raw) return tp("mutateError")
  if (raw === "invalid_html_response" || raw.startsWith("bad_json")) return tp("invalidHtmlResponse")
  return raw
}

function SortableCardTile({
  c,
  canDrag,
  busy,
  saving,
  tp,
  methodLabel,
  cardSubtitle,
  onToggleActive,
  onEdit,
  onDelete,
}: {
  c: DashRecord
canDrag: boolean
  busy: boolean
  saving: boolean
  tp: (k: string) => string
  methodLabel: (raw: unknown) => string
  cardSubtitle: (c: DashRecord) => string
  onToggleActive: (c: DashRecord, checked: boolean) => void
  onEdit: (c: DashRecord) => void
  onDelete: (c: DashRecord) => void
}) {
  const { isFa } = useDashLocale()

  const id = num(c.id)
  const act = isActiveRow(c)
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    disabled: !canDrag || busy || saving,
  })
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  return (
    <div ref={setNodeRef} style={style} className={cn(isDragging && "z-10 opacity-80")}>
      <Card>
        <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
          <div className="flex min-w-0 flex-1 items-start gap-2">
            {canDrag ? (
              <button
                type="button"
                className="mt-0.5 shrink-0 cursor-grab touch-none text-muted-foreground active:cursor-grabbing"
                aria-label={tp("dragHint")}
                {...attributes}
                {...listeners}
              >
                <GripVertical className="size-4" />
              </button>
            ) : null}
            <div className="min-w-0 space-y-1">
              <CardTitle className="text-base">{String(c.bank_name ?? "—")}</CardTitle>
              <CardDescription className="font-mono text-xs">{cardSubtitle(c)}</CardDescription>
            </div>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <Switch
              checked={act}
              disabled={busy || saving}
              onCheckedChange={(checked) => onToggleActive(c, checked)}
              aria-label={act ? tp("badgeActive") : tp("badgeInactive")}
            />
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="icon" className="size-8">
                  <EllipsisVerticalIcon className="size-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align={isFa ? "start" : "end"}>
                <DropdownMenuItem onClick={() => onEdit(c)}>{tp("edit")}</DropdownMenuItem>
                <DropdownMenuItem className="text-destructive" onClick={() => onDelete(c)}>
                  {tp("delete")}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </CardHeader>
        <CardContent className="text-xs text-muted-foreground">
          <span>
            {tp("method")}: {methodLabel(c.method_key)}
          </span>
          {" · "}
          <span>
            {tp("dailyLimit")}: {formatNumber(num(c.daily_limit), isFa)}
          </span>
        </CardContent>
      </Card>
    </div>
  )
}

export function DashboardCardsAdmin({
  cards,
  pagination,
  settings,
  paymentMethods,
  canEditDisplayMode = true,
  isReseller = false,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  cards: DashRecord[]
  pagination: PaginationMeta | null
  settings?: DashRecord
  paymentMethods?: PaymentMethodsPayload | null
  /** Site admin only; resellers cannot save global cards_display_mode. */
  canEditDisplayMode?: boolean
  isReseller?: boolean
onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const tp = (k: string) => t(`cardsAdmin.${k}`)

  const [filter, setFilter] = useState<"all" | "active" | "inactive">("all")
  const [sheetOpen, setSheetOpen] = useState(false)
  const [formMode, setFormMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<CardFormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)
  const [displayMode, setDisplayMode] = useState<CardsDisplayMode>(parseCardsDisplayMode(settings?.cards_display_mode))
  const [paymentMethodsMap, setPaymentMethodsMap] = useState<Record<PaymentMethodKey, boolean>>(() =>
    parsePaymentMethodsMap(paymentMethods?.effective ?? settings?.payment_methods)
  )
  const [savingPaymentMethods, setSavingPaymentMethods] = useState(false)
  const [orderedCards, setOrderedCards] = useState<DashRecord[]>(cards)
  const [reordering, setReordering] = useState(false)
  const cryptoOn = useMemo(() => {
    const f = settings?.features
    return !!(f && typeof f === "object" && (f as Record<string, unknown>).crypto === true)
  }, [settings?.features])
  const cardPaymentMethodKeys = useMemo(
    () =>
      CARD_PAYMENT_METHOD_KEYS.filter((key) => {
        if (key === "crypto" || key === "crypto_auto") return cryptoOn
        return true
      }),
    [cryptoOn]
  )
  const formMethodKeys = useMemo(
    () => METHOD_KEYS.filter((key) => (key === "crypto" || key === "crypto_auto" ? cryptoOn : true)),
    [cryptoOn]
  )
  const walletPaymentMethodKeys = WALLET_PAYMENT_METHOD_KEYS.filter(
    (key) => key !== "bale_wallet" || mainEnabledPlatforms(settings).includes("bale")
  )
  useEffect(() => {
    setDisplayMode(parseCardsDisplayMode(settings?.cards_display_mode))
  }, [settings?.cards_display_mode])
  useEffect(() => {
    setPaymentMethodsMap(parsePaymentMethodsMap(paymentMethods?.effective ?? settings?.payment_methods))
  }, [paymentMethods?.effective, settings?.payment_methods])
  useEffect(() => {
    setOrderedCards(cards)
  }, [cards])

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 8 },
    })
  )

  const stats = useMemo(() => {
    const slice = cards.length
    let active = 0
    for (const c of cards) {
      if (isActiveRow(c)) active += 1
    }
    return { total: pagination?.total ?? slice, active, inactive: slice - active }
  }, [cards, pagination])

  const sourceList = filter === "all" ? orderedCards : cards

  const filtered = useMemo(() => {
    if (filter === "active") return sourceList.filter(isActiveRow)
    if (filter === "inactive") return sourceList.filter((c) => !isActiveRow(c))
    return sourceList
  }, [sourceList, filter])

  const sortableIds = useMemo(
    () => (filter === "all" ? filtered.map((c) => num(c.id)).filter((id) => id > 0) : []),
    [filtered, filter]
  )

  const openAdd = useCallback(() => {
    setError(null)
    setFormMode("add")
    setForm(emptyForm())
    setSheetOpen(true)
  }, [])

  const openEdit = useCallback((c: DashRecord) => {
    setError(null)
    setFormMode("edit")
    setForm(formFromRow(c))
    setSheetOpen(true)
  }, [])

  const onSaveSheet = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      if (formMode === "add") {
        const res = await postAdminMutate("card_add", {
          card_number: form.card_number,
          holder_name: form.holder_name,
          bank_name: form.bank_name,
          method_key: form.method_key,
          daily_limit: form.daily_limit,
          note: form.note,
        })
        if (!res.ok) {
          setError(formatMutateError(res.message, tp))
          return
        }
      } else {
        const res = await postAdminMutate("card_update", {
          edit_id: form.edit_id,
          card_number: form.card_number,
          holder_name: form.holder_name,
          bank_name: form.bank_name,
          method_key: form.method_key,
          daily_limit: form.daily_limit,
          note: form.note,
          active: form.active ? 1 : 0,
        })
        if (!res.ok) {
          setError(formatMutateError(res.message, tp))
          return
        }
      }
      setSheetOpen(false)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, formMode, onMutateSuccess, tp])

  const onConfirmDelete = useCallback(async () => {
    if (!deleteTarget) return
    const id = num(deleteTarget.id)
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("card_delete", { edit_id: id })
      if (!res.ok) {
        setError(formatMutateError(res.message, tp))
        return
      }
      setDeleteTarget(null)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [deleteTarget, onMutateSuccess, tp])

  const methodLabel = useCallback(
    (raw: unknown): string => {
      const key = String(raw ?? "").trim() === "mehr" ? "c2c" : String(raw ?? "")
      if (key === "crypto") return tp("method_crypto")
      if (key === "crypto_auto") return tp("method_crypto_auto")
      return tp("method_c2c")
    },
    [tp]
  )

  const paymentMethodLabel = useCallback(
    (key: PaymentMethodKey): string => {
      const map: Record<PaymentMethodKey, string> = {
        c2c: tp("paymentMethod_c2c"),
        crypto: tp("paymentMethod_crypto"),
        crypto_auto: tp("paymentMethod_crypto_auto"),
        bale_wallet: tp("paymentMethod_bale_wallet"),
        site_wallet: tp("paymentMethod_site_wallet"),
        wallet_topup: tp("paymentMethod_wallet_topup"),
      }
      return map[key]
    },
    [tp]
  )

  const paymentMethodHint = useCallback(
    (key: PaymentMethodKey): string => {
      const map: Record<PaymentMethodKey, string> = {
        c2c: tp("paymentMethodHint_c2c"),
        crypto: tp("paymentMethodHint_crypto"),
        crypto_auto: tp("paymentMethodHint_crypto_auto"),
        bale_wallet: tp("paymentMethodHint_bale_wallet"),
        site_wallet: tp("paymentMethodHint_site_wallet"),
        wallet_topup: tp("paymentMethodHint_wallet_topup"),
      }
      return map[key]
    },
    [tp]
  )

  const canSavePaymentMethods = isReseller || canEditDisplayMode
  const showSidebar = canEditDisplayMode || canSavePaymentMethods
  const formLabels = useMemo(() => formFieldLabels(form.method_key, tp), [form.method_key, tp])

  const cardSubtitle = useCallback(
    (c: DashRecord): string => {
      if (isCryptoMethodKey(c.method_key)) {
        return `${shortenCryptoAddress(c.card_number)} · ${String(c.holder_name ?? "")}`
      }
      return `${formatRedactedCardNumber(c.card_number)} · ${String(c.holder_name ?? "")}`
    },
    []
  )

  const renderPaymentMethodToggle = (key: PaymentMethodKey) => (
    <div
      key={key}
      className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border/60 px-3 py-2.5"
    >
      <div className="min-w-0 flex-1 space-y-1">
        <Label htmlFor={`pm-${key}`} className="text-xs font-medium">
          {paymentMethodLabel(key)}
        </Label>
        <p className="text-[11px] leading-snug text-muted-foreground">{paymentMethodHint(key)}</p>
      </div>
      <Switch
        id={`pm-${key}`}
        checked={paymentMethodsMap[key]}
        onCheckedChange={(checked) => setPaymentMethodsMap((prev) => ({ ...prev, [key]: checked }))}
        disabled={savingPaymentMethods}
      />
    </div>
  )

  const savePaymentMethods = useCallback(async () => {
    if (!canSavePaymentMethods) return
    setSavingPaymentMethods(true)
    setError(null)
    try {
      const res = isReseller
        ? await postAdminMutate("reseller_payment_methods_save", { payment_methods: paymentMethodsMap })
        : await postAdminMutate("settings_tab", {
            tab: "cards",
            payment_methods: paymentMethodsMap,
            ...(canEditDisplayMode ? { cards_display_mode: displayMode } : {}),
          })
      if (!res.ok) {
        setError(formatMutateError(res.message, tp))
        return
      }
      setSuccess(tp("paymentMethodsSaved"))
      onMutateSuccess?.()
    } finally {
      setSavingPaymentMethods(false)
    }
  }, [canSavePaymentMethods, isReseller, paymentMethodsMap, canEditDisplayMode, displayMode, onMutateSuccess, tp])

  const onToggleActive = useCallback(
    async (c: DashRecord, checked: boolean) => {
      const f = formFromRow(c)
      setSaving(true)
      setError(null)
      try {
        const res = await postAdminMutate("card_update", {
          edit_id: f.edit_id,
          card_number: f.card_number,
          holder_name: f.holder_name,
          bank_name: f.bank_name,
          method_key: f.method_key,
          daily_limit: f.daily_limit,
          note: f.note,
          active: checked ? 1 : 0,
        })
        if (!res.ok) {
          setError(formatMutateError(res.message, tp))
          return
        }
        onMutateSuccess?.()
      } finally {
        setSaving(false)
      }
    },
    [onMutateSuccess, tp]
  )

  const onDragEnd = useCallback(
    async (event: DragEndEvent) => {
      if (filter !== "all") return
      const { active, over } = event
      if (!over || active.id === over.id) return
      const oldIndex = orderedCards.findIndex((c) => num(c.id) === active.id)
      const newIndex = orderedCards.findIndex((c) => num(c.id) === over.id)
      if (oldIndex < 0 || newIndex < 0) return
      const next = arrayMove(orderedCards, oldIndex, newIndex)
      setOrderedCards(next)
      setReordering(true)
      setError(null)
      try {
        const res = await postAdminMutate("card_reorder", {
          ordered_ids: next.map((row) => num(row.id)).filter((id) => id > 0),
        })
        if (!res.ok) {
          setError(formatMutateError(res.message, tp))
          setOrderedCards(cards)
          return
        }
        onMutateSuccess?.()
      } finally {
        setReordering(false)
      }
    },
    [cards, filter, onMutateSuccess, orderedCards, tp]
  )

  const saveDisplayMode = useCallback(async () => {
    if (!canEditDisplayMode) return
    setSaving(true)
    setError(null)
    setSuccess(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "cards",
        cards_display_mode: displayMode,
      })
      if (!res.ok) {
        setError(formatMutateError(res.message, tp))
        return
      }
      setSuccess(tp("displayModeSaved"))
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [canEditDisplayMode, displayMode, onMutateSuccess, tp])

  return (
    <DashPage data-testid="dash-cards-tab">
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />

      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}
      {success ? (
        <div
          role="status"
          className="rounded-md border border-primary/30 bg-primary/10 px-3 py-2 text-sm text-foreground"
        >
          {success}
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsTotal")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.total, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsActive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.active, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsInactive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.inactive, isFa)}</CardTitle>
          </CardHeader>
        </Card>
      </div>
      <div
        className={cn(
          "grid gap-4 lg:items-start",
          showSidebar ? "lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]" : ""
        )}
      >
        <div className="order-2 min-w-0 space-y-4 lg:order-1">
          {pagination ? <p className="text-xs text-muted-foreground">{tp("statsPageBreakdown")}</p> : null}

          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex flex-wrap items-center gap-2">
              <Label className="text-muted-foreground">{tp("filterLabel")}</Label>
              <DashSelect
                triggerClassName="w-auto min-w-[8rem]"
                value={filter}
                onValueChange={(v) => setFilter(v as "all" | "active" | "inactive")}
                options={[
                  { value: "all", label: tp("filterAll") },
                  { value: "active", label: tp("filterActive") },
                  { value: "inactive", label: tp("filterInactive") },
                ]}
              />
            </div>
            <Button type="button" size="sm" onClick={openAdd}>
              {tp("addCard")}
            </Button>
          </div>

          {filter === "all" && filtered.length > 1 ? (
            <p className="text-xs text-muted-foreground">{tp("dragHint")}</p>
          ) : null}

          {filtered.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("empty")}</p>
          ) : (
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={(e) => void onDragEnd(e)}>
              <SortableContext items={sortableIds} strategy={rectSortingStrategy}>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                  {filtered.map((c) => (
                    <SortableCardTile
                      key={num(c.id) || String(c.card_number)}
                      c={c}
        canDrag={filter === "all" && sortableIds.length > 1}
                      busy={reordering}
                      saving={saving}
                      tp={tp}
                      methodLabel={methodLabel}
                      cardSubtitle={cardSubtitle}
                      onToggleActive={(row, checked) => void onToggleActive(row, checked)}
                      onEdit={openEdit}
                      onDelete={setDeleteTarget}
                    />
                  ))}
                </div>
              </SortableContext>
            </DndContext>
          )}

          <DataPagination
            meta={pagination}
        onPageChange={onPageChange}
            onPerPageChange={onPerPageChange}
            perPageOptions={[40, 80, 120, 200]}
          />
        </div>

        {showSidebar ? (
          <div className="order-1 flex flex-col gap-4 lg:order-2 lg:sticky lg:top-4">
            {canEditDisplayMode ? (
              <Card className="h-fit">
                <CardHeader className="space-y-1 pb-2">
                  <CardTitle className="text-sm font-medium">{tp("displayModeTitle")}</CardTitle>
                  <CardDescription className="text-xs leading-snug">{tp("displayModeDesc")}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-2 pt-0">
                  <div className="space-y-2">
                    <Label className="text-xs text-muted-foreground">{tp("displayModeLabel")}</Label>
                    <DashSelect
                      value={displayMode}
                      onValueChange={(v) => {
                        setSuccess(null)
                        setDisplayMode((v as CardsDisplayMode) || "list")
                      }}
                      options={[
                        { value: "list", label: tp("displayModeList") },
                        { value: "sequential", label: tp("displayModeSequential") },
                        { value: "random", label: tp("displayModeRandom") },
                      ]}
                    />
                    <Button
                      type="button"
                      size="sm"
                      className="w-full"
                      disabled={saving}
                      onClick={() => void saveDisplayMode()}
                    >
                      {tp("saveDisplayMode")}
                    </Button>
                  </div>
                  <p className="text-xs leading-snug text-muted-foreground">{tp("displayModeHint")}</p>
                </CardContent>
              </Card>
            ) : null}

            {canSavePaymentMethods ? (
              <Card className="h-fit">
                <CardHeader className="space-y-1 pb-2">
                  <CardTitle className="text-sm font-medium">{tp("paymentMethodsTitle")}</CardTitle>
                  <CardDescription className="text-xs leading-snug">{tp("paymentMethodsDesc")}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4 pt-0">
                  <div className="space-y-2">
                    <p className="text-xs font-medium text-muted-foreground">{tp("paymentMethodsGroup_cards")}</p>
                    {cardPaymentMethodKeys.map((key) => renderPaymentMethodToggle(key))}
                  </div>
                  <div className="space-y-2">
                    <p className="text-xs font-medium text-muted-foreground">{tp("paymentMethodsGroup_wallet")}</p>
                    {walletPaymentMethodKeys.map((key) => renderPaymentMethodToggle(key))}
                  </div>
                  <Button
                    type="button"
                    size="sm"
                    className="w-full"
                    onClick={() => void savePaymentMethods()}
                    disabled={savingPaymentMethods}
                  >
                    {savingPaymentMethods ? tp("saving") : tp("paymentMethodsSave")}
                  </Button>
                </CardContent>
              </Card>
            ) : null}
          </div>
        ) : null}
      </div>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <DashSheetContent className={cn("flex w-full flex-col gap-0 overflow-y-auto sm:max-w-md")}>
          <SheetHeader className="border-b p-4 text-start">
            <SheetTitle>{formMode === "add" ? tp("addCard") : tp("editCard")}</SheetTitle>
          </SheetHeader>
          <div className="flex-1 space-y-4 p-4">
            <div className="space-y-2">
              <Label>{tp("method")}</Label>
              <DashSelect
                value={form.method_key}
                onValueChange={(v) =>
                  setForm((f) => ({
                    ...f,
                    method_key: (v as (typeof METHOD_KEYS)[number]) || "c2c",
                  }))
                }
                options={formMethodKeys.map((k) => ({ value: k, label: methodLabel(k) }))}
              />
            </div>
            {formLabels.cryptoAutoHint ? (
              <p className="text-xs text-muted-foreground">{formLabels.cryptoAutoHint}</p>
            ) : null}
            <div className="space-y-2">
              <Label>{formLabels.primary}</Label>
              <Input
                className={isCryptoMethodKey(form.method_key) ? "font-mono text-sm" : "font-mono"}
                value={form.card_number}
                onChange={(e) => setForm((f) => ({ ...f, card_number: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{formLabels.secondary}</Label>
              <Input
                value={form.holder_name}
                onChange={(e) => setForm((f) => ({ ...f, holder_name: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{formLabels.tertiary}</Label>
              <Input value={form.bank_name} onChange={(e) => setForm((f) => ({ ...f, bank_name: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{formLabels.note}</Label>
              <Input value={form.note} onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))} />
              {formLabels.noteHint ? (
                <p className="text-xs text-muted-foreground">{formLabels.noteHint}</p>
              ) : null}
            </div>
            <div className="space-y-2">
              <Label>{tp("dailyLimit")}</Label>
              <Input
                type="number"
                min={0}
                step="0.01"
                value={form.daily_limit}
                onChange={(e) => setForm((f) => ({ ...f, daily_limit: num(e.target.value) }))}
              />
            </div>
          </div>
          <SheetFooter className="flex-row gap-2 border-t p-4">
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>
              {tp("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void onSaveSheet()}>
              {tp("save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

      <Dialog open={Boolean(deleteTarget)} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DashDialogContent className={cn()}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>{tp("deleteTitle")}</DialogTitle>
            <DialogDescription>{tp("deleteDescription")}</DialogDescription>
          </DashDialogHeader>
          <DashDialogFooter className={cn("gap-2 sm:justify-between")}>
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
              {tp("deleteCancel")}
            </Button>
            <Button type="button" variant="destructive" disabled={saving} onClick={() => void onConfirmDelete()}>
              {tp("deleteConfirm")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </DashPage>
  )
}
