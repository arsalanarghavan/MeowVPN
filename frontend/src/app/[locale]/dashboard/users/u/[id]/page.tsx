"use client"

import { UserDetailAdmin } from "@/components/admin/users/user-detail-admin"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
import { useLocale, useTranslations } from "next-intl"
import { useRouter } from "next/navigation"
import { use } from "react"

type DashRecord = Record<string, unknown>

function rows(v: unknown): DashRecord[] {
  return Array.isArray(v) ? (v.filter((x) => x && typeof x === "object") as DashRecord[]) : []
}

export default function UserDetailPage({
  params,
}: {
  params: Promise<{ locale: string; id: string }> | { locale: string; id: string }
}) {
  const { id } = use(Promise.resolve(params))
  const userId = Number(id)
  const t = useTranslations("usersAdmin")
  const locale = useLocale()
  const router = useRouter()
  const { data, loading, error, reload, isReseller, actorPermissions, enabledPlatforms } = useAdminTabState(
    "users",
    { users_detail_id: String(userId) }
  )

  if (!Number.isFinite(userId) || userId < 1) {
    return <p className="text-sm text-destructive">{t("loadError")}</p>
  }
  if (loading && !data.userDetail) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error) {
    return <p className="text-sm text-destructive">{t("loadError")}</p>
  }

  return (
    <UserDetailAdmin
      userId={userId}
      plans={rows(data.plans)}
      planCategories={rows(data.planCategories ?? data.plan_categories)}
      settings={(data.settings as Record<string, unknown>) ?? {}}
      isReseller={isReseller}
      actorPermissions={actorPermissions}
      canReviewReceipts={!isReseller || actorPermissions?.["receipts.review"] === true}
      onBack={() => router.push(`/${locale}/dashboard/users`)}
      onMutateSuccess={reload}
      onOpenUserDetail={(nextId) => router.push(`/${locale}/dashboard/users/u/${nextId}`)}
      enabledPlatforms={enabledPlatforms as import("@/config/bot-platforms").BotPlatformId[]}
    />
  )
}
