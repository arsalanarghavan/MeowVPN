import { randomUUID } from "node:crypto";
import { spawn } from "node:child_process";
const jobs = new Map();
const MAX_JOBS = 50;
function trimJobs() {
    if (jobs.size <= MAX_JOBS)
        return;
    const sorted = [...jobs.values()].sort((a, b) => a.created_at.localeCompare(b.created_at));
    for (let i = 0; i < sorted.length - MAX_JOBS; i++) {
        jobs.delete(sorted[i].id);
    }
}
export function getJob(id) {
    return jobs.get(id) || null;
}
export function listJobs() {
    return [...jobs.values()].sort((a, b) => b.created_at.localeCompare(a.created_at));
}
export function runJob(type, cmd, args) {
    const job = {
        id: randomUUID(),
        type,
        status: "running",
        created_at: new Date().toISOString(),
        finished_at: null,
        output: "",
        error: null,
    };
    jobs.set(job.id, job);
    trimJobs();
    const child = spawn(cmd, args, { shell: false, env: process.env });
    let out = "";
    child.stdout?.on("data", (c) => {
        out += String(c);
        job.output = out.slice(-8000);
    });
    child.stderr?.on("data", (c) => {
        out += String(c);
        job.output = out.slice(-8000);
    });
    child.on("close", (code) => {
        job.finished_at = new Date().toISOString();
        if (code === 0) {
            job.status = "done";
        }
        else {
            job.status = "failed";
            job.error = `exit ${code}`;
        }
    });
    child.on("error", (err) => {
        job.finished_at = new Date().toISOString();
        job.status = "failed";
        job.error = err.message;
    });
    return job;
}
