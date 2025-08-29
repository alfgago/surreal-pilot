"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Separator } from "@/components/ui/separator"
import { GamePlayer } from "@/components/games/game-player"
import { FileExplorer } from "@/components/games/file-explorer"
import { BuildHistory } from "@/components/games/build-history"
import { ShareOptions } from "@/components/games/share-options"
import { ArrowLeft, Share, Download, Settings, Code, Globe, Clock, HardDrive, Users } from "lucide-react"
import Link from "next/link"
import Image from "next/image"

// Mock game data
const gameData = {
  id: "1",
  title: "Space Explorer VR",
  description: "An immersive VR space exploration game with realistic physics and stunning visuals",
  engine: "unreal" as const,
  thumbnail: "/space-exploration-game.png",
  lastModified: new Date(Date.now() - 86400000),
  status: "active" as const,
  buildSize: "1.2 GB",
  version: "v0.3.1",
  author: "John Doe",
  collaborators: 3,
  totalBuilds: 12,
  playTime: "2h 34m",
}

export default function GameDetailsPage({ params }: { params: { id: string } }) {
  const [isPlaying, setIsPlaying] = useState(false)
  const [activeTab, setActiveTab] = useState("overview")

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b border-border bg-card/50 backdrop-blur-sm">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <Link
                href="/games"
                className="flex items-center space-x-2 text-muted-foreground hover:text-foreground transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                <span>Back to Games</span>
              </Link>
              <div className="flex items-center space-x-3">
                <div className="relative w-12 h-12 rounded-lg overflow-hidden bg-muted">
                  <Image
                    src={gameData.thumbnail || "/placeholder.svg"}
                    alt={gameData.title}
                    fill
                    className="object-cover"
                  />
                </div>
                <div>
                  <h1 className="text-xl font-serif font-black text-foreground">{gameData.title}</h1>
                  <div className="flex items-center space-x-2">
                    <Badge variant="secondary" className="text-xs">
                      {gameData.engine === "unreal" ? (
                        <>
                          <Code className="w-3 h-3 mr-1" />
                          Unreal Engine
                        </>
                      ) : (
                        <>
                          <Globe className="w-3 h-3 mr-1" />
                          PlayCanvas
                        </>
                      )}
                    </Badge>
                    <Badge className="text-xs bg-primary/10 text-primary">{gameData.status}</Badge>
                    <span className="text-xs text-muted-foreground">{gameData.version}</span>
                  </div>
                </div>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <Button variant="outline">
                <Share className="w-4 h-4 mr-2" />
                Share
              </Button>
              <Button variant="outline">
                <Download className="w-4 h-4 mr-2" />
                Export
              </Button>
              <Button>
                <Settings className="w-4 h-4 mr-2" />
                Settings
              </Button>
            </div>
          </div>
        </div>
      </header>

      <div className="container mx-auto px-4 py-8">
        <div className="grid lg:grid-cols-3 gap-8">
          {/* Main Content */}
          <div className="lg:col-span-2 space-y-6">
            {/* Game Player */}
            <GamePlayer game={gameData} isPlaying={isPlaying} onPlayToggle={() => setIsPlaying(!isPlaying)} />

            {/* Tabs */}
            <Tabs value={activeTab} onValueChange={setActiveTab}>
              <TabsList className="grid w-full grid-cols-4">
                <TabsTrigger value="overview">Overview</TabsTrigger>
                <TabsTrigger value="files">Files</TabsTrigger>
                <TabsTrigger value="builds">Builds</TabsTrigger>
                <TabsTrigger value="share">Share</TabsTrigger>
              </TabsList>

              <TabsContent value="overview" className="space-y-6">
                <Card className="border-border bg-card">
                  <CardHeader>
                    <CardTitle className="font-serif font-bold">About This Game</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <p className="text-muted-foreground leading-relaxed">{gameData.description}</p>
                  </CardContent>
                </Card>

                <div className="grid md:grid-cols-2 gap-6">
                  <Card className="border-border bg-card">
                    <CardHeader>
                      <CardTitle className="font-serif font-bold text-sm">Development Stats</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">Total Play Time</span>
                        <span className="text-sm font-medium">{gameData.playTime}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">Build Size</span>
                        <span className="text-sm font-medium">{gameData.buildSize}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">Total Builds</span>
                        <span className="text-sm font-medium">{gameData.totalBuilds}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">Collaborators</span>
                        <span className="text-sm font-medium">{gameData.collaborators}</span>
                      </div>
                    </CardContent>
                  </Card>

                  <Card className="border-border bg-card">
                    <CardHeader>
                      <CardTitle className="font-serif font-bold text-sm">Recent Activity</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                      <div className="flex items-center space-x-3">
                        <div className="w-2 h-2 bg-primary rounded-full"></div>
                        <div className="flex-1">
                          <p className="text-sm">Updated player movement system</p>
                          <p className="text-xs text-muted-foreground">2 hours ago</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-3">
                        <div className="w-2 h-2 bg-muted rounded-full"></div>
                        <div className="flex-1">
                          <p className="text-sm">Fixed collision detection bug</p>
                          <p className="text-xs text-muted-foreground">1 day ago</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-3">
                        <div className="w-2 h-2 bg-muted rounded-full"></div>
                        <div className="flex-1">
                          <p className="text-sm">Added new space station model</p>
                          <p className="text-xs text-muted-foreground">3 days ago</p>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </TabsContent>

              <TabsContent value="files">
                <FileExplorer gameId={gameData.id} engine={gameData.engine} />
              </TabsContent>

              <TabsContent value="builds">
                <BuildHistory gameId={gameData.id} />
              </TabsContent>

              <TabsContent value="share">
                <ShareOptions game={gameData} />
              </TabsContent>
            </Tabs>
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            <Card className="border-border bg-card">
              <CardHeader>
                <CardTitle className="font-serif font-bold text-sm">Game Info</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <span className="text-xs text-muted-foreground">Created by</span>
                  <p className="text-sm font-medium">{gameData.author}</p>
                </div>
                <Separator />
                <div>
                  <span className="text-xs text-muted-foreground">Last Modified</span>
                  <div className="flex items-center mt-1">
                    <Clock className="w-3 h-3 mr-1 text-muted-foreground" />
                    <span className="text-sm">{gameData.lastModified.toLocaleDateString()}</span>
                  </div>
                </div>
                <Separator />
                <div>
                  <span className="text-xs text-muted-foreground">Engine Version</span>
                  <p className="text-sm font-medium">
                    {gameData.engine === "unreal" ? "Unreal Engine 5.3" : "PlayCanvas 1.65.0"}
                  </p>
                </div>
                <Separator />
                <div>
                  <span className="text-xs text-muted-foreground">Build Size</span>
                  <div className="flex items-center mt-1">
                    <HardDrive className="w-3 h-3 mr-1 text-muted-foreground" />
                    <span className="text-sm">{gameData.buildSize}</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card className="border-border bg-card">
              <CardHeader>
                <CardTitle className="font-serif font-bold text-sm">Team</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex items-center space-x-3">
                  <div className="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
                    <span className="text-xs font-medium text-primary-foreground">JD</span>
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium">John Doe</p>
                    <p className="text-xs text-muted-foreground">Owner</p>
                  </div>
                </div>
                <div className="flex items-center space-x-3">
                  <div className="w-8 h-8 bg-secondary rounded-full flex items-center justify-center">
                    <span className="text-xs font-medium text-secondary-foreground">AS</span>
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium">Alice Smith</p>
                    <p className="text-xs text-muted-foreground">Developer</p>
                  </div>
                </div>
                <div className="flex items-center space-x-3">
                  <div className="w-8 h-8 bg-accent rounded-full flex items-center justify-center">
                    <span className="text-xs font-medium text-accent-foreground">BJ</span>
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium">Bob Johnson</p>
                    <p className="text-xs text-muted-foreground">Artist</p>
                  </div>
                </div>
                <Button variant="outline" size="sm" className="w-full bg-transparent">
                  <Users className="w-3 h-3 mr-2" />
                  Manage Team
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  )
}
