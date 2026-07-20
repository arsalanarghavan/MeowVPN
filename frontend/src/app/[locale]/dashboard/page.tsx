import { getTranslations, setRequestLocale } from "next-intl/server"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"

export default async function DashboardHomePage({
  params,
}: {
  params: Promise<{ locale: string }> | { locale: string }
}) {
  const { locale } = await Promise.resolve(params)
  setRequestLocale(locale)
  const t = await getTranslations()

  const cards = [
    { key: "users", title: t("sidebar.items.users"), desc: t("sidebar.sections.users") },
    { key: "payments", title: t("sidebar.items.payments"), desc: t("sidebar.sections.finance") },
    { key: "monitoring", title: t("sidebar.items.monitoring"), desc: t("sidebar.sections.overview") },
  ]

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t("overview.title")}</h1>
        <p className="text-muted-foreground">{t("overview.subtitle")}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        {cards.map((card) => (
          <Card key={card.key}>
            <CardHeader>
              <CardTitle>{card.title}</CardTitle>
              <CardDescription>{card.desc}</CardDescription>
            </CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground">{card.desc}</p>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
