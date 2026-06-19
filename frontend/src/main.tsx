import { StrictMode } from "react"
import { createRoot } from "react-dom/client"
import { ThemeProvider } from "next-themes"
import { TooltipProvider } from "@/components/ui/tooltip"
import "./index.css"
import "./lib/i18n"
import App from "./App"

const bootTheme = window.__SIMPLEVPBOT_DASH__?.uiTheme
const defaultTheme =
  bootTheme === "light" || bootTheme === "dark" || bootTheme === "system" ? bootTheme : "system"

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <ThemeProvider
      attribute="class"
      defaultTheme={defaultTheme}
      storageKey="svp-dashboard-theme"
      enableSystem
    >
      <TooltipProvider>
        <App />
      </TooltipProvider>
    </ThemeProvider>
  </StrictMode>
)
