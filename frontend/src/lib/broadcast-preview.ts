/** Client-side Bale Markdown preview (mirrors server rules loosely). */

function stripTags(html: string): string {
  return html.replace(/<[^>]*>/g, "")
}

function escapeLinkLabel(text: string): string {
  return text.replace(/[[\]()]/g, (c) => `\\${c}`)
}

function baleBold(inner: string): string {
  const t = inner.trim()
  return t ? ` *${t}* ` : ""
}

function baleItalic(inner: string): string {
  const t = inner.trim()
  return t ? ` _${t}_ ` : ""
}

/** Rough HTML → Bale Markdown for dashboard preview. */
export function htmlToBalePreviewMarkdown(html: string): string {
  let t = html
    .replace(/\r\n?/g, "\n")
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<\/div>\s*<div[^>]*>/gi, "\n")
    .replace(/<div[^>]*>/gi, "")
    .replace(/<\/div>/gi, "\n")
    .replace(/<\/p>\s*<p[^>]*>/gi, "\n")
    .replace(/<p[^>]*>/gi, "")
    .replace(/<\/p>/gi, "\n")

  t = t.replace(/<(b|strong)>(.*?)<\/\1>/gi, (_, __, inner: string) => baleBold(inner))
  t = t.replace(/<(i|em)>(.*?)<\/\1>/gi, (_, __, inner: string) => baleItalic(inner))
  t = t.replace(
    /<a\b[^>]*href=["']([^"']+)["'][^>]*>(.*?)<\/a>/gi,
    (_, href: string, label: string) => {
      const lab = stripTags(label).trim() || href
      return `[${escapeLinkLabel(lab)}](${href})`
    },
  )
  t = t.replace(/<pre[^>]*>(.*?)<\/pre>/gi, (_, inner: string) => `\n${stripTags(inner).trim()}\n`)
  t = t.replace(/<code[^>]*>(.*?)<\/code>/gi, (_, inner: string) => stripTags(inner))
  t = t.replace(/<blockquote[^>]*>(.*?)<\/blockquote>/gi, (_, inner: string) => {
    const plain = stripTags(inner).replace(/\s+/g, " ").trim()
    return plain ? `\n« ${plain} »\n` : ""
  })
  t = t.replace(/<tg-spoiler[^>]*>(.*?)<\/tg-spoiler>/gi, (_, inner: string) => `▒${stripTags(inner).trim()}▒`)
  t = t.replace(/<span[^>]*class=["'][^"']*tg-spoiler[^"']*["'][^>]*>(.*?)<\/span>/gi, (_, inner: string) =>
    `▒${stripTags(inner).trim()}▒`,
  )
  t = stripTags(t)
  t = t.replace(/\n{3,}/g, "\n\n").trim()
  return t
}

/** Features that Bale Markdown does not support like Telegram HTML. */
export function hasBaleUnsupportedFeatures(html: string): boolean {
  return /<(u|ins|s|strike|del)\b/i.test(html)
}

/** Preview HTML with visible line breaks (canonical uses \\n). */
export function htmlForTelegramPreview(html: string): string {
  if (!html) return ""
  return html.replace(/\n/g, "<br>")
}
