package stats

import (
	"encoding/json"
	"os/exec"
)

// Collect uses `xray api statsquery` when available; returns email -> {up, down}.
func Collect(configPath string) (map[string]map[string]int64, error) {
	cmd := exec.Command("xray", "api", "statsquery", "-c", configPath)
	out, err := cmd.Output()
	if err != nil {
		return map[string]map[string]int64{}, err
	}
	var payload struct {
		Stat []struct {
			Name  string `json:"name"`
			Value int64  `json:"value"`
		} `json:"stat"`
	}
	if err := json.Unmarshal(out, &payload); err != nil {
		return map[string]map[string]int64{}, err
	}
	result := map[string]map[string]int64{}
	for _, s := range payload.Stat {
		email, dir := parseStatName(s.Name)
		if email == "" {
			continue
		}
		if result[email] == nil {
			result[email] = map[string]int64{"up": 0, "down": 0}
		}
		if dir == "uplink" {
			result[email]["up"] += s.Value
		} else if dir == "downlink" {
			result[email]["down"] += s.Value
		}
	}
	return result, nil
}

func parseStatName(name string) (email, dir string) {
	// user>>>email@domain>>>traffic>>>uplink
	parts := splitStat(name)
	if len(parts) < 4 {
		return "", ""
	}
	return parts[1], parts[3]
}

func splitStat(name string) []string {
	var out []string
	cur := ""
	for _, ch := range name {
		if ch == '>' {
			if cur != "" {
				out = append(out, cur)
				cur = ""
			}
			continue
		}
		cur += string(ch)
	}
	if cur != "" {
		out = append(out, cur)
	}
	return out
}
