/** Mask card number for display (digits only, last 4 visible). */
export function formatRedactedCardNumber(raw: unknown): string {
  if (raw == null || raw === "") return "—"
  const digits = String(raw).replace(/[^\d]/g, "")
  return digits.length > 4 ? `••••…${digits.slice(-4)}` : "••••"
}
