"use client"

import { useCallback, useEffect, useRef } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

function selectionHtml(): string {
  const sel = window.getSelection()
  if (!sel || sel.rangeCount === 0) return ""
  const range = sel.getRangeAt(0)
  const frag = range.cloneContents()
  const div = document.createElement("div")
  div.appendChild(frag)
  return div.innerHTML
}

function insertHtml(html: string) {
  document.execCommand("insertHTML", false, html)
}

function escapeForPreFragment(text: string): string {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
}

/** Strip browser-specific markup from contentEditable output. */
function normalizeEditorHtml(html: string): string {
  let t = html
  t = t.replace(/<span[^>]*style="[^"]*"[^>]*>(.*?)<\/span>/gis, "$1")
  t = t.replace(/<font[^>]*>(.*?)<\/font>/gis, "$1")
  return t
}

export function BroadcastRichEditor({
  value,
  onChange,
  disabled,
  placeholder,
}: {
  value: string
  onChange: (html: string) => void
  disabled?: boolean
  placeholder?: string
}) {
  const { t } = useTranslation()
  const { isFa, dir } = useDashLocale()
  const tip = (key: string) => t(`broadcastAdmin.${key}`)
  const ref = useRef<HTMLDivElement>(null)
  const lastEmitted = useRef(value)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    if (value === lastEmitted.current) return
    lastEmitted.current = value
    if (value === "") {
      el.innerHTML = ""
      return
    }
    if (el.innerHTML !== value) {
      el.innerHTML = value
    }
  }, [value])

  const emit = useCallback(() => {
    const el = ref.current
    if (!el) return
    const next = normalizeEditorHtml(el.innerHTML)
    lastEmitted.current = next
    onChange(next)
  }, [onChange])

  const runBold = useCallback(() => {
    ref.current?.focus()
    document.execCommand("bold", false)
    emit()
  }, [emit])

  const runItalic = useCallback(() => {
    ref.current?.focus()
    document.execCommand("italic", false)
    emit()
  }, [emit])

  const runUnderline = useCallback(() => {
    ref.current?.focus()
    document.execCommand("underline", false)
    emit()
  }, [emit])

  const runStrike = useCallback(() => {
    ref.current?.focus()
    document.execCommand("strikeThrough", false)
    emit()
  }, [emit])

  const runMono = useCallback(() => {
    ref.current?.focus()
    const inner = selectionHtml() || "…"
    insertHtml(`<code>${inner}</code>`)
    emit()
  }, [emit])

  const runPre = useCallback(() => {
    ref.current?.focus()
    const raw = window.getSelection()?.toString() || "…"
    const inner = escapeForPreFragment(raw)
    insertHtml(`<pre>${inner}</pre>`)
    emit()
  }, [emit])

  const runSpoiler = useCallback(() => {
    ref.current?.focus()
    const inner = selectionHtml() || "…"
    insertHtml(`<tg-spoiler>${inner}</tg-spoiler>`)
    emit()
  }, [emit])

  const runQuote = useCallback(() => {
    ref.current?.focus()
    const inner = selectionHtml() || "…"
    insertHtml(`<blockquote>${inner}</blockquote>`)
    emit()
  }, [emit])

  const runLink = useCallback(() => {
    ref.current?.focus()
    const url = window.prompt("https://…")
    if (!url || !url.trim()) return
    const safe = url.trim().replace(/"/g, "&quot;")
    const inner = selectionHtml() || safe
    insertHtml(`<a href="${safe}">${inner}</a>`)
    emit()
  }, [emit])

  const btn = (
    label: string,
    titleKey: string,
    onClick: () => void,
    extraClass?: string,
  ) => (
    <Button
      type="button"
      variant="secondary"
      size="sm"
      className={cn("h-8 px-2 text-xs", extraClass)}
      disabled={disabled}
      title={tip(titleKey)}
      onClick={onClick}
    >
      {label}
    </Button>
  )

  return (
    <div className="min-w-0 space-y-2">
      <div
        className={cn(
          "flex flex-wrap gap-1 rounded-md border border-input bg-muted/30 p-1",
        )}
      >
        {btn("B", "editorTipBold", runBold, "font-bold")}
        {btn("I", "editorTipItalic", runItalic, "italic")}
        {btn(t("broadcastAdmin.editorBtnMono"), "editorTipMono", runMono, "font-mono")}
        {btn(t("broadcastAdmin.editorBtnPre"), "editorTipPre", runPre, "font-mono")}
        {btn("U", "editorTipUnderline", runUnderline, "underline")}
        {btn("S", "editorTipStrike", runStrike, "line-through")}
        {btn(t("broadcastAdmin.editorBtnSpoiler"), "editorTipSpoiler", runSpoiler)}
        {btn(t("broadcastAdmin.editorBtnQuote"), "editorTipQuote", runQuote)}
        {btn(t("broadcastAdmin.editorBtnLink"), "editorTipLink", runLink)}
      </div>
      <div
        ref={ref}
        className={cn(
          "min-h-[8rem] w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-start text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
          isFa && "whitespace-pre-wrap",
        )}
        contentEditable={!disabled}
        dir={dir}
        suppressContentEditableWarning
        data-placeholder={placeholder || ""}
        onInput={emit}
        onBlur={emit}
      />
      <style>{`
        [contenteditable][data-placeholder]:empty:before {
          content: attr(data-placeholder);
          color: var(--muted-foreground);
          pointer-events: none;
        }
      `}</style>
    </div>
  )
}
