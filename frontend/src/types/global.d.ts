export {}

declare global {
  interface Window {
    __SIMPLEVPBOT_DASH__?: {
      siteTimeZone?: string
      lang?: string
      restUrl?: string
      [key: string]: unknown
    }
  }
}
