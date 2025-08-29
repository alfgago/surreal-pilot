/**
 * NEW WORKSPACE PAGE - Workspace creation interface
 *
 * PURPOSE:
 * - Allow users to create new game development workspaces
 * - Engine selection (Unreal Engine vs PlayCanvas)
 * - Workspace configuration and metadata setup
 * - Integration with workspace-first architecture
 *
 * INERTIA.JS DATA REQUIREMENTS:
 * - user: User - Current user info for workspace ownership
 * - availableEngines: Array<Engine> - Supported engines with features
 * - templates: Array<Template> - Available project templates (PlayCanvas)
 * - limits: Object - User's workspace creation limits
 *
 * API CALLS NEEDED:
 * - POST /api/workspaces - Create new workspace
 * - GET /api/engines - Fetch available engines and features
 * - GET /api/templates - Fetch PlayCanvas templates (if engine selected)
 * - GET /api/user/limits - Check workspace creation limits
 *
 * NAVIGATION FLOW:
 * - From: /workspaces (main workspace list)
 * - To: /chat?workspace={id}&engine={type} (new workspace chat)
 * - Alternative: /templates (PlayCanvas template selection)
 */

"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { ArrowLeft, Gamepad2, Globe, Zap } from "lucide-react"
import Link from "next/link"
import { useRouter } from "next/navigation"

// Engine configuration - In real app, this comes from backend
const engines = [
  {
    id: "playcanvas",
    name: "PlayCanvas",
    description: "Web-based 3D game engine for browser and mobile games",
    icon: Globe,
    features: ["WebGL Rendering", "Cross-platform", "Real-time Collaboration", "Visual Editor"],
    color: "bg-blue-500",
    // Additional fields for backend integration:
    // templates: Array<Template> - Available starter templates
    // pricing: Object - Engine-specific pricing info
    // requirements: Object - System requirements
  },
  {
    id: "unreal",
    name: "Unreal Engine",
    description: "Industry-leading game engine for AAA games and experiences",
    icon: Zap,
    features: ["Blueprint Visual Scripting", "C++ Support", "Advanced Graphics", "VR/AR Ready"],
    color: "bg-purple-500",
    // Additional fields for backend integration:
    // pluginUrl: string - Download URL for SurrealPilot plugin
    // documentation: string - Setup documentation link
    // supportedVersions: Array<string> - Compatible UE versions
  },
]

export default function NewWorkspacePage() {
  // Form state
  const [selectedEngine, setSelectedEngine] = useState<string>("")
  const [workspaceName, setWorkspaceName] = useState("")
  const [description, setDescription] = useState("")
  const [isCreating, setIsCreating] = useState(false)

  // Additional state for real implementation:
  // const [selectedTemplate, setSelectedTemplate] = useState<string>("")
  // const [workspaceIcon, setWorkspaceIcon] = useState<string>("")
  // const [isPrivate, setIsPrivate] = useState<boolean>(true)
  // const [teamMembers, setTeamMembers] = useState<Array<string>>([])

  const router = useRouter()

  const handleCreateWorkspace = async () => {
    if (!workspaceName || !selectedEngine) return

    setIsCreating(true)

    try {
      // Real implementation with Inertia.js:
      // const response = await Inertia.post('/workspaces', {
      //   name: workspaceName,
      //   description,
      //   engine: selectedEngine,
      //   template: selectedTemplate, // For PlayCanvas
      //   icon: workspaceIcon,
      //   isPrivate,
      //   teamMembers
      // })

      // Simulate API call for demo
      await new Promise((resolve) => setTimeout(resolve, 1500))

      // Redirect to chat with new workspace
      // Real implementation: router.push(`/chat?workspace=${response.data.id}`)
      router.push(`/chat?workspace=${encodeURIComponent(workspaceName)}&engine=${selectedEngine}`)
    } catch (error) {
      console.error("Failed to create workspace:", error)
      // Handle error - show toast notification
    } finally {
      setIsCreating(false)
    }
  }

  const handleEngineSelect = (engineId: string) => {
    setSelectedEngine(engineId)

    // For PlayCanvas, might redirect to template selection
    // if (engineId === 'playcanvas') {
    //   router.push(`/templates?workspace=${workspaceName}&description=${description}`)
    // }
  }

  return (
    <div className="min-h-screen bg-background">
      <div className="container mx-auto px-4 py-8 max-w-4xl">
        {/* Header with navigation */}
        <div className="flex items-center space-x-4 mb-8">
          <Link href="/workspaces">
            <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-foreground">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Back to Workspaces
            </Button>
          </Link>
        </div>

        <div className="mb-8">
          <h1 className="text-3xl font-serif font-black text-foreground mb-2">Create New Workspace</h1>
          <p className="text-muted-foreground">Set up a new game development project with AI assistance</p>
        </div>

        <div className="grid gap-8 lg:grid-cols-2">
          {/* Workspace Details Form */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <Gamepad2 className="w-5 h-5 text-accent" />
                <span>Workspace Details</span>
              </CardTitle>
              <CardDescription>Give your workspace a name and description</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="workspace-name">Workspace Name</Label>
                <Input
                  id="workspace-name"
                  placeholder="My Awesome Game"
                  value={workspaceName}
                  onChange={(e) => setWorkspaceName(e.target.value)}
                  maxLength={50} // Backend validation should match
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="description">Description (Optional)</Label>
                <Textarea
                  id="description"
                  placeholder="A brief description of your game project..."
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  rows={3}
                  maxLength={200} // Backend validation should match
                />
              </div>
              {/* Additional fields for full implementation:
                - Workspace icon/color picker
                - Privacy settings (public/private)
                - Team member invitations
                - Project tags/categories
              */}
            </CardContent>
          </Card>

          {/* Engine Selection */}
          <Card>
            <CardHeader>
              <CardTitle>Choose Your Engine</CardTitle>
              <CardDescription>Select the game engine for this workspace</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {engines.map((engine) => {
                const Icon = engine.icon
                return (
                  <div
                    key={engine.id}
                    className={`p-4 rounded-lg border-2 cursor-pointer transition-all ${
                      selectedEngine === engine.id
                        ? "border-accent bg-accent/10"
                        : "border-border hover:border-accent/50"
                    }`}
                    onClick={() => handleEngineSelect(engine.id)}
                  >
                    <div className="flex items-start space-x-3">
                      {/* Engine icon with brand color */}
                      <div className={`w-10 h-10 ${engine.color} rounded-lg flex items-center justify-center`}>
                        <Icon className="w-5 h-5 text-white" />
                      </div>
                      <div className="flex-1">
                        <h3 className="font-semibold text-foreground mb-1">{engine.name}</h3>
                        <p className="text-sm text-muted-foreground mb-2">{engine.description}</p>
                        {/* Engine feature badges */}
                        <div className="flex flex-wrap gap-1">
                          {engine.features.map((feature) => (
                            <Badge key={feature} variant="secondary" className="text-xs">
                              {feature}
                            </Badge>
                          ))}
                        </div>
                      </div>
                    </div>
                  </div>
                )
              })}
            </CardContent>
          </Card>
        </div>

        {/* Create Button - Primary action */}
        <div className="mt-8 flex justify-end">
          <Button
            onClick={handleCreateWorkspace}
            disabled={!workspaceName || !selectedEngine || isCreating}
            className="bg-accent hover:bg-accent/90 text-accent-foreground px-8"
          >
            {isCreating ? (
              <>
                <div className="w-4 h-4 border-2 border-accent-foreground/30 border-t-accent-foreground rounded-full animate-spin mr-2" />
                Creating Workspace...
              </>
            ) : (
              <>
                <Gamepad2 className="w-4 h-4 mr-2" />
                Create Workspace
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  )
}
