import { NextIntlClientProvider } from "next-intl"
import { getMessages, setRequestLocale } from "next-intl/server"
import { notFound } from "next/navigation"
import { ThemeProvider } from "@/components/theme-provider"
import { TooltipProvider } from "@/components/ui/tooltip"
import { isLocale, localeDirection, locales, type Locale } from "@/i18n/config"
import "../globals.css"

export function generateStaticParams() {
  return locales.map((locale) => ({ locale }))
}

export default async function LocaleLayout({
  children,
  params,
}: {
  children: React.ReactNode
  params: Promise<{ locale: string }> | { locale: string }
}) {
  const resolved = await Promise.resolve(params)
  const locale = resolved.locale
  if (!isLocale(locale)) {
    notFound()
  }
  setRequestLocale(locale)
  const messages = await getMessages()
  const dir = localeDirection(locale as Locale)

  return (
    <html lang={locale} dir={dir} suppressHydrationWarning>
      <head>
        <link rel="preload" href="/favicon.ico" as="image" fetchPriority="high" />
      </head>
      <body className="min-h-svh antialiased">
        <ThemeProvider
          attribute="class"
          defaultTheme="system"
          enableSystem
          storageKey="svp-dashboard-theme"
          disableTransitionOnChange
        >
          <NextIntlClientProvider messages={messages}>
            <TooltipProvider>{children}</TooltipProvider>
          </NextIntlClientProvider>
        </ThemeProvider>
      </body>
    </html>
  )
}
