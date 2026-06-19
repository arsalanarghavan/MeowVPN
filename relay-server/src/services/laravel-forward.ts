import type { ForwardJob } from "../types.js"
import { env } from "../env.js"

const queue: ForwardJob[] = []
let draining = false

function scheduleDrain(): void {
  if (draining) return
  draining = true
  setImmediate(() => {
    void drainLoop().finally(() => {
      draining = false
      if (queue.length > 0) scheduleDrain()
    })
  })
}

async function drainLoop(): Promise<void> {
  const now = Date.now()
  const ready = queue.filter((j) => j.nextAt <= now)
  if (ready.length === 0) {
    if (queue.length > 0) {
      const wait = Math.max(50, queue[0].nextAt - now)
      setTimeout(scheduleDrain, wait)
    }
    return
  }
  for (const job of ready) {
    const idx = queue.indexOf(job)
    if (idx >= 0) queue.splice(idx, 1)
    const ok = await forwardOnce(job)
    if (!ok && job.tries < env.forwardMaxRetries) {
      job.tries += 1
      job.nextAt = Date.now() + Math.min(30000, 500 * 2 ** job.tries)
      queue.push(job)
    }
  }
}

async function forwardOnce(job: ForwardJob): Promise<boolean> {
  const ctrl = new AbortController()
  const t = setTimeout(() => ctrl.abort(), env.forwardTimeoutMs)
  try {
    const res = await fetch(job.url, {
      method: "POST",
      headers: {
        "content-type": "application/json",
        ...job.headers,
      },
      body: job.body,
      signal: ctrl.signal,
    })
    if (res.status >= 200 && res.status < 300) {
      return true
    }
    console.error(`[relay] forward failed status=${res.status} url=${job.url}`)
    return false
  } catch (err) {
    console.error(`[relay] forward error url=${job.url}`, err)
    return false
  } finally {
    clearTimeout(t)
  }
}

export function enqueueForward(url: string, body: string, headers: Record<string, string>): void {
  queue.push({
    id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
    url,
    body,
    headers,
    tries: 0,
    nextAt: Date.now(),
  })
  scheduleDrain()
}

export function forwardQueueDepth(): number {
  return queue.length
}

/** @internal test helper */
export function peekForwardQueueUrls(): string[] {
  return queue.map((j) => j.url)
}
