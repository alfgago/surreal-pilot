"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent } from "@/components/ui/card"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { GameGrid } from "@/components/games/game-grid"
import { Search, Plus, Grid3X3, List, Gamepad2, ArrowLeft, Filter } from "lucide-react"
import Link from "next/link"

interface Game {
  id: string
  title: string
  description: string
  engine: "unreal" | "playcanvas"
  thumbnail: string
  lastModified: Date
  status: "active" | "archived" | "published"
  buildSize: string
  version: string
}

const mockGames: Game[] = [
  {
    id: "1",
    title: "Space Explorer VR",
    description: "An immersive VR space exploration game with realistic physics",
    engine: "unreal",
    thumbnail: "/space-exploration-game.png",
    lastModified: new Date(Date.now() - 86400000),
    status: "active",
    buildSize: "1.2 GB",
    version: "v0.3.1",
  },
  {
    id: "2",
    title: "Puzzle Master Web",
    description: "A challenging puzzle game for web browsers",
    engine: "playcanvas",
    thumbnail: "/colorful-puzzle-game.png",
    lastModified: new Date(Date.now() - 3600000),
    status: "published",
    buildSize: "15.4 MB",
    version: "v1.0.2",
  },
  {
    id: "3",
    title: "Racing Championship",
    description: "High-speed racing game with multiplayer support",
    engine: "unreal",
    thumbnail: "/stylized-racing-game.png",
    lastModified: new Date(Date.now() - 172800000),
    status: "active",
    buildSize: "2.8 GB",
    version: "v0.8.5",
  },
  {
    id: "4",
    title: "Mobile Adventure",
    description: "Cross-platform adventure game for mobile devices",
    engine: "playcanvas",
    thumbnail: "/mobile-adventure-game.png",
    lastModified: new Date(Date.now() - 259200000),
    status: "archived",
    buildSize: "42.1 MB",
    version: "v2.1.0",
  },
]

export default function GamesPage() {
  const [games, setGames] = useState<Game[]>(mockGames)
  const [searchQuery, setSearchQuery] = useState("")
  const [filterEngine, setFilterEngine] = useState<string>("all")
  const [filterStatus, setFilterStatus] = useState<string>("all")
  const [viewMode, setViewMode] = useState<"grid" | "list">("grid")
  const [showMobileFilters, setShowMobileFilters] = useState(false) // Added mobile filters toggle

  const filteredGames = games.filter((game) => {
    const matchesSearch =
      game.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      game.description.toLowerCase().includes(searchQuery.toLowerCase())
    const matchesEngine = filterEngine === "all" || game.engine === filterEngine
    const matchesStatus = filterStatus === "all" || game.status === filterStatus
    return matchesSearch && matchesEngine && matchesStatus
  })

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b border-border bg-card/50 backdrop-blur-sm">
        <div className="container mx-auto px-3 md:px-4 py-3 md:py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2 md:space-x-4 min-w-0">
              <Link
                href="/chat"
                className="flex items-center space-x-1 md:space-x-2 text-muted-foreground hover:text-foreground transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                <span className="hidden sm:inline">Back to Chat</span>
              </Link>
              <div className="flex items-center space-x-2 min-w-0">
                <div className="w-6 h-6 md:w-8 md:h-8 bg-primary rounded-lg flex items-center justify-center">
                  <Gamepad2 className="w-4 h-4 md:w-5 md:h-5 text-primary-foreground" />
                </div>
                <div className="min-w-0">
                  <h1 className="text-lg md:text-xl font-serif font-black text-foreground truncate">My Games</h1>
                  <p className="text-xs md:text-sm text-muted-foreground">{filteredGames.length} projects</p>
                </div>
              </div>
            </div>
            <Button asChild size="sm" className="shrink-0">
              <Link href="/games/new">
                <Plus className="w-4 h-4 md:mr-2" />
                <span className="hidden md:inline">New Game</span>
              </Link>
            </Button>
          </div>
        </div>
      </header>

      <div className="container mx-auto px-3 md:px-4 py-4 md:py-8">
        {/* Filters and Search */}
        <Card className="border-border bg-card mb-4 md:mb-8">
          <CardContent className="p-3 md:p-6">
            {/* Mobile Search and Filter Toggle */}
            <div className="flex items-center space-x-2 md:hidden mb-4">
              <div className="flex-1 relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input
                  placeholder="Search games..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-10 bg-input border-border"
                />
              </div>
              <Button variant="outline" size="sm" onClick={() => setShowMobileFilters(!showMobileFilters)}>
                <Filter className="w-4 h-4" />
              </Button>
            </div>

            {/* Desktop Layout */}
            <div className="hidden md:flex flex-col md:flex-row gap-4">
              <div className="flex-1 relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input
                  placeholder="Search games..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-10 bg-input border-border"
                />
              </div>
              <div className="flex items-center space-x-4">
                <Select value={filterEngine} onValueChange={setFilterEngine}>
                  <SelectTrigger className="w-40 bg-input border-border">
                    <SelectValue placeholder="Engine" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Engines</SelectItem>
                    <SelectItem value="unreal">Unreal Engine</SelectItem>
                    <SelectItem value="playcanvas">PlayCanvas</SelectItem>
                  </SelectContent>
                </Select>
                <Select value={filterStatus} onValueChange={setFilterStatus}>
                  <SelectTrigger className="w-32 bg-input border-border">
                    <SelectValue placeholder="Status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Status</SelectItem>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="published">Published</SelectItem>
                    <SelectItem value="archived">Archived</SelectItem>
                  </SelectContent>
                </Select>
                <div className="flex items-center border border-border rounded-lg">
                  <Button
                    variant={viewMode === "grid" ? "default" : "ghost"}
                    size="sm"
                    onClick={() => setViewMode("grid")}
                    className="rounded-r-none"
                  >
                    <Grid3X3 className="w-4 h-4" />
                  </Button>
                  <Button
                    variant={viewMode === "list" ? "default" : "ghost"}
                    size="sm"
                    onClick={() => setViewMode("list")}
                    className="rounded-l-none"
                  >
                    <List className="w-4 h-4" />
                  </Button>
                </div>
              </div>
            </div>

            {/* Mobile Filters */}
            {showMobileFilters && (
              <div className="md:hidden space-y-3 pt-4 border-t border-border">
                <div className="grid grid-cols-2 gap-3">
                  <Select value={filterEngine} onValueChange={setFilterEngine}>
                    <SelectTrigger className="bg-input border-border">
                      <SelectValue placeholder="Engine" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Engines</SelectItem>
                      <SelectItem value="unreal">Unreal Engine</SelectItem>
                      <SelectItem value="playcanvas">PlayCanvas</SelectItem>
                    </SelectContent>
                  </Select>
                  <Select value={filterStatus} onValueChange={setFilterStatus}>
                    <SelectTrigger className="bg-input border-border">
                      <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Status</SelectItem>
                      <SelectItem value="active">Active</SelectItem>
                      <SelectItem value="published">Published</SelectItem>
                      <SelectItem value="archived">Archived</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex items-center justify-center">
                  <div className="flex items-center border border-border rounded-lg">
                    <Button
                      variant={viewMode === "grid" ? "default" : "ghost"}
                      size="sm"
                      onClick={() => setViewMode("grid")}
                      className="rounded-r-none"
                    >
                      <Grid3X3 className="w-4 h-4" />
                    </Button>
                    <Button
                      variant={viewMode === "list" ? "default" : "ghost"}
                      size="sm"
                      onClick={() => setViewMode("list")}
                      className="rounded-l-none"
                    >
                      <List className="w-4 h-4" />
                    </Button>
                  </div>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Games Grid/List */}
        {filteredGames.length === 0 ? (
          <Card className="border-border bg-card">
            <CardContent className="p-6 md:p-12 text-center">
              <div className="w-12 h-12 md:w-16 md:h-16 bg-muted rounded-full flex items-center justify-center mx-auto mb-4">
                <Gamepad2 className="w-6 h-6 md:w-8 md:h-8 text-muted-foreground" />
              </div>
              <h3 className="text-base md:text-lg font-serif font-bold text-foreground mb-2">No games found</h3>
              <p className="text-sm md:text-base text-muted-foreground mb-4 md:mb-6">
                {searchQuery || filterEngine !== "all" || filterStatus !== "all"
                  ? "Try adjusting your search or filters"
                  : "Create your first game to get started"}
              </p>
              <Button asChild size="sm">
                <Link href="/games/new">
                  <Plus className="w-4 h-4 mr-2" />
                  Create New Game
                </Link>
              </Button>
            </CardContent>
          </Card>
        ) : (
          <GameGrid games={filteredGames} viewMode={viewMode} />
        )}
      </div>
    </div>
  )
}
