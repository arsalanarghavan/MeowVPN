package api

import (
	"encoding/json"
	"net/http"
	"os"
	"os/exec"
	"syscall"
	"time"

	"github.com/meowvpn/node-agent/internal/stats"
	"github.com/meowvpn/node-agent/internal/xray"
)

type Server struct {
	xray *xray.Manager
}

func NewServer(configPath, xrayBin string) *Server {
	return &Server{xray: xray.NewManager(configPath, xrayBin)}
}

func (s *Server) ListenAndServe(addr, certFile, keyFile string) error {
	mux := http.NewServeMux()
	mux.HandleFunc("/health", s.handleHealth)
	mux.HandleFunc("/apply", s.handleApply)
	mux.HandleFunc("/restart", s.handleRestart)
	mux.HandleFunc("/stats", s.handleStats)

	srv := &http.Server{Addr: addr, Handler: mux, ReadHeaderTimeout: 10 * time.Second}
	if certFile != "" && keyFile != "" {
		return srv.ListenAndServeTLS(certFile, keyFile)
	}
	return srv.ListenAndServe()
}

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"ok": false})
		return
	}
	running := s.xray.IsRunning()
	status := "stopped"
	if running {
		status = "running"
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "status": status, "xray_running": running})
}

func (s *Server) handleApply(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"ok": false})
		return
	}
	var body struct {
		Config map[string]any `json:"config"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil || body.Config == nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"ok": false, "reason": "invalid_json"})
		return
	}
	if err := s.xray.WriteConfig(body.Config); err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]any{"ok": false, "reason": "write_failed", "detail": err.Error()})
		return
	}
	if err := s.xray.Reload(); err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]any{"ok": false, "reason": "reload_failed", "detail": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) handleRestart(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"ok": false})
		return
	}
	if err := s.xray.Restart(); err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]any{"ok": false, "reason": "restart_failed", "detail": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) handleStats(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"ok": false})
		return
	}
	data, err := stats.Collect(s.xray.ConfigPath())
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"ok": true, "stats": map[string]any{}})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "stats": data})
}

func writeJSON(w http.ResponseWriter, code int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	_ = json.NewEncoder(w).Encode(v)
}

func runCmd(name string, args ...string) error {
	cmd := exec.Command(name, args...)
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	return cmd.Run()
}

func signalProcess(pid int, sig syscall.Signal) error {
	p, err := os.FindProcess(pid)
	if err != nil {
		return err
	}
	return p.Signal(sig)
}
