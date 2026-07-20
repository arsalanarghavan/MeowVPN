import { useCallback, useEffect, useRef, useState } from "react"

export type LiveMetricsSsePayload = {
  ok?: boolean
  ts?: number
  collected_at?: number
  version?: string
  livePanelSnapshots?: Array<Record<string, unknown>>
}

type UseLiveMetricsSseOpts = {
  enabled: boolean
  /** API base including `/api/v1` (e.g. from `apiBase()`). */
  restBase: string
  /** @deprecated WP nonce; unused on Laravel Sanctum cookie auth. */
  nonce?: string
  onPayload: (payload: LiveMetricsSsePayload) => void
}

/**
 * Subscribe to server-sent live panel metrics (monitoring / dashboard tabs).
 * Laravel: GET /api/v1/admin/live-stream (Sanctum cookie).
 */
export function useLiveMetricsSse({ enabled, restBase, onPayload }: UseLiveMetricsSseOpts) {
  const onPayloadRef = useRef(onPayload)
  const [connected, setConnected] = useState(false)
  onPayloadRef.current = onPayload

  const stableOnPayload = useCallback((payload: LiveMetricsSsePayload) => {
    onPayloadRef.current(payload)
  }, [])

  useEffect(() => {
    if (!enabled || !restBase) {
      setConnected(false)
      return
    }
    const base = restBase.replace(/\/$/, "")
    const url = `${base}/admin/live-stream`
    let es: EventSource | null = null
    let closed = false

    const open = () => {
      if (closed) return
      es = new EventSource(url, { withCredentials: true })
      es.addEventListener("open", () => {
        if (!closed) setConnected(true)
      })
      es.addEventListener("metrics", (ev) => {
        try {
          const raw = (ev as MessageEvent<string>).data
          const json = JSON.parse(raw) as LiveMetricsSsePayload
          stableOnPayload(json)
        } catch {
          /* ignore malformed */
        }
      })
      es.onmessage = (ev) => {
        try {
          const json = JSON.parse(ev.data) as LiveMetricsSsePayload
          stableOnPayload(json)
        } catch {
          /* ignore */
        }
      }
      es.onerror = () => {
        setConnected(false)
        es?.close()
        es = null
        if (!closed && document.visibilityState === "visible") {
          window.setTimeout(open, 5000)
        }
      }
    }

    open()

    const onVis = () => {
      if (document.visibilityState === "hidden") {
        setConnected(false)
        es?.close()
        es = null
      } else if (!es) {
        open()
      }
    }
    document.addEventListener("visibilitychange", onVis)

    return () => {
      closed = true
      setConnected(false)
      document.removeEventListener("visibilitychange", onVis)
      es?.close()
    }
  }, [enabled, restBase, stableOnPayload])

  return { connected }
}
