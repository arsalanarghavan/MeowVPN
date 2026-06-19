import { randomUUID } from "node:crypto"
import { spawn } from "node:child_process"

export type JobStatus = "pending" | "running" | "done" | "failed"

export type AdminJob = {
  id: string
  type: string
  status: JobStatus
  created_at: string
  finished_at: string | null
  output: string
  error: string | null
}

const jobs = new Map<string, AdminJob>()
const MAX_JOBS = 50

function trimJobs(): void {
  if (jobs.size <= MAX_JOBS) return
  const sorted = [...jobs.values()].sort((a, b) => a.created_at.localeCompare(b.created_at))
  for (let i = 0; i < sorted.length - MAX_JOBS; i++) {
    jobs.delete(sorted[i].id)
  }
}

export function getJob(id: string): AdminJob | null {
  return jobs.get(id) || null
}

export function listJobs(): AdminJob[] {
  return [...jobs.values()].sort((a, b) => b.created_at.localeCompare(a.created_at))
}

export function runJob(type: string, cmd: string, args: string[]): AdminJob {
  const job: AdminJob = {
    id: randomUUID(),
    type,
    status: "running",
    created_at: new Date().toISOString(),
    finished_at: null,
    output: "",
    error: null,
  }
  jobs.set(job.id, job)
  trimJobs()

  const child = spawn(cmd, args, { shell: false, env: process.env })
  let out = ""
  child.stdout?.on("data", (c) => {
    out += String(c)
    job.output = out.slice(-8000)
  })
  child.stderr?.on("data", (c) => {
    out += String(c)
    job.output = out.slice(-8000)
  })
  child.on("close", (code) => {
    job.finished_at = new Date().toISOString()
    if (code === 0) {
      job.status = "done"
    } else {
      job.status = "failed"
      job.error = `exit ${code}`
    }
  })
  child.on("error", (err) => {
    job.finished_at = new Date().toISOString()
    job.status = "failed"
    job.error = err.message
  })

  return job
}
