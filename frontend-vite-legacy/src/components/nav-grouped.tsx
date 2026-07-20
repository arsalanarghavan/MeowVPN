"use client"

import { useEffect, useState } from "react"
import { ChevronRight } from "lucide-react"
import { useTranslation } from "react-i18next"

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from "@/components/ui/sidebar"
import type { AdminNavSection } from "@/config/admin-nav"
import { ADMIN_NAV_SECTIONS } from "@/config/admin-nav"
import {
  menuBtnCollapsedIcon,
  menuChevronCollapsedHidden,
} from "@/lib/sidebar-menu-classes"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

function findOpenMenuId(sections: AdminNavSection[], activeTabKey: string): string | null {
  for (const section of sections) {
    for (const entry of section.entries) {
      if (entry.kind === "collapsible" && entry.children.some((c) => c.tabKey === activeTabKey)) {
        return entry.id
      }
    }
  }
  return null
}

export function NavGrouped({
  activeTabKey,
  onSelectTab,
  subItemUrl,
  sections = ADMIN_NAV_SECTIONS,
}: {
  activeTabKey: string
  onSelectTab: (tabKey: string) => void
  subItemUrl: (tabKey: string) => string
  sections?: AdminNavSection[]
}) {
  const { isFa, dir } = useDashLocale()
  const { t } = useTranslation()
  const [openMenuId, setOpenMenuId] = useState<string | null>(null)

  useEffect(() => {
    setOpenMenuId(findOpenMenuId(sections, activeTabKey))
  }, [activeTabKey, sections])

  const itemLabel = (tabKey: string) =>
    t(`sidebar.items.${tabKey}`, { defaultValue: tabKey })

  return (
    <>
      {sections.map((section) => (
        <SidebarGroup
          key={section.id}
          className="text-start"
          dir={dir}
        >
          <SidebarGroupLabel>{t(section.hintKey)}</SidebarGroupLabel>
          <SidebarMenu>
            {section.entries.map((entry) => {
              if (entry.kind === "leaf") {
                const href = subItemUrl(entry.tabKey)
                const isActive = activeTabKey === entry.tabKey
                const Icon = entry.icon
                return (
                  <SidebarMenuItem key={entry.tabKey}>
                    <SidebarMenuButton
                      asChild
                      isActive={isActive}
                      tooltip={itemLabel(entry.tabKey)}
                      className={menuBtnCollapsedIcon}
                    >
                      <a
                        href={href}
                        onClick={(e) => {
                          e.preventDefault()
                          onSelectTab(entry.tabKey)
                        }}
                      >
                        <Icon />
                        <span>{itemLabel(entry.tabKey)}</span>
                      </a>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )
              }

              const ParentIcon = entry.icon
              return (
                <Collapsible
                  key={entry.id}
                  asChild
                  open={openMenuId === entry.id}
                  onOpenChange={(open) => setOpenMenuId(open ? entry.id : null)}
                  className="group/collapsible"
                >
                  <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                      <SidebarMenuButton
                        tooltip={t(entry.labelKey)}
                        className={menuBtnCollapsedIcon}
                      >
                        <ParentIcon />
                        <span>{t(entry.labelKey)}</span>
                        <ChevronRight
                          className={cn(
                            "ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90",
                            menuChevronCollapsedHidden,
                            isFa && "-scale-x-100"
                          )}
                        />
                      </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <SidebarMenuSub
                        className={cn(
                          isFa &&
                            "me-3.5 ms-0 translate-x-0 border-r border-l-0 pe-2.5 ps-0"
                        )}
                      >
                        {entry.children.map((sub) => {
                          const href = subItemUrl(sub.tabKey)
                          const isActive = activeTabKey === sub.tabKey
                          const SubIcon = sub.icon
                          return (
                            <SidebarMenuSubItem key={sub.tabKey}>
                              <SidebarMenuSubButton asChild isActive={isActive}>
                                <a
                                  href={href}
                                  className={cn(SubIcon && "gap-2")}
                                  onClick={(e) => {
                                    e.preventDefault()
                                    onSelectTab(sub.tabKey)
                                  }}
                                >
                                  {SubIcon ? <SubIcon className="size-4 shrink-0" /> : null}
                                  <span>{itemLabel(sub.tabKey)}</span>
                                </a>
                              </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                          )
                        })}
                      </SidebarMenuSub>
                    </CollapsibleContent>
                  </SidebarMenuItem>
                </Collapsible>
              )
            })}
          </SidebarMenu>
        </SidebarGroup>
      ))}
    </>
  )
}
