# meow-node-agent

Lightweight Xray node agent for MeowVPN native panel.

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Process health |
| POST | `/apply` | Write config JSON and reload Xray |
| POST | `/restart` | Restart Xray |
| GET | `/stats` | Traffic stats by client email |

## Run

```bash
go build -o meow-node-agent ./cmd/agent
sudo ./meow-node-agent -addr :8444 -config /etc/xray/config.json -cert server.crt -key server.key
```

Configure `agent_url` on the Xray node in MeowVPN dashboard (e.g. `https://node-ip:8444`).
