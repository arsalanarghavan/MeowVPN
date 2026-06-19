import i18n from "i18next"
import { initReactI18next } from "react-i18next"
import { buildDashboardResources } from "../../shared/locales/dashboard"

const bootstrap = window.__SIMPLEVPBOT_DASH__ || {}
const initialLang = bootstrap.lang === "fa" ? "fa" : "en"

const resources = buildDashboardResources()

void i18n.use(initReactI18next).init({
  lng: initialLang,
  fallbackLng: "en",
  resources,
  interpolation: { escapeValue: false },
})

export default i18n
