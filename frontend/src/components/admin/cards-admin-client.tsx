"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import {
  DndContext,
  PointerSensor,
  closestCenter,
  type DragEndEvent,
  useSensor,
  useSensors,
} from "@dnd-kit/core"
import { SortableContext, arrayMove, rectSortingStrategy } from "@dnd-kit/sortable"
import { useDashboardShellOptional } from "@/components/dashboard-shell-provider"
import { DataPagination } from "@/components/data-pagination"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"
import { mainEnabledPlatforms } from "@/lib/enabled-platforms"
import { Button } from "@/components/ui/button"
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { PaymentMethodSection } from "@/components/admin/payment-methods/payment-method-section"
import { AddCardTile } from "@/components/admin/payment-methods/add-card-tile"
import { SortableCardTile } from "@/components/payment-methods/sortable-card-tile"
import {
  DisplayModeBanner,
  type CardsDisplayMode,
} from "@/components/payment-methods/display-mode-banner"
import { WalletMethodCard } from "@/components/admin/payment-methods/wallet-method-card"

type DashRecord = Record<string, unknown>
type MethodKey =
  | "c2c"
  | "crypto"
  | "crypto_auto"
  | "crypto_tetra"
  | "rial_zarinpal"
  | "rial_aqayepardakht"
  | "rial_zibal"
type GatewaySheetKey = "zarinpal" | "aqayepardakht" | "zibal" | "tetra" | "nowpayments" | null
type PaymentMethodKey = "c2c" | "crypto" | "crypto_auto" | "bale_wallet" | "site_wallet" | "wallet_topup"

const PAYMENT_METHOD_KEYS: PaymentMethodKey[] = [
  "c2c",
  "crypto",
  "crypto_auto",
  "bale_wallet",
  "site_wallet",
  "wallet_topup",
]
const WALLET_PAYMENT_METHOD_KEYS: PaymentMethodKey[] = ["bale_wallet", "site_wallet", "wallet_topup"]

function parseCardsDisplayMode(raw: unknown): CardsDisplayMode {
  const v = String(raw ?? "list")
  if (v === "sequential" || v === "random") return v
  return "list"
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

type CardForm = {
  id: number
  card_number: string
  holder_name: string
  bank_name: string
  method_key: MethodKey
  daily_limit: number
  priority: number
  note: string
  active: boolean
}

const METHOD_KEYS: MethodKey[] = [
  "c2c",
  "crypto",
  "crypto_auto",
  "crypto_tetra",
  "rial_zarinpal",
  "rial_aqayepardakht",
  "rial_zibal",
]

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function rows(v: unknown): DashRecord[] {
  return Array.isArray(v) ? (v.filter((x) => x && typeof x === "object") as DashRecord[]) : []
}

function isActive(row: DashRecord): boolean {
  return row.active === true || row.active === 1 || row.active === "1"
}

function emptyForm(method_key: MethodKey = "c2c"): CardForm {
  return {
    id: 0,
    card_number: "",
    holder_name: "",
    bank_name: "",
    method_key,
    daily_limit: 0,
    priority: 0,
    note: "",
    active: true,
  }
}

function formFromRow(row: DashRecord): CardForm {
  const method = String(row.method_key ?? "c2c") as MethodKey
  return {
    id: num(row.id),
    card_number: String(row.card_number ?? ""),
    holder_name: String(row.holder_name ?? ""),
    bank_name: String(row.bank_name ?? ""),
    method_key: METHOD_KEYS.includes(method) ? method : "c2c",
    daily_limit: num(row.daily_limit),
    priority: num(row.priority),
    note: String(row.note ?? ""),
    active: isActive(row),
  }
}

function shorten(raw: unknown): string {
  const s = String(raw ?? "").trim()
  if (!s) return "—"
  if (s.length <= 18) return s
  return `${s.slice(0, 8)}…${s.slice(-4)}`
}

function methodOf(row: DashRecord): string {
  return String(row.method_key ?? "c2c")
}

function pickCardsPagination(data: DashRecord): PaginationMeta | null {
  const raw = data.pagination
  if (raw && typeof raw === "object") {
    return parsePaginationMeta((raw as DashRecord).cards)
  }
  return parsePaginationMeta(data.cardsPagination)
}

export function CardsAdminClient() {
  const t = useTranslations("cardsAdmin")
  const tFinance = useTranslations("siteSettings.finance")
  const locale = useLocale()
  const isFa = locale === "fa"
  const shell = useDashboardShellOptional()
  const isReseller = Boolean(shell?.isReseller)
  const canEditDisplayMode = !isReseller
  const [cards, setCards] = useState<DashRecord[]>([])
  const [settings, setSettings] = useState<DashRecord>({})
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [cardsPage, setCardsPage] = useState(1)
  const [cardsPerPage, setCardsPerPage] = useState(40)
  const [filter, setFilter] = useState<"all" | "active" | "inactive">("all")
  const [formOpen, setFormOpen] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)
  const [form, setForm] = useState<CardForm>(() => emptyForm())
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [reordering, setReordering] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [feedback, setFeedback] = useState<string | null>(null)
  const [gatewaySheet, setGatewaySheet] = useState<GatewaySheetKey>(null)
  const [zarinpalMerchantId, setZarinpalMerchantId] = useState("")
  const [zarinpalSandboxDraft, setZarinpalSandboxDraft] = useState(false)
  const [aqayepardakhtPin, setAqayepardakhtPin] = useState("")
  const [aqayepardakhtSandboxDraft, setAqayepardakhtSandboxDraft] = useState(false)
  const [zibalMerchant, setZibalMerchant] = useState("")
  const [zibalSandboxDraft, setZibalSandboxDraft] = useState(false)
  const [tetraApiKey, setTetraApiKey] = useState("")
  const [nowPaymentsApiKey, setNowPaymentsApiKey] = useState("")
  const [nowPaymentsIpnSecret, setNowPaymentsIpnSecret] = useState("")
  const [nowPaymentsPayCurrency, setNowPaymentsPayCurrency] = useState("usdttrc20")
  const [nowPaymentsTomanPerUsd, setNowPaymentsTomanPerUsd] = useState("50000")
  const [displayMode, setDisplayMode] = useState<CardsDisplayMode>("list")
  const [paymentMethodsMap, setPaymentMethodsMap] = useState<Record<PaymentMethodKey, boolean>>(defaultPaymentMethodsMap)
  const [savingDisplayMode, setSavingDisplayMode] = useState(false)
  const [savingPaymentMethods, setSavingPaymentMethods] = useState(false)

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }))

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await getAdminState("cards", {
        cards_page: cardsPage,
        cards_per_page: cardsPerPage,
      })
      setCards(rows(data.cards))
      setPagination(pickCardsPagination(data))
      const nextSettings = (data.settings && typeof data.settings === "object" ? data.settings : {}) as DashRecord
      setSettings(nextSettings)
      setZarinpalSandboxDraft(Boolean(nextSettings.zarinpal_sandbox))
      setAqayepardakhtSandboxDraft(Boolean(nextSettings.aqayepardakht_sandbox))
      setZibalSandboxDraft(Boolean(nextSettings.zibal_sandbox))
      setNowPaymentsPayCurrency(String(nextSettings.crypto_nowpayments_pay_currency ?? "usdttrc20") || "usdttrc20")
      setNowPaymentsTomanPerUsd(String(nextSettings.crypto_toman_per_usd ?? "50000") || "50000")
      setDisplayMode(parseCardsDisplayMode(nextSettings.cards_display_mode))
      const pm =
        data.paymentMethods && typeof data.paymentMethods === "object"
          ? (data.paymentMethods as DashRecord).effective
          : nextSettings.payment_methods
      setPaymentMethodsMap(parsePaymentMethodsMap(pm))
    } catch {
      setError(t("loadError"))
    } finally {
      setLoading(false)
    }
  }, [cardsPage, cardsPerPage, t])

  useEffect(() => {
    void load()
  }, [load])

  const zarinpalConfigured = Boolean(String(settings.zarinpal_merchant_id ?? "").trim()) || Boolean(settings.zarinpal_merchant_id_set)
  const aqayepardakhtConfigured = Boolean(String(settings.aqayepardakht_pin ?? "").trim()) || Boolean(settings.aqayepardakht_pin_set)
  const zibalConfigured = Boolean(String(settings.zibal_merchant ?? "").trim()) || Boolean(settings.zibal_merchant_set)
  const tetraConfigured = Boolean(String(settings.crypto_tetra_api_key ?? "").trim()) || Boolean(settings.crypto_tetra_api_key_set)
  const nowPaymentsConfigured =
    Boolean(String(settings.crypto_nowpayments_api_key ?? "").trim()) || Boolean(settings.crypto_nowpayments_api_key_set)

  const stats = useMemo(() => {
    const active = cards.filter(isActive).length
    const slice = cards.length
    return {
      total: pagination?.total ?? slice,
      active,
      inactive: Math.max(0, slice - active),
    }
  }, [cards, pagination])

  const visibleCards = useMemo(() => {
    if (filter === "active") return cards.filter(isActive)
    if (filter === "inactive") return cards.filter((card) => !isActive(card))
    return cards
  }, [cards, filter])

  const grouped = useMemo(() => {
    return {
      c2c: visibleCards.filter((card) => methodOf(card) === "c2c"),
      crypto: visibleCards.filter((card) => methodOf(card) === "crypto"),
      crypto_auto: visibleCards.filter((card) => methodOf(card) === "crypto_auto"),
      crypto_tetra: visibleCards.filter((card) => methodOf(card) === "crypto_tetra"),
      rial_zarinpal: visibleCards.filter((card) => methodOf(card) === "rial_zarinpal"),
      rial_aqayepardakht: visibleCards.filter((card) => methodOf(card) === "rial_aqayepardakht"),
      rial_zibal: visibleCards.filter((card) => methodOf(card) === "rial_zibal"),
    }
  }, [visibleCards])

  const walletKeys = useMemo(() => {
    const showBale = mainEnabledPlatforms(settings).includes("bale")
    return WALLET_PAYMENT_METHOD_KEYS.filter((k) => k !== "bale_wallet" || showBale)
  }, [settings])

  const methodLabel = (raw: unknown) => {
    const key = String(raw ?? "c2c")
    if (key === "crypto") return t("method_crypto")
    if (key === "crypto_auto") return t("method_crypto_auto")
    if (key === "crypto_tetra") return t("method_crypto_tetra")
    if (key === "rial_zarinpal") return t("method_rial_zarinpal")
    if (key === "rial_aqayepardakht") return t("method_rial_aqayepardakht")
    if (key === "rial_zibal") return t("method_rial_zibal")
    return t("method_c2c")
  }

  const openAdd = (method_key: MethodKey = "c2c") => {
    setForm({ ...emptyForm(method_key), method_key })
    setError(null)
    setFormOpen(true)
  }

  const openEdit = (row: DashRecord) => {
    setForm(formFromRow(row))
    setError(null)
    setFormOpen(true)
  }

  const run = async (op: string, params: Record<string, unknown>, busyCardId?: number) => {
    setSaving(true)
    setBusyId(busyCardId ?? null)
    setFeedback(null)
    try {
      const res = await postAdminMutate(op, params)
      if (!res.ok) {
        setFeedback(String(res.message ?? res.reason ?? t("mutateError")))
        return false
      }
      setFeedback(t("mutateSuccess"))
      await load()
      return true
    } catch {
      setFeedback(t("mutateError"))
      return false
    } finally {
      setSaving(false)
      setBusyId(null)
    }
  }

  const saveForm = async () => {
    const payload = {
      card_number: form.card_number.trim(),
      holder_name: form.holder_name.trim(),
      bank_name: form.bank_name.trim(),
      method_key: form.method_key,
      daily_limit: form.daily_limit,
      priority: form.priority,
      note: form.note.trim(),
      active: form.active ? 1 : 0,
    }
    const ok =
      form.id > 0
        ? await run("card_update", { id: form.id, ...payload }, form.id)
        : await run("card_add", payload)
    if (ok) setFormOpen(false)
  }

  const toggleActive = (row: DashRecord, active: boolean) => {
    const id = num(row.id)
    void run(
      "card_update",
      {
        id,
        card_number: String(row.card_number ?? ""),
        holder_name: String(row.holder_name ?? ""),
        bank_name: String(row.bank_name ?? ""),
        method_key: String(row.method_key ?? "c2c"),
        daily_limit: num(row.daily_limit),
        priority: num(row.priority),
        note: String(row.note ?? ""),
        active: active ? 1 : 0,
      },
      id
    )
  }

  const deleteCard = async () => {
    if (!deleteTarget) return
    const ok = await run("card_delete", { id: num(deleteTarget.id), edit_id: num(deleteTarget.id) }, num(deleteTarget.id))
    if (ok) setDeleteTarget(null)
  }

  const saveDisplayMode = async () => {
    if (!canEditDisplayMode) return
    setSavingDisplayMode(true)
    setFeedback(null)
    try {
      const res = await postAdminMutate("settings_tab", { tab: "cards", cards_display_mode: displayMode })
      if (!res.ok) {
        setFeedback(String(res.message ?? t("mutateError")))
        return
      }
      setFeedback(t("displayModeSaved"))
      await load()
    } finally {
      setSavingDisplayMode(false)
    }
  }

  const savePaymentMethods = async () => {
    setSavingPaymentMethods(true)
    setFeedback(null)
    try {
      const res = isReseller
        ? await postAdminMutate("reseller_payment_methods_save", { payment_methods: paymentMethodsMap })
        : await postAdminMutate("settings_tab", {
            tab: "cards",
            payment_methods: paymentMethodsMap,
            ...(canEditDisplayMode ? { cards_display_mode: displayMode } : {}),
          })
      if (!res.ok) {
        setFeedback(String(res.message ?? t("mutateError")))
        return
      }
      setFeedback(t("paymentMethodsSaved"))
      await load()
    } finally {
      setSavingPaymentMethods(false)
    }
  }

  const onC2cDragEnd = async (event: DragEndEvent) => {
    if (filter !== "all") return
    const { active, over } = event
    if (!over || active.id === over.id) return
    const list = grouped.c2c
    const oldIndex = list.findIndex((c) => num(c.id) === Number(active.id))
    const newIndex = list.findIndex((c) => num(c.id) === Number(over.id))
    if (oldIndex < 0 || newIndex < 0) return
    const nextC2c = arrayMove(list, oldIndex, newIndex)
    const others = cards.filter((c) => methodOf(c) !== "c2c")
    const fullOrdered = [...nextC2c, ...others]
    setCards(fullOrdered)
    setReordering(true)
    try {
      // Prefer full card_reorder when filter is all (legacy behavior).
      const res = await postAdminMutate("card_reorder", {
        ordered_ids: fullOrdered.map((row) => num(row.id)).filter((id) => id > 0),
      })
      if (!res.ok) {
        setFeedback(String(res.message ?? t("mutateError")))
        await load()
        return
      }
      setFeedback(t("mutateSuccess"))
      await load()
    } finally {
      setReordering(false)
    }
  }

  const saveZarinpalSettings = async () => {
    const merchantId = zarinpalMerchantId.trim()
    if (!merchantId && !zarinpalConfigured) return
    const payload: Record<string, unknown> = { zarinpal_sandbox: zarinpalSandboxDraft ? 1 : 0 }
    if (merchantId) payload.zarinpal_merchant_id = merchantId
    const ok = await run("rial_settings", payload)
    if (ok) {
      setZarinpalMerchantId("")
      setGatewaySheet(null)
      setFeedback(t("zarinpalSettingsSaved"))
    }
  }

  const saveAqayepardakhtSettings = async () => {
    const pin = aqayepardakhtPin.trim()
    if (!pin && !aqayepardakhtConfigured) return
    const payload: Record<string, unknown> = { aqayepardakht_sandbox: aqayepardakhtSandboxDraft ? 1 : 0 }
    if (pin) payload.aqayepardakht_pin = pin
    const ok = await run("rial_settings", payload)
    if (ok) {
      setAqayepardakhtPin("")
      setGatewaySheet(null)
      setFeedback(t("aqayepardakhtSettingsSaved"))
    }
  }

  const saveZibalSettings = async () => {
    const merchant = zibalMerchant.trim()
    if (!merchant && !zibalConfigured) return
    const payload: Record<string, unknown> = { zibal_sandbox: zibalSandboxDraft ? 1 : 0 }
    if (merchant) payload.zibal_merchant = merchant
    const ok = await run("rial_settings", payload)
    if (ok) {
      setZibalMerchant("")
      setGatewaySheet(null)
      setFeedback(t("zibalSettingsSaved"))
    }
  }

  const saveTetraApiKey = async () => {
    const key = tetraApiKey.trim()
    if (!key) return
    const ok = await run("crypto_settings", { crypto_tetra_api_key: key })
    if (ok) {
      setTetraApiKey("")
      setGatewaySheet(null)
      setFeedback(t("tetraApiKeySaved"))
    }
  }

  const saveNowPaymentsSettings = async () => {
    const payload: Record<string, unknown> = {
      crypto_nowpayments_pay_currency: nowPaymentsPayCurrency.trim() || "usdttrc20",
      crypto_toman_per_usd: Number(nowPaymentsTomanPerUsd) || 50000,
    }
    if (nowPaymentsApiKey.trim()) payload.crypto_nowpayments_api_key = nowPaymentsApiKey.trim()
    if (nowPaymentsIpnSecret.trim()) payload.crypto_nowpayments_ipn_secret = nowPaymentsIpnSecret.trim()
    const ok = await run("crypto_settings", payload)
    if (ok) {
      setNowPaymentsApiKey("")
      setNowPaymentsIpnSecret("")
      setGatewaySheet(null)
      setFeedback(t("mutateSuccess"))
    }
  }

  const cardSubtitle = (row: DashRecord) => shorten(row.card_number)
  const isGatewayMethod = (key: MethodKey) =>
    key === "crypto" || key === "crypto_auto" || key === "crypto_tetra" || key.startsWith("rial_")
  const c2cSortableIds = grouped.c2c.map((c) => num(c.id)).filter((id) => id > 0)

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load()}>
          {t("refresh")}
        </Button>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {feedback ? <p className="text-sm text-muted-foreground">{feedback}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}

      <div className="grid gap-3 sm:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t("statsTotal")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.total, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t("statsActive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.active, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t("statsInactive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.inactive, isFa)}</CardTitle>
          </CardHeader>
        </Card>
      </div>

      <DisplayModeBanner
        label={t("displayModeBannerLabel")}
        displayMode={displayMode}
        onDisplayModeChange={setDisplayMode}
        onSaveDisplayMode={() => void saveDisplayMode()}
        savingDisplayMode={savingDisplayMode}
        canEditDisplayMode={canEditDisplayMode}
        c2cEnabled={paymentMethodsMap.c2c}
        onC2cEnabledChange={(checked) => setPaymentMethodsMap((m) => ({ ...m, c2c: checked }))}
        onSaveC2cToggle={() => void savePaymentMethods()}
        savingC2cToggle={savingPaymentMethods}
        canSaveC2cToggle
        saveDisplayModeLabel={t("saveDisplayMode")}
        saveTogglesLabel={t("paymentMethodsSave")}
        tp={t}
      />

      <div className="flex flex-wrap items-center gap-2">
        <Label className="text-xs text-muted-foreground">{t("filterLabel")}</Label>
        {(["all", "active", "inactive"] as const).map((next) => (
          <Button key={next} type="button" size="sm" variant={filter === next ? "default" : "outline"} onClick={() => setFilter(next)}>
            {next === "all" ? t("filterAll") : next === "active" ? t("filterActive") : t("filterInactive")}
          </Button>
        ))}
      </div>

      <PaymentMethodSection title={t("sectionC2c")} description={t("sectionC2cDesc")}>
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={(e) => void onC2cDragEnd(e)}>
          <SortableContext items={c2cSortableIds} strategy={rectSortingStrategy}>
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {grouped.c2c.map((card) => (
                <SortableCardTile
                  key={num(card.id)}
                  c={card}
                  canDrag={!reordering && filter === "all"}
                  busy={busyId === num(card.id)}
                  saving={saving || reordering}
                  tp={t}
                  cardSubtitle={cardSubtitle}
                  onToggleActive={toggleActive}
                  onEdit={openEdit}
                  onDelete={setDeleteTarget}
                />
              ))}
              <AddCardTile label={t("addCardTile")} onClick={() => openAdd("c2c")} />
            </div>
          </SortableContext>
        </DndContext>
        {grouped.c2c.length === 0 ? <p className="text-sm text-muted-foreground">{t("emptyC2c")}</p> : null}
        {filter === "all" && grouped.c2c.length > 1 ? (
          <p className="mt-2 text-xs text-muted-foreground">{t("dragHint")}</p>
        ) : null}
      </PaymentMethodSection>

      <PaymentMethodSection title={t("sectionRial")} description={t("sectionRialDesc")}>
        <div className="mb-3 flex flex-wrap gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => setGatewaySheet("zarinpal")}>
            {t("zarinpalTitle")} · {zarinpalConfigured ? t("zarinpalMerchantConfigured") : t("zarinpalMerchantMissing")}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={() => setGatewaySheet("aqayepardakht")}>
            {t("aqayepardakhtTitle")} · {aqayepardakhtConfigured ? t("aqayepardakhtPinConfigured") : t("aqayepardakhtPinMissing")}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={() => setGatewaySheet("zibal")}>
            {t("zibalTitle")} · {zibalConfigured ? t("zibalMerchantConfigured") : t("zibalMerchantMissing")}
          </Button>
        </div>
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {[...grouped.rial_zarinpal, ...grouped.rial_aqayepardakht, ...grouped.rial_zibal].map((card) => (
            <SortableCardTile
              key={num(card.id)}
              c={card}
              canDrag={false}
              busy={busyId === num(card.id)}
              saving={saving}
              tp={t}
              cardSubtitle={cardSubtitle}
              showMethod
              methodLabel={methodLabel}
              onToggleActive={toggleActive}
              onEdit={openEdit}
              onDelete={setDeleteTarget}
            />
          ))}
          <AddCardTile label={t("zarinpalTitle")} onClick={() => openAdd("rial_zarinpal")} />
          <AddCardTile label={t("aqayepardakhtTitle")} onClick={() => openAdd("rial_aqayepardakht")} />
          <AddCardTile label={t("zibalTitle")} onClick={() => openAdd("rial_zibal")} />
        </div>
      </PaymentMethodSection>

      <PaymentMethodSection title={t("sectionCrypto")} description={t("sectionCryptoDesc")}>
        <div className="mb-3 flex flex-wrap gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => setGatewaySheet("nowpayments")}>
            {t("cryptoNowPaymentsTitle")} · {nowPaymentsConfigured ? t("nowPaymentsApiConfigured") : t("nowPaymentsApiMissing")}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={() => setGatewaySheet("tetra")}>
            {t("cryptoTetraTitle")} · {tetraConfigured ? t("tetraApiConfigured") : t("tetraApiMissing")}
          </Button>
        </div>
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {[...grouped.crypto, ...grouped.crypto_auto, ...grouped.crypto_tetra].map((card) => (
            <SortableCardTile
              key={num(card.id)}
              c={card}
              canDrag={false}
              busy={busyId === num(card.id)}
              saving={saving}
              tp={t}
              cardSubtitle={cardSubtitle}
              showMethod
              methodLabel={methodLabel}
              onToggleActive={toggleActive}
              onEdit={openEdit}
              onDelete={setDeleteTarget}
            />
          ))}
          <AddCardTile label={t("addCryptoWalletTile")} onClick={() => openAdd("crypto")} />
          <AddCardTile label={t("cryptoNowPaymentsTitle")} onClick={() => openAdd("crypto_auto")} />
          <AddCardTile label={t("cryptoTetraTitle")} onClick={() => openAdd("crypto_tetra")} />
        </div>
      </PaymentMethodSection>

      <DataPagination
        meta={pagination}
        onPageChange={setCardsPage}
        onPerPageChange={(n) => {
          setCardsPerPage(n)
          setCardsPage(1)
        }}
        perPageOptions={[40, 80, 120, 200]}
      />

      <PaymentMethodSection title={t("sectionWallet")} description={t("sectionWalletDesc")}>
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {walletKeys.map((key) => (
            <WalletMethodCard
              key={key}
              switchId={`wallet-${key}`}
              title={t(`paymentMethod_${key}`)}
              hint={t(`paymentMethodHint_${key}`)}
              checked={paymentMethodsMap[key]}
              disabled={savingPaymentMethods}
              onCheckedChange={(checked) => setPaymentMethodsMap((m) => ({ ...m, [key]: checked }))}
            />
          ))}
        </div>
        <Button type="button" size="sm" className="mt-3" disabled={savingPaymentMethods} onClick={() => void savePaymentMethods()}>
          {t("paymentMethodsSave")}
        </Button>
      </PaymentMethodSection>

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{form.id > 0 ? t("editCard") : t("addCard")}</DialogTitle>
            <DialogDescription>{methodLabel(form.method_key)}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="grid gap-1.5">
              <Label>{t("method")}</Label>
              <select className="h-8 rounded-lg border border-input bg-background px-2 text-sm" value={form.method_key} onChange={(e) => setForm((f) => ({ ...f, method_key: e.target.value as MethodKey }))}>
                {METHOD_KEYS.map((key) => (
                  <option key={key} value={key}>
                    {methodLabel(key)}
                  </option>
                ))}
              </select>
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="card-primary">{isGatewayMethod(form.method_key) ? t("field_walletAddress") : t("cardNumber")}</Label>
              <Input id="card-primary" value={form.card_number} onChange={(e) => setForm((f) => ({ ...f, card_number: e.target.value }))} />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="card-holder">{t("holderName")}</Label>
              <Input id="card-holder" value={form.holder_name} onChange={(e) => setForm((f) => ({ ...f, holder_name: e.target.value }))} />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="card-bank">{t("bankName")}</Label>
              <Input id="card-bank" value={form.bank_name} onChange={(e) => setForm((f) => ({ ...f, bank_name: e.target.value }))} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-1.5">
                <Label htmlFor="card-limit">{t("dailyLimit")}</Label>
                <Input id="card-limit" type="number" value={form.daily_limit} onChange={(e) => setForm((f) => ({ ...f, daily_limit: num(e.target.value) }))} />
              </div>
              <div className="grid gap-1.5">
                <Label htmlFor="card-priority">{t("priority")}</Label>
                <Input id="card-priority" type="number" value={form.priority} onChange={(e) => setForm((f) => ({ ...f, priority: num(e.target.value) }))} />
              </div>
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="card-note">{t("note")}</Label>
              <Textarea id="card-note" value={form.note} onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))} />
            </div>
            <Label className="flex items-center gap-2">
              <Switch checked={form.active} onCheckedChange={(active) => setForm((f) => ({ ...f, active }))} />
              {t("active")}
            </Label>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void saveForm()}>
              {t("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={deleteTarget != null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
            <DialogDescription>{t("deleteDescription")}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
              {t("deleteCancel")}
            </Button>
            <Button type="button" variant="destructive" disabled={saving} onClick={() => void deleteCard()}>
              {t("deleteConfirm")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={gatewaySheet === "zarinpal"} onOpenChange={(open) => !open && setGatewaySheet(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("zarinpalTitle")}</DialogTitle>
            <DialogDescription>{zarinpalConfigured ? t("zarinpalMerchantConfigured") : t("zarinpalMerchantMissing")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="grid gap-1.5">
              <Label>{t("zarinpalMerchantIdLabel")}</Label>
              <Input value={zarinpalMerchantId} onChange={(e) => setZarinpalMerchantId(e.target.value)} placeholder={zarinpalConfigured ? "••••••••" : ""} />
            </div>
            <Label className="flex items-center gap-2">
              <Switch checked={zarinpalSandboxDraft} onCheckedChange={setZarinpalSandboxDraft} />
              {t("zarinpalSandbox")}
            </Label>
            {String(settings.zarinpal_callback_url ?? "").trim() ? (
              <p className="break-all text-xs text-muted-foreground">
                {t("zarinpalCallbackUrl")}: {String(settings.zarinpal_callback_url)}
              </p>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setGatewaySheet(null)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void saveZarinpalSettings()}>
              {t("zarinpalSettingsSave")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={gatewaySheet === "aqayepardakht"} onOpenChange={(open) => !open && setGatewaySheet(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("aqayepardakhtTitle")}</DialogTitle>
            <DialogDescription>{aqayepardakhtConfigured ? t("aqayepardakhtPinConfigured") : t("aqayepardakhtPinMissing")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="grid gap-1.5">
              <Label>{t("aqayepardakhtPinLabel")}</Label>
              <Input value={aqayepardakhtPin} onChange={(e) => setAqayepardakhtPin(e.target.value)} placeholder={aqayepardakhtConfigured ? "••••••••" : ""} />
            </div>
            <Label className="flex items-center gap-2">
              <Switch checked={aqayepardakhtSandboxDraft} onCheckedChange={setAqayepardakhtSandboxDraft} />
              {t("aqayepardakhtSandbox")}
            </Label>
            {String(settings.aqayepardakht_callback_url ?? "").trim() ? (
              <p className="break-all text-xs text-muted-foreground">
                {t("aqayepardakhtCallbackUrl")}: {String(settings.aqayepardakht_callback_url)}
              </p>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setGatewaySheet(null)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void saveAqayepardakhtSettings()}>
              {t("aqayepardakhtSettingsSave")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={gatewaySheet === "zibal"} onOpenChange={(open) => !open && setGatewaySheet(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("zibalTitle")}</DialogTitle>
            <DialogDescription>{zibalConfigured ? t("zibalMerchantConfigured") : t("zibalMerchantMissing")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="grid gap-1.5">
              <Label>{t("zibalMerchantLabel")}</Label>
              <Input value={zibalMerchant} onChange={(e) => setZibalMerchant(e.target.value)} placeholder={zibalConfigured ? "••••••••" : ""} />
            </div>
            <Label className="flex items-center gap-2">
              <Switch checked={zibalSandboxDraft} onCheckedChange={setZibalSandboxDraft} />
              {t("zibalSandbox")}
            </Label>
            {String(settings.zibal_callback_url ?? "").trim() ? (
              <p className="break-all text-xs text-muted-foreground">
                {t("zibalCallbackUrl")}: {String(settings.zibal_callback_url)}
              </p>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setGatewaySheet(null)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void saveZibalSettings()}>
              {t("zibalSettingsSave")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={gatewaySheet === "tetra"} onOpenChange={(open) => !open && setGatewaySheet(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("cryptoTetraTitle")}</DialogTitle>
            <DialogDescription>{tetraConfigured ? t("tetraApiConfigured") : t("tetraApiMissing")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="grid gap-1.5">
              <Label>{t("tetraApiKeyLabel")}</Label>
              <Input value={tetraApiKey} onChange={(e) => setTetraApiKey(e.target.value)} placeholder={tetraConfigured ? "••••••••" : ""} />
            </div>
            {String(settings.crypto_tetra_callback_url ?? "").trim() ? (
              <p className="break-all text-xs text-muted-foreground">
                {t("tetraCallbackUrl")}: {String(settings.crypto_tetra_callback_url)}
              </p>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setGatewaySheet(null)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void saveTetraApiKey()}>
              {t("tetraApiKeySave")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={gatewaySheet === "nowpayments"} onOpenChange={(open) => !open && setGatewaySheet(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("cryptoNowPaymentsTitle")}</DialogTitle>
            <DialogDescription>
              {nowPaymentsConfigured ? t("nowPaymentsApiConfigured") : t("nowPaymentsApiMissing")}
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="grid gap-1.5">
              <Label>{tFinance("cryptoApiKey")}</Label>
              <Input
                value={nowPaymentsApiKey}
                onChange={(e) => setNowPaymentsApiKey(e.target.value)}
                placeholder={nowPaymentsConfigured ? "••••••••" : ""}
              />
            </div>
            <div className="grid gap-1.5">
              <Label>{tFinance("cryptoIpnSecret")}</Label>
              <Input
                value={nowPaymentsIpnSecret}
                onChange={(e) => setNowPaymentsIpnSecret(e.target.value)}
                placeholder="••••••••"
              />
            </div>
            <div className="grid gap-1.5">
              <Label>{tFinance("cryptoPayCurrency")}</Label>
              <Input value={nowPaymentsPayCurrency} onChange={(e) => setNowPaymentsPayCurrency(e.target.value)} />
            </div>
            <div className="grid gap-1.5">
              <Label>Toman / USD</Label>
              <Input value={nowPaymentsTomanPerUsd} onChange={(e) => setNowPaymentsTomanPerUsd(e.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setGatewaySheet(null)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void saveNowPaymentsSettings()}>
              {tFinance("cryptoSave")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
