/**
 * MAIN LAYOUT COMPONENT - Global application layout wrapper
 *
 * PURPOSE:
 * - Provides consistent navigation structure across all authenticated pages
 * - Manages responsive sidebar and mobile bottom navigation
 * - Displays global elements (workspace switcher, engine status, credits)
 * - Handles workspace-scoped navigation and engine-specific features
 *
 * INERTIA.JS DATA REQUIREMENTS:
 * - currentWorkspace: Workspace - Active workspace data
 * - user: User - Current user info and permissions
 * - credits: Object - User's credit balance and usage
 * - engineStatus: Object - Real-time engine connection status
 * - navigation: Array<NavItem> - User-specific navigation items
 *
 * PROPS INTEGRATION:
 * - Should receive workspace and engine data from Inertia page props
 * - Navigation items filtered based on user permissions and engine type
 * - Real-time status updates via WebSocket or polling
 *
 * RESPONSIVE DESIGN:
 * - Desktop: Fixed sidebar with full navigation
 * - Mobile: Collapsible sidebar + bottom tab navigation
 * - Tablet: Hybrid approach with collapsible sidebar
 */

"use client"

import type React from "react"
import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import {
  MessageSquare,
  FolderOpen,
  BookTemplate as Template,
  Eye,
  Upload,
  Users,
  History,
  CreditCard,
  Settings,
  Gamepad2,
  Code,
  Globe,
  CheckCircle,
  AlertCircle,
  Loader2,
  Zap,
  Menu,
  X,
} from "lucide-react"
import Link from "next/link"
import { usePathname } from "next/navigation"
import { WorkspaceSwitcher } from "@/components/chat/workspace-switcher"
import { UserMenu } from "@/components/layout/user-menu"
import { cn } from "@/lib/utils"

interface MainLayoutProps {
  children: React.ReactNode
  currentWorkspace?: string
  currentEngine?: "unreal" | "playcanvas"
  connectionStatus?: "connected" | "connecting" | "disconnected" | "error"
  // Additional props for full Inertia.js integration:
  // user?: User
  // credits?: { current: number; total: number; usage: CreditUsage[] }
  // permissions?: UserPermissions
  // notifications?: Notification[]
}

// Navigation configuration - In real app, this could come from backend based on user permissions
const navigationItems = [
  { name: "Chat", href: "/chat", icon: MessageSquare, mobileOrder: 1 },
  { name: "Workspaces", href: "/workspaces", icon: FolderOpen, mobileOrder: 2 },
  { name: "Templates", href: "/templates", icon: Template, mobileOrder: 3, playCanvasOnly: true },
  { name: "Preview", href: "/preview", icon: Eye, mobileOrder: 4 },
  { name: "Publish", href: "/publish", icon: Upload, mobileOrder: 5 },
  { name: "Multiplayer", href: "/multiplayer", icon: Users, mobileOrder: 0 }, // 0 = desktop only
  { name: "History", href: "/history", icon: History, mobileOrder: 0 },
  { name: "Credits & Billing", href: "/company/billing", icon: CreditCard, mobileOrder: 0 },
  { name: "Settings", href: "/settings", icon: Settings, mobileOrder: 0 },
  // Additional navigation items for full implementation:
  // { name: "Team", href: "/team", icon: Users2, adminOnly: true },
  // { name: "Analytics", href: "/analytics", icon: BarChart, proOnly: true },
  // { name: "Integrations", href: "/integrations", icon: Plug },
]

export function MainLayout({
  children,
  currentWorkspace = "Web Racing Game",
  currentEngine = "playcanvas",
  connectionStatus = "connected",
}: MainLayoutProps) {
  const pathname = usePathname()
  const [showMobileSidebar, setShowMobileSidebar] = useState(false)

  // Credits state - In real app, this comes from Inertia props
  const [credits, setCredits] = useState({ current: 1247, total: 2000 })

  // Filter navigation based on engine and user permissions
  const filteredNavItems = navigationItems.filter((item) => {
    // Hide PlayCanvas-only items for Unreal workspaces
    if (item.playCanvasOnly && currentEngine !== "playcanvas") return false

    // Additional filtering for real implementation:
    // if (item.adminOnly && !user?.isAdmin) return false
    // if (item.proOnly && !user?.isPro) return false

    return true
  })

  // Dynamic status badge based on engine and connection state
  const getStatusBadge = () => {
    if (currentEngine === "playcanvas") {
      return (
        <Badge variant="outline" className="text-xs">
          <CheckCircle className="w-3 h-3 mr-1 text-green-500" />
          Ready
        </Badge>
      )
    }

    // Unreal Engine connection status
    switch (connectionStatus) {
      case "connected":
        return (
          <Badge variant="outline" className="text-xs">
            <CheckCircle className="w-3 h-3 mr-1 text-green-500" />
            Connected
          </Badge>
        )
      case "connecting":
        return (
          <Badge variant="outline" className="text-xs">
            <Loader2 className="w-3 h-3 mr-1 animate-spin" />
            Connecting...
          </Badge>
        )
      case "error":
        return (
          <Badge variant="destructive" className="text-xs">
            <AlertCircle className="w-3 h-3 mr-1" />
            Error
          </Badge>
        )
      default:
        return (
          <Badge variant="secondary" className="text-xs">
            <AlertCircle className="w-3 h-3 mr-1" />
            Disconnected
          </Badge>
        )
    }
  }

  // Engine identification badge
  const getEngineBadge = () => (
    <Badge variant="secondary" className="text-xs">
      {currentEngine === "unreal" ? (
        <>
          <Code className="w-3 h-3 mr-1" />
          Unreal
        </>
      ) : (
        <>
          <Globe className="w-3 h-3 mr-1" />
          PlayCanvas
        </>
      )}
    </Badge>
  )

  return (
    <div className="min-h-screen bg-background">
      {/* Desktop Sidebar - Fixed navigation for large screens */}
      <div className="hidden lg:block fixed inset-y-0 left-0 w-64 bg-sidebar border-r border-sidebar-border">
        <div className="flex flex-col h-full">
          {/* Logo/Brand */}
          <div className="p-4 border-b border-sidebar-border">
            <div className="flex items-center space-x-2">
              <div className="w-8 h-8 bg-sidebar-primary rounded-lg flex items-center justify-center">
                <Gamepad2 className="w-5 h-5 text-sidebar-primary-foreground" />
              </div>
              <span className="font-serif font-black text-sidebar-foreground">SurrealPilot</span>
            </div>
          </div>

          {/* Primary Navigation */}
          <nav className="flex-1 p-4">
            <div className="space-y-1">
              {filteredNavItems.map((item) => {
                const isActive = pathname.startsWith(item.href)
                return (
                  <Link
                    key={item.name}
                    href={item.href}
                    className={cn(
                      "flex items-center space-x-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors",
                      isActive
                        ? "bg-sidebar-accent text-sidebar-accent-foreground"
                        : "text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                    )}
                  >
                    <item.icon className="w-4 h-4" />
                    <span>{item.name}</span>
                  </Link>
                )
              })}
            </div>
          </nav>

          {/* Credits Display - Bottom of sidebar */}
          <div className="p-4 border-t border-sidebar-border">
            <div className="flex items-center justify-between text-sm">
              <span className="text-muted-foreground">Credits</span>
              <Badge variant="outline" className="text-xs">
                {credits.current.toLocaleString()} / {credits.total.toLocaleString()}
              </Badge>
            </div>
          </div>
        </div>
      </div>

      {/* Mobile Sidebar Overlay - Slide-out navigation for mobile */}
      {showMobileSidebar && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div className="absolute inset-0 bg-black/50" onClick={() => setShowMobileSidebar(false)} />
          <div className="absolute left-0 top-0 h-full w-64 bg-sidebar border-r border-sidebar-border">
            <div className="flex flex-col h-full">
              {/* Logo with close button */}
              <div className="p-4 border-b border-sidebar-border">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <div className="w-8 h-8 bg-sidebar-primary rounded-lg flex items-center justify-center">
                      <Gamepad2 className="w-5 h-5 text-sidebar-primary-foreground" />
                    </div>
                    <span className="font-serif font-black text-sidebar-foreground">SurrealPilot</span>
                  </div>
                  <Button variant="ghost" size="sm" onClick={() => setShowMobileSidebar(false)}>
                    <X className="w-4 h-4" />
                  </Button>
                </div>
              </div>

              {/* Mobile Navigation */}
              <nav className="flex-1 p-4">
                <div className="space-y-1">
                  {filteredNavItems.map((item) => {
                    const isActive = pathname.startsWith(item.href)
                    return (
                      <Link
                        key={item.name}
                        href={item.href}
                        onClick={() => setShowMobileSidebar(false)} // Close sidebar on navigation
                        className={cn(
                          "flex items-center space-x-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors",
                          isActive
                            ? "bg-sidebar-accent text-sidebar-accent-foreground"
                            : "text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                        )}
                      >
                        <item.icon className="w-4 h-4" />
                        <span>{item.name}</span>
                      </Link>
                    )
                  })}
                </div>
              </nav>
            </div>
          </div>
        </div>
      )}

      {/* Main Content Area */}
      <div className="lg:pl-64">
        {/* Global Header - Persistent across all pages */}
        <header className="sticky top-0 z-40 border-b border-border bg-card/50 backdrop-blur-sm">
          <div className="flex items-center justify-between p-4">
            <div className="flex items-center space-x-4">
              {/* Mobile menu trigger */}
              <Button variant="ghost" size="sm" className="lg:hidden" onClick={() => setShowMobileSidebar(true)}>
                <Menu className="w-4 h-4" />
              </Button>

              {/* Workspace Switcher - Core navigation element */}
              <WorkspaceSwitcher currentWorkspace={currentWorkspace} currentEngine={currentEngine} />
            </div>

            {/* Global Status Elements */}
            <div className="flex items-center space-x-2">
              {getEngineBadge()}
              {getStatusBadge()}
              <Badge variant="outline" className="text-xs hidden sm:flex">
                <Zap className="w-3 h-3 mr-1" />
                GPT-4
              </Badge>
              <Badge variant="outline" className="text-xs hidden md:flex">
                {credits.current.toLocaleString()} credits
              </Badge>
              {/* User menu with profile, settings, logout */}
              <UserMenu />
            </div>
          </div>
        </header>

        {/* Page Content - Where individual pages render */}
        <main className="min-h-[calc(100vh-73px)]">{children}</main>
      </div>

      {/* Mobile Bottom Navigation - Tab bar for mobile devices */}
      <div className="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-card border-t border-border">
        <div className="flex items-center justify-around py-2">
          {filteredNavItems
            .filter((item) => item.mobileOrder > 0) // Only show items with mobile priority
            .sort((a, b) => a.mobileOrder - b.mobileOrder) // Sort by mobile priority
            .slice(0, 5) // Limit to 5 tabs for mobile
            .map((item) => {
              const isActive = pathname.startsWith(item.href)
              return (
                <Link
                  key={item.name}
                  href={item.href}
                  className={cn(
                    "flex flex-col items-center space-y-1 px-3 py-2 rounded-lg transition-colors min-w-0",
                    isActive ? "text-primary" : "text-muted-foreground hover:text-foreground",
                  )}
                >
                  <item.icon className="w-5 h-5" />
                  <span className="text-xs truncate">{item.name}</span>
                </Link>
              )
            })}
        </div>
      </div>

      {/* Mobile bottom padding to prevent content overlap */}
      <div className="h-20 lg:hidden" />
    </div>
  )
}
