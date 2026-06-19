/**
 * Gregorian → Jalali (Shamsi), same algorithm as PHP SimpleVPBot_Jalali_Date::gregorian_to_jalali.
 */

export function gregorianToJalali(gy: number, gm: number, gd: number): [number, number, number] {
  const gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
  const jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29]

  let gY = gy - 1600
  let gM = gm - 1
  let gD = gd - 1

  let gDayNo =
    365 * gY +
    Math.floor((gY + 3) / 4) -
    Math.floor((gY + 99) / 100) +
    Math.floor((gY + 399) / 400)

  for (let i = 0; i < gM; i++) {
    gDayNo += gDaysInMonth[i]!
  }
  if (gM > 1 && ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0)) {
    gDayNo++
  }
  gDayNo += gD

  let jDayNo = gDayNo - 79
  const jNp = Math.floor(jDayNo / 12053)
  jDayNo %= 12053

  let jy = 979 + 33 * jNp + 4 * Math.floor(jDayNo / 1461)
  jDayNo %= 1461

  if (jDayNo >= 366) {
    jy += Math.floor((jDayNo - 1) / 365)
    jDayNo = (jDayNo - 1) % 365
  }

  let j = 0
  for (; j < 11 && jDayNo >= jDaysInMonth[j]!; j++) {
    jDayNo -= jDaysInMonth[j]!
  }
  const jm = j + 1
  const jd = jDayNo + 1
  return [jy, jm, jd]
}

/**
 * Jalali → Gregorian using the same `gregorianToJalali` (inverse search in UTC noon space).
 * Covers typical dashboard years (~1300–1500 شمسی) with a small fixed window.
 */
export function jalaliToGregorian(jy: number, jm: number, jd: number): [number, number, number] {
  const y = Math.trunc(jy)
  const m = Math.trunc(jm)
  const d = Math.trunc(jd)
  if (y < 1 || m < 1 || m > 12 || d < 1 || d > 31) {
    return [2000, 1, 1]
  }
  const gyGuess = y + 621
  const anchor = Date.UTC(gyGuess, 2, 1, 12, 0, 0)
  for (const span of [220, 500]) {
    for (let delta = -span; delta <= span; delta++) {
      const t = anchor + delta * 86400000
      const dt = new Date(t)
      const gy = dt.getUTCFullYear()
      const gm = dt.getUTCMonth() + 1
      const gd = dt.getUTCDate()
      const [jjy, jjm, jjd] = gregorianToJalali(gy, gm, gd)
      if (jjy === y && jjm === m && jjd === d) {
        return [gy, gm, gd]
      }
    }
  }
  return [gyGuess, 3, 1]
}

/** Format API date `YYYY-MM-DD` as Jalali `YYYY/MM/DD` for display hints. */
export function formatJalaliDateFromIso(iso: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso.trim())
  if (!m) return ""
  const [jy, jm, jd] = gregorianToJalali(Number(m[1]), Number(m[2]), Number(m[3]))
  const pad = (n: number) => String(n).padStart(2, "0")
  return `${jy}/${pad(jm)}/${pad(jd)}`
}
