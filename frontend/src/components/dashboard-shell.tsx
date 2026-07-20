"use client"

import { useEffect, type ReactNode } from "react"
import { useLocale } from "next-intl"
import { useRouter } from "next/navigation"
import { AppSidebar } from "@/components/app-sidebar"
import { DashboardShellProvider, useDashboardShell } from "@/components/dashboard-shell-provider"
import { DashboardToolbar } from "@/components/dashboard-toolbar"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import { SidebarInset, SidebarProvider, SidebarTrigger } from "@/components/ui/sidebar"

export function DashboardShellInner({ children }: { children: ReactNode }) {
  const locale = useLocale()
  const router = useRouter()
  const { me, features, isReseller, allowedResellerTabs, impersonating, loading } = useDashboardShell()

  useEffect(() => {
    if (loading || !me) return
    if (me.activePersona === "user" && !impersonating) {
      router.replace(`/${locale}/portal`)
    }
  }, [loading, me, impersonating, locale, router])

  const displayUser = me?.user
    ? {
        name: String(me.user.display_name || me.user.name || me.user.username || "User"),
        email: String(me.user.email || ""),
        avatar: "",
      }
    : undefined

  if (!loading && me?.activePersona === "user" && !impersonating) {
    return <p className="p-4 text-sm text-muted-foreground">Redirecting…</p>
  }

  return (
    <SidebarProvider>
      <AppSidebar
        user={displayUser}
        features={features}
        isReseller={isReseller}
        allowedResellerTabs={allowedResellerTabs}
        personaSwitchBlocked={impersonating}
        activePersona={me?.activePersona}
        availablePersonas={
          Array.isArray(me?.availablePersonas) ? (me.availablePersonas as string[]) : undefined
        }
      />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
          <div className="flex w-full items-center gap-2 px-4">
            <SidebarTrigger className="-ms-1" />
            <Separator orientation="vertical" className="me-2 data-[orientation=vertical]:h-4" />
            <Breadcrumb>
              <BreadcrumbList>
                <BreadcrumbItem className="hidden md:block">
                  <BreadcrumbLink href={`/${locale}/dashboard`}>MeowVPN</BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator className="hidden md:block" />
                <BreadcrumbItem>
                  <BreadcrumbPage>Dashboard</BreadcrumbPage>
                </BreadcrumbItem>
              </BreadcrumbList>
            </Breadcrumb>
            <DashboardToolbar />
          </div>
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4 pt-0">{children}</div>
      </SidebarInset>
    </SidebarProvider>
  )
}

export function DashboardShell({ children }: { children: ReactNode }) {
  return (
    <DashboardShellProvider>
      <DashboardShellInner>{children}</DashboardShellInner>
    </DashboardShellProvider>
  )
}
