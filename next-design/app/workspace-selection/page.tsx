"use client"

import { useState, useEffect } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Gamepad2, Plus, Users, Settings, ArrowRight, Code, Globe, Search } from "lucide-react"
import Link from "next/link"
import { useSearchParams } from "next/navigation"

export default function WorkspaceSelectionPage() {
  const searchParams = useSearchParams()
  const [selectedEngine, setSelectedEngine] = useState<"unreal" | "playcanvas" | null>(null)
  const [workspaceName, setWorkspaceName] = useState("")
  const [companyName, setCompanyName] = useState("")
  const [searchQuery, setSearchQuery] = useState("")

  useEffect(() => {
    const engine = searchParams.get("engine") as "unreal" | "playcanvas" | null
    if (engine) {
      setSelectedEngine(engine)
      setWorkspaceName(engine === "unreal" ? "My Unreal Project" : "My PlayCanvas Game")
    }
  }, [searchParams])

  const mockWorkspaces = [
    {
      id: "1",
      name: "Indie Game Studio",
      description: "3 active projects • 5 team members",
      engines: ["unreal", "playcanvas"],
      lastActive: "2 hours ago",
      projects: 3,
      members: 5,
    },
    {
      id: "2",
      name: "Personal Projects",
      description: "1 active project • Solo developer",
      engines: ["playcanvas"],
      lastActive: "1 day ago",
      projects: 1,
      members: 1,
    },
    {
      id: "3",
      name: "Mobile Games Co",
      description: "2 active projects • 3 team members",
      engines: ["playcanvas"],
      lastActive: "3 days ago",
      projects: 2,
      members: 3,
    },
  ]

  const filteredWorkspaces = mockWorkspaces.filter(
    (workspace) =>
      workspace.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      workspace.description.toLowerCase().includes(searchQuery.toLowerCase()),
  )

  return (
    <div className="min-h-screen bg-background">
      <div className="container mx-auto px-4 py-8 md:py-12 max-w-6xl">
        <div className="text-center mb-8 md:mb-12">
          <div className="flex items-center justify-center mb-6">
            <div className="w-16 h-16 bg-primary rounded-xl flex items-center justify-center">
              <Gamepad2 className="w-8 h-8 text-primary-foreground" />
            </div>
          </div>
          <h1 className="text-3xl md:text-4xl font-serif font-black text-foreground mb-4">
            {selectedEngine
              ? `Create ${selectedEngine === "unreal" ? "Unreal Engine" : "PlayCanvas"} Workspace`
              : "Choose Your Workspace"}
          </h1>
          <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
            {selectedEngine
              ? `Set up a new workspace for your ${selectedEngine === "unreal" ? "Unreal Engine" : "PlayCanvas"} project.`
              : "Select an existing workspace or create a new one to organize your game development projects."}
          </p>
        </div>

        <div className="grid lg:grid-cols-2 gap-8">
          {/* Left Column - Create New Workspace */}
          <div className="space-y-6">
            <Card className="border-border bg-card">
              <CardHeader>
                <div className="flex items-center space-x-3">
                  <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                    <Plus className="w-6 h-6 text-primary" />
                  </div>
                  <div>
                    <CardTitle className="font-serif font-bold">Create New Workspace</CardTitle>
                    <CardDescription>Start fresh with a new workspace for your projects</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-6">
                {!selectedEngine && (
                  <div className="space-y-3">
                    <Label>Choose Engine</Label>
                    <div className="grid grid-cols-2 gap-3">
                      <Button
                        variant={selectedEngine === "unreal" ? "default" : "outline"}
                        className="h-auto p-4 flex-col space-y-2"
                        onClick={() => setSelectedEngine("unreal")}
                      >
                        <Code className="w-6 h-6" />
                        <span className="text-sm">Unreal Engine</span>
                      </Button>
                      <Button
                        variant={selectedEngine === "playcanvas" ? "default" : "outline"}
                        className="h-auto p-4 flex-col space-y-2"
                        onClick={() => setSelectedEngine("playcanvas")}
                      >
                        <Globe className="w-6 h-6" />
                        <span className="text-sm">PlayCanvas</span>
                      </Button>
                    </div>
                  </div>
                )}

                {selectedEngine && (
                  <div className="flex items-center space-x-2">
                    <Badge variant="secondary" className="flex items-center space-x-1">
                      {selectedEngine === "unreal" ? <Code className="w-3 h-3" /> : <Globe className="w-3 h-3" />}
                      <span>{selectedEngine === "unreal" ? "Unreal Engine" : "PlayCanvas"}</span>
                    </Badge>
                    <Button variant="ghost" size="sm" onClick={() => setSelectedEngine(null)} className="text-xs">
                      Change
                    </Button>
                  </div>
                )}

                <div className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="workspaceName">Workspace Name</Label>
                    <Input
                      id="workspaceName"
                      placeholder="My Game Studio"
                      className="bg-input border-border"
                      value={workspaceName}
                      onChange={(e) => setWorkspaceName(e.target.value)}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="companyName">Company Name (Optional)</Label>
                    <Input
                      id="companyName"
                      placeholder="Awesome Games Inc."
                      className="bg-input border-border"
                      value={companyName}
                      onChange={(e) => setCompanyName(e.target.value)}
                    />
                  </div>
                </div>

                {selectedEngine && (
                  <div className="bg-muted/50 rounded-lg p-4 space-y-2">
                    <h4 className="font-medium text-sm">Next Steps:</h4>
                    <ul className="text-sm text-muted-foreground space-y-1">
                      {selectedEngine === "unreal" ? (
                        <>
                          <li>• Download and install the SurrealPilot plugin</li>
                          <li>• Connect your Unreal Engine project</li>
                          <li>• Start chatting with your AI copilot</li>
                        </>
                      ) : (
                        <>
                          <li>• Choose from curated game templates</li>
                          <li>• Get instant live preview of your game</li>
                          <li>• Start building with AI assistance</li>
                        </>
                      )}
                    </ul>
                  </div>
                )}

                <Button
                  className="w-full"
                  disabled={!workspaceName.trim() || !selectedEngine}
                  asChild={workspaceName.trim() && selectedEngine}
                >
                  {workspaceName.trim() && selectedEngine ? (
                    <Link href={`/chat?workspace=${encodeURIComponent(workspaceName)}&engine=${selectedEngine}`}>
                      Create Workspace <ArrowRight className="ml-2 w-4 h-4" />
                    </Link>
                  ) : (
                    <span>
                      Create Workspace <ArrowRight className="ml-2 w-4 h-4" />
                    </span>
                  )}
                </Button>
              </CardContent>
            </Card>
          </div>

          {/* Right Column - Existing Workspaces */}
          <div className="space-y-6">
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h2 className="text-xl font-serif font-bold text-foreground">Recent Workspaces</h2>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                  <Input
                    placeholder="Search workspaces..."
                    className="pl-10 w-64"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                  />
                </div>
              </div>

              <div className="space-y-3 max-h-[600px] overflow-y-auto">
                {filteredWorkspaces.map((workspace) => (
                  <Card
                    key={workspace.id}
                    className="border-border bg-card hover:bg-card/80 transition-colors cursor-pointer"
                  >
                    <CardContent className="p-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                          <div className="w-12 h-12 bg-gradient-to-br from-primary to-accent rounded-lg flex items-center justify-center">
                            {workspace.members > 1 ? (
                              <Users className="w-6 h-6 text-primary-foreground" />
                            ) : (
                              <Settings className="w-6 h-6 text-primary-foreground" />
                            )}
                          </div>
                          <div className="flex-1">
                            <h3 className="font-serif font-bold text-foreground">{workspace.name}</h3>
                            <p className="text-sm text-muted-foreground">{workspace.description}</p>
                            <div className="flex items-center space-x-2 mt-2">
                              {workspace.engines.map((engine) => (
                                <Badge key={engine} variant="secondary" className="text-xs">
                                  {engine === "unreal" ? "Unreal Engine" : "PlayCanvas"}
                                </Badge>
                              ))}
                              <span className="text-xs text-muted-foreground">• {workspace.lastActive}</span>
                            </div>
                          </div>
                        </div>
                        <Button variant="outline" asChild>
                          <Link href={`/chat?workspace=${encodeURIComponent(workspace.name)}`}>Select</Link>
                        </Button>
                      </div>
                    </CardContent>
                  </Card>
                ))}

                {filteredWorkspaces.length === 0 && searchQuery && (
                  <div className="text-center py-8">
                    <p className="text-muted-foreground">No workspaces found matching "{searchQuery}"</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        <div className="text-center mt-8">
          <p className="text-sm text-muted-foreground mb-4">
            Need help setting up your workspace?{" "}
            <Link href="/support" className="text-primary hover:text-primary/80 transition-colors">
              Contact support
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}
