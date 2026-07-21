import type { UiButtonStyle, UiStudioCell } from "./types"

export const TELEGRAM_BUTTON_CLASS: Record<UiButtonStyle, string> = {
  "": "bg-muted/80 text-foreground border border-border/60",
  primary: "bg-[#3390ec] text-white border border-[#2b7fd4]",
  success: "bg-[#28a745] text-white border border-[#218838]",
  danger: "bg-[#dc3545] text-white border border-[#c82333]",
}

export function pickLabelPreview(
  textKey: string,
  textDefaults: Record<string, unknown> | undefined,
  isFa: boolean
): string {
  if (!textDefaults || !textKey) return textKey
  const row = textDefaults[textKey]
  if (row && typeof row === "object" && row !== null && ("fa" in row || "en" in row)) {
    const o = row as { fa?: unknown; en?: unknown }
    const s = isFa ? String(o.fa ?? "") : String(o.en ?? "")
    return s || textKey
  }
  if (typeof row === "string") return row || textKey
  return textKey
}

export function cellLabel(
  cellId: string,
  meta: { textKey?: string; labelFa?: string; labelEn?: string } | undefined,
  textDefaults: Record<string, unknown> | undefined,
  isFa: boolean,
  glass: boolean
): string {
  const tk = meta?.textKey ?? ""
  const regTitle = isFa ? meta?.labelFa : meta?.labelEn
  const base =
    regTitle && String(regTitle).trim() !== ""
      ? String(regTitle)
      : tk
        ? pickLabelPreview(tk, textDefaults, isFa)
        : cellId
  return glass ? `⟨${base}⟩` : base
}

export type { UiStudioCell }
