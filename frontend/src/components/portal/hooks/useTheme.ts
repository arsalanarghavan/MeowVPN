import { useEffect, useState } from "react"

export type ThemeMode = "light" | "dark" | "system"

export function useTheme() {
  const [mode, setMode] = useState<ThemeMode>(() => {
    const saved = localStorage.getItem("svp-portal-theme")
    return saved === "light" || saved === "dark" || saved === "system" ? saved : "dark"
  })

  useEffect(() => {
    localStorage.setItem("svp-portal-theme", mode)
    const root = document.documentElement
    const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches
    const dark = mode === "dark" || (mode === "system" && prefersDark)
    root.dataset.theme = dark ? "dark" : "light"
  }, [mode])

  return { mode, setMode }
}
