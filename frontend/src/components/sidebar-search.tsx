"use client"

import { useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import { Search, UserRound } from "lucide-react"
import { useCommandState } from "cmdk"

import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import { apiBase, apiHeaders, normalizeAdminApiPath } from "@/lib/api"
import { Button } from "@/components/ui/button"
import { ADMIN_NAV_SECTIONS, flattenNavForSearch, type AdminNavSection } from "@/config/admin-nav"
import { formatPlainLatinInt } from "@/lib/format-locale"
import { useDashLocale } from "@/lib/dash-locale-context"
import { menuBtnCollapsedIcon } from "@/lib/sidebar-menu-classes"
import { cn } from "@/lib/utils"

function CommandSearchBridge({ onSearch }: { onSearch: (q: string) => void }) {
  const q = useCommandState((state) => state.search)
  useEffect(() => {
    onSearch(q)
  }, [q, onSearch])
  return null
}

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function userDisplayName(u: DashRecord): string {
  const fn = String(u.first_name ?? "").trim()
  const ln = String(u.last_name ?? "").trim()
  const combined = `${fn} ${ln}`.trim()
  if (combined) return combined
  return String(u.username ?? "").trim() || "—"
}

export type DashboardSearchProps = {
  placement?: "sidebar" | "header"
  className?: string
  onSelectTab: (tabKey: string) => void
  onOpenUserDetail?: (id: number) => void
  restUrl?: string
  resellerContextId?: number
  sections?: AdminNavSection[]
}

export function DashboardSearch({
  placement = "sidebar",
  className,
  onSelectTab,
  onOpenUserDetail,
  restUrl,
  resellerContextId = 0,
  sections = ADMIN_NAV_SECTIONS,
}: DashboardSearchProps) {
  const { isFa, dir } = useDashLocale()
  const [open, setOpen] = useState(false)
  const [paletteQuery, setPaletteQuery] = useState("")
  const [userHits, setUserHits] = useState<DashRecord[]>([])
  const [userLoading, setUserLoading] = useState(false)
  const t = useTranslations()
  const rows = useMemo(() => flattenNavForSearch(sections), [sections])

  const bySection = useMemo(() => {
    const m = new Map<string, typeof rows>()
    for (const sec of sections) {
      m.set(sec.hintKey, rows.filter((r) => r.sectionHintKey === sec.hintKey))
    }
    return m
  }, [rows, sections])

  const itemLabel = (tabKey: string) => {
    try {
      return t(`sidebar.items.${tabKey}`)
    } catch {
      return tabKey
    }
  }

  const canUserSearch = Boolean(onOpenUserDetail && (restUrl || apiBase()))

  useEffect(() => {
    if (!open) {
      setPaletteQuery("")
      setUserHits([])
      setUserLoading(false)
      return
    }
  }, [open])

  useEffect(() => {
    if (!canUserSearch || !open) return
    const q = paletteQuery.trim()
    if (q.length < 1) {
      setUserHits([])
      setUserLoading(false)
      return
    }
    const base = restUrl?.replace(/\/$/, "") || apiBase()
    const ctrl = new AbortController()
    setUserLoading(true)
    const timer = window.setTimeout(() => {
      const sp = new URLSearchParams()
      sp.set("q", q)
      if (resellerContextId > 0) {
        sp.set("resellerContextId", String(resellerContextId))
      }
      void fetch(`${base}${normalizeAdminApiPath("/dashboard/admin/user-search")}?${sp.toString()}`, {
        credentials: "include",
        headers: apiHeaders(),
        signal: ctrl.signal,
      })
        .then((r) => r.json() as Promise<{ users?: DashRecord[] }>)
        .then((json) => {
          setUserHits(Array.isArray(json.users) ? json.users : [])
        })
        .catch(() => {
          if (!ctrl.signal.aborted) setUserHits([])
        })
        .finally(() => {
          if (!ctrl.signal.aborted) setUserLoading(false)
        })
    }, 280)
    return () => {
      window.clearTimeout(timer)
      ctrl.abort()
    }
  }, [canUserSearch, open, paletteQuery, restUrl, resellerContextId])

  const triggerLabel = t("sidebar.search.trigger")
  const isHeader = placement === "header"

  return (
    <>
      {isHeader ? (
        <Button
          type="button"
          variant="outline"
          className={cn(
            "flex h-9 w-full max-w-none items-center justify-start gap-2 text-start text-muted-foreground md:max-w-md",
            className
          )}
          dir={dir}
          onClick={() => setOpen(true)}
        >
          <Search className="size-4 shrink-0 opacity-70" />
          <span className="min-w-0 flex-1 truncate">{triggerLabel}</span>
        </Button>
      ) : (
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              type="button"
              variant="outline"
              tooltip={triggerLabel}
              className={cn(
                menuBtnCollapsedIcon,
                "h-9 text-muted-foreground hover:text-sidebar-accent-foreground"
              )}
              dir={dir}
              onClick={() => setOpen(true)}
            >
              <Search className="opacity-70" />
              <span className="min-w-0 flex-1 truncate text-start">
                {triggerLabel}
              </span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      )}
      <CommandDialog
        open={open}
        onOpenChange={setOpen}
        title={t("sidebar.search.title")}
        description={t("sidebar.search.placeholder")}
        rtl={isFa}
      >
        <CommandInput
          rtl={isFa}
          placeholder={t("sidebar.search.placeholder")}
        />
        <CommandSearchBridge onSearch={setPaletteQuery} />
        <CommandList>
          <CommandEmpty className="w-full text-start">
            {t("sidebar.search.empty")}
          </CommandEmpty>
          {canUserSearch && paletteQuery.trim().length >= 1 ? (
            <CommandGroup heading={t("sidebar.search.usersHeading")}>
              {userLoading ? (
                <div
                  className={cn(
                    "w-full px-3 py-2 text-xs text-muted-foreground"
                  )}
                >
                  {t("sidebar.search.usersLoading")}
                </div>
              ) : userHits.length === 0 ? (
                <div
                  className={cn(
                    "w-full px-3 py-2 text-xs text-muted-foreground"
                  )}
                >
                  {t("sidebar.search.usersEmpty")}
                </div>
              ) : (
                userHits.map((u) => {
                  const id = num(u.id)
                  const tg = num(u.tg_user_id)
                  const bl = num(u.bale_user_id)
                  const un = String(u.username ?? "").trim()
                  const name = userDisplayName(u)
                  const filterValue = `${name} ${un} ${id} ${tg} ${bl} @${un}`
                  return (
                    <CommandItem
                      key={id}
                      value={filterValue}
                      className={cn()}
                      onSelect={() => {
                        if (id > 0) onOpenUserDetail?.(id)
                        setOpen(false)
                      }}
                    >
                      <UserRound className="size-4 opacity-70" />
                      <span className={cn("min-w-0 flex-1 truncate")}>
                        {name}
                      </span>
                      <span
                        dir="ltr"
                        className="shrink-0 font-mono text-xs text-muted-foreground"
                      >
                        #{formatPlainLatinInt(id)}
                      </span>
                    </CommandItem>
                  )
                })
              )}
            </CommandGroup>
          ) : null}
          {sections.map((sec) => {
            const secRows = bySection.get(sec.hintKey) ?? []
            if (!secRows.length) return null
            return (
              <CommandGroup key={sec.id} heading={t(sec.hintKey)}>
                {secRows.map((r, idx) => (
                  <CommandItem
                    key={`${sec.id}-${r.tabKey}-${r.parentLabelKey ?? "x"}-${idx}`}
                    value={`${itemLabel(r.tabKey)} ${r.parentLabelKey ? t(r.parentLabelKey) : ""} ${t(sec.hintKey)}`}
                    className={cn(
                      "w-full",
                      r.parentLabelKey && "flex-col items-start gap-0.5"
                    )}
                    onSelect={() => {
                      onSelectTab(r.tabKey)
                      setOpen(false)
                    }}
                  >
                    <span className="w-full truncate text-start">
                      {itemLabel(r.tabKey)}
                    </span>
                    {r.parentLabelKey ? (
                      <span
                        className="w-full truncate text-xs text-muted-foreground text-start"
                      >
                        {t(r.parentLabelKey)}
                      </span>
                    ) : null}
                  </CommandItem>
                ))}
              </CommandGroup>
            )
          })}
        </CommandList>
      </CommandDialog>
    </>
  )
}

/** @deprecated Use DashboardSearch */
export const SidebarSearch = DashboardSearch
