import express from "express"
import { env } from "./env.js"
import { migrateLegacyConfigIfNeeded } from "./store.js"
import { webhookRouter } from "./routes/webhook.js"
import { botProxyRouter } from "./routes/bot-proxy.js"
import { internalRouter } from "./routes/internal.js"
import { adminRouter } from "./routes/admin.js"

migrateLegacyConfigIfNeeded()

const app = express()

app.use(express.json({ limit: "8mb" }))

app.get("/health", (_req, res) => {
  res.json({ ok: true, service: "simplevpbot-telegram-relay" })
})

app.use(webhookRouter)
app.use(botProxyRouter)
app.use(internalRouter)
app.use(adminRouter)

app.listen(env.port, env.bindHost, () => {
  console.log(`[relay] listening on ${env.bindHost}:${env.port}`)
})
