/**
 * WORKSPACES PAGE - Main workspace selection and management interface
 *
 * PURPOSE:
 * - Display all user's workspaces (game projects) in a grid layout
 * - Allow workspace creation, selection, and basic management
 * - Show workspace metadata (engine type, conversation count, last activity)
 * - Provide entry point to chat interface for each workspace
 *
 * INERTIA.JS DATA REQUIREMENTS:
 * - workspaces: Array<Workspace> - User's workspaces with metadata
 * - user: User - Current authenticated user info
 * - permissions: Object - User permissions for workspace actions
 *
 * API CALLS NEEDED:
 * - GET /api/workspaces - Fetch user's workspaces
 * - DELETE /api/workspaces/{id} - Delete workspace
 * - PUT /api/workspaces/{id} - Update workspace settings
 *
 * NAVIGATION FLOW:
 * - From: Landing page, login redirect, main navigation
 * - To: /workspaces/new (create), /chat?workspace={id} (open workspace)
 */

"use client"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Plus, Gamepad2, Globe, Zap, Calendar, MessageSquare, Settings, Trash2 } from "lucide-react"
import Link from "next/link"

interface Workspace {
  id: string
  name: string
  description: string
  engine: "playcanvas" | "unreal" // Engine type determines available features
  lastActive: Date // For sorting and displaying activity
  conversationCount: number // Number of chat threads in this workspace
  createdAt: Date
  // Additional fields that should come from backend:
  // status: "active" | "archived" | "error" // Workspace status
  // owner: User // Workspace owner info
  // members: User[] // Team members with access
  // settings: WorkspaceSettings // Engine-specific settings
}

const workspaces: Workspace[] = [
  {
    id: "1",
    name: "Space Explorer VR",
    description: "A virtual reality space exploration game with realistic physics",
    engine: "unreal",
    lastActive: new Date(Date.now() - 300000),
    conversationCount: 12,
    createdAt: new Date(Date.now() - 86400000 * 7),
  },
  {
    id: "2",
    name: "Web Racing Game",
    description: "Fast-paced browser racing game with multiplayer support",
    engine: "playcanvas",
    lastActive: new Date(Date.now() - 3600000),
    conversationCount: 8,
    createdAt: new Date(Date.now() - 86400000 * 3),
  },
  {
    id: "3",
    name: "Mobile Puzzle Adventure",
    description: "Cross-platform puzzle game for mobile and web",
    engine: "playcanvas",
    lastActive: new Date(Date.now() - 86400000),
    conversationCount: 15,
    createdAt: new Date(Date.now() - 86400000 * 14),
  },
]

export default function WorkspacesPage() {
  const getEngineIcon = (engine: string) => {
    return engine === "playcanvas" ? Globe : Zap
  }

  const getEngineColor = (engine: string) => {
    return engine === "playcanvas" ? "bg-blue-500" : "bg-purple-500"
  }

  const handleDeleteWorkspace = (workspaceId: string) => {
    // Inertia.delete(`/workspaces/${workspaceId}`, {
    //   onSuccess: () => toast.success('Workspace deleted'),
    //   onError: () => toast.error('Failed to delete workspace')
    // })
  }

  const handleWorkspaceSettings = (workspaceId: string) => {
    // Navigate to workspace settings page
    // router.visit(`/workspaces/${workspaceId}/settings`)
  }

  return (
    <div className="min-h-screen bg-background">
      <div className="container mx-auto px-4 py-8">
        {/* Header Section */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-serif font-black text-foreground mb-2">Your Workspaces</h1>
            <p className="text-muted-foreground">Manage your game development projects</p>
          </div>
          {/* Primary CTA - Create new workspace */}
          <Link href="/workspaces/new">
            <Button className="bg-accent hover:bg-accent/90 text-accent-foreground">
              <Plus className="w-4 h-4 mr-2" />
              New Workspace
            </Button>
          </Link>
        </div>

        {/* Workspaces Grid - Main content area */}
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {workspaces.map((workspace) => {
            const EngineIcon = getEngineIcon(workspace.engine)
            return (
              <Card key={workspace.id} className="hover:shadow-lg transition-shadow cursor-pointer group">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between">
                    {/* Workspace Identity */}
                    <div className="flex items-center space-x-3">
                      <div
                        className={`w-10 h-10 ${getEngineColor(workspace.engine)} rounded-lg flex items-center justify-center`}
                      >
                        <EngineIcon className="w-5 h-5 text-white" />
                      </div>
                      <div>
                        <CardTitle className="text-lg">{workspace.name}</CardTitle>
                        <Badge variant="secondary" className="text-xs mt-1">
                          {workspace.engine === "playcanvas" ? "PlayCanvas" : "Unreal Engine"}
                        </Badge>
                      </div>
                    </div>
                    {/* Workspace Actions - Show on hover */}
                    <div className="opacity-0 group-hover:opacity-100 transition-opacity flex space-x-1">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0"
                        onClick={() => handleWorkspaceSettings(workspace.id)}
                      >
                        <Settings className="w-4 h-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                        onClick={() => handleDeleteWorkspace(workspace.id)}
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                  </div>
                </CardHeader>
                <CardContent>
                  <CardDescription className="mb-4 line-clamp-2">{workspace.description}</CardDescription>

                  {/* Workspace Metadata */}
                  <div className="space-y-2 text-sm text-muted-foreground">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-1">
                        <MessageSquare className="w-4 h-4" />
                        <span>{workspace.conversationCount} conversations</span>
                      </div>
                      <div className="flex items-center space-x-1">
                        <Calendar className="w-4 h-4" />
                        <span>{workspace.lastActive.toLocaleDateString()}</span>
                      </div>
                    </div>
                  </div>

                  {/* Primary Action - Enter workspace */}
                  <div className="mt-4 pt-4 border-t border-border">
                    <Link href={`/chat?workspace=${workspace.id}&engine=${workspace.engine}`}>
                      <Button className="w-full bg-accent hover:bg-accent/90 text-accent-foreground">
                        <Gamepad2 className="w-4 h-4 mr-2" />
                        Open Workspace
                      </Button>
                    </Link>
                  </div>
                </CardContent>
              </Card>
            )
          })}
        </div>

        {/* Empty State - Show when no workspaces exist */}
        {workspaces.length === 0 && (
          <div className="text-center py-12">
            <Gamepad2 className="w-16 h-16 text-muted-foreground mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-foreground mb-2">No workspaces yet</h3>
            <p className="text-muted-foreground mb-6">
              Create your first workspace to start building games with AI assistance
            </p>
            <Link href="/workspaces/new">
              <Button className="bg-accent hover:bg-accent/90 text-accent-foreground">
                <Plus className="w-4 h-4 mr-2" />
                Create Your First Workspace
              </Button>
            </Link>
          </div>
        )}
      </div>
    </div>
  )
}
