package xray

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"sync"
)

type Manager struct {
	configPath string
	xrayBin    string
	mu         sync.Mutex
	cmd        *exec.Cmd
}

func NewManager(configPath, xrayBin string) *Manager {
	return &Manager{configPath: configPath, xrayBin: xrayBin}
}

func (m *Manager) ConfigPath() string {
	return m.configPath
}

func (m *Manager) WriteConfig(cfg map[string]any) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	if err := os.MkdirAll(filepath.Dir(m.configPath), 0o755); err != nil {
		return err
	}
	raw, err := json.MarshalIndent(cfg, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(m.configPath, raw, 0o644)
}

func (m *Manager) IsRunning() bool {
	m.mu.Lock()
	defer m.mu.Unlock()
	return m.cmd != nil && m.cmd.Process != nil
}

func (m *Manager) Reload() error {
	m.mu.Lock()
	defer m.mu.Unlock()
	if m.cmd == nil || m.cmd.Process == nil {
		return m.startLocked()
	}
	return m.runXrayApi("reload")
}

func (m *Manager) Restart() error {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.stopLocked()
	return m.startLocked()
}

func (m *Manager) startLocked() error {
	m.cmd = exec.Command(m.xrayBin, "run", "-c", m.configPath)
	m.cmd.Stdout = os.Stdout
	m.cmd.Stderr = os.Stderr
	if err := m.cmd.Start(); err != nil {
		return err
	}
	go func(c *exec.Cmd) {
		_ = c.Wait()
	}(m.cmd)
	return nil
}

func (m *Manager) stopLocked() {
	if m.cmd != nil && m.cmd.Process != nil {
		_ = m.cmd.Process.Kill()
	}
	m.cmd = nil
}

func (m *Manager) runXrayApi(subcmd string) error {
	cmd := exec.Command(m.xrayBin, "api", subcmd, "-c", m.configPath)
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	return cmd.Run()
}
