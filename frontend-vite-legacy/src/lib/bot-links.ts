/** Build external bot URL (Telegram / Bale). */
export function botPlatformUrl(platform: "telegram" | "bale", username: string): string | null {
  const u = String(username || "")
    .trim()
    .replace(/^@+/, "")
  if (!u) return null
  if (platform === "bale") {
    return `https://ble.ir/${encodeURIComponent(u)}`
  }
  return `https://t.me/${encodeURIComponent(u)}`
}
