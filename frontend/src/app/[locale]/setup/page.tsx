import { setRequestLocale } from "next-intl/server"
import { InstallWizard } from "@/components/install-wizard"

export default async function LocaleSetupPage({
  params,
}: {
  params: Promise<{ locale: string }> | { locale: string }
}) {
  const { locale } = await Promise.resolve(params)
  setRequestLocale(locale)
  return <InstallWizard />
}
