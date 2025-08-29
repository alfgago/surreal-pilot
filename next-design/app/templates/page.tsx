"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { MainLayout } from "@/components/layout/main-layout"
import { Search, Play, Download, Star, Clock } from "lucide-react"
import Link from "next/link"

const templates = [
  {
    id: "fps-starter",
    name: "Starter FPS",
    description: "Complete first-person shooter template with weapons, enemies, and multiplayer support",
    thumbnail: "/fps-game-screenshot.png",
    category: "Action",
    difficulty: "Beginner",
    estimatedSize: "2.5 MB",
    downloads: 1247,
    rating: 4.8,
    tags: ["FPS", "Multiplayer", "Weapons"],
  },
  {
    id: "third-person",
    name: "Third-Person Adventure",
    description: "Adventure game template with character controller, inventory, and quest system",
    thumbnail: "/third-person-adventure-game.png",
    category: "Adventure",
    difficulty: "Intermediate",
    estimatedSize: "3.2 MB",
    downloads: 892,
    rating: 4.6,
    tags: ["Adventure", "RPG", "Quests"],
  },
  {
    id: "platformer-2d",
    name: "2D Platformer",
    description: "Classic 2D platformer with physics, collectibles, and level progression",
    thumbnail: "/2d-platformer-game.png",
    category: "Platformer",
    difficulty: "Beginner",
    estimatedSize: "1.8 MB",
    downloads: 2156,
    rating: 4.9,
    tags: ["2D", "Physics", "Collectibles"],
  },
  {
    id: "racing-game",
    name: "Racing Circuit",
    description: "High-speed racing game with realistic physics and multiple tracks",
    thumbnail: "/racing-game-circuit.png",
    category: "Racing",
    difficulty: "Advanced",
    estimatedSize: "4.1 MB",
    downloads: 634,
    rating: 4.7,
    tags: ["Racing", "Physics", "Multiplayer"],
  },
  {
    id: "puzzle-match",
    name: "Match-3 Puzzle",
    description: "Addictive match-3 puzzle game with power-ups and level progression",
    thumbnail: "/match-3-puzzle.png",
    category: "Puzzle",
    difficulty: "Beginner",
    estimatedSize: "1.2 MB",
    downloads: 3421,
    rating: 4.5,
    tags: ["Puzzle", "Match-3", "Casual"],
  },
  {
    id: "tower-defense",
    name: "Tower Defense",
    description: "Strategic tower defense with multiple tower types and enemy waves",
    thumbnail: "/tower-defense-game.png",
    category: "Strategy",
    difficulty: "Intermediate",
    estimatedSize: "2.9 MB",
    downloads: 1089,
    rating: 4.4,
    tags: ["Strategy", "Defense", "Waves"],
  },
]

const categories = ["All", "Action", "Adventure", "Platformer", "Racing", "Puzzle", "Strategy"]
const difficulties = ["All", "Beginner", "Intermediate", "Advanced"]

export default function TemplatesPage() {
  const [searchQuery, setSearchQuery] = useState("")
  const [selectedCategory, setSelectedCategory] = useState("All")
  const [selectedDifficulty, setSelectedDifficulty] = useState("All")

  const filteredTemplates = templates.filter((template) => {
    const matchesSearch =
      template.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      template.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
      template.tags.some((tag) => tag.toLowerCase().includes(searchQuery.toLowerCase()))
    const matchesCategory = selectedCategory === "All" || template.category === selectedCategory
    const matchesDifficulty = selectedDifficulty === "All" || template.difficulty === selectedDifficulty

    return matchesSearch && matchesCategory && matchesDifficulty
  })

  return (
    <MainLayout currentWorkspace="Web Racing Game" currentEngine="playcanvas">
      <div className="p-6 max-w-7xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-serif font-black text-foreground mb-2">PlayCanvas Templates</h1>
          <p className="text-muted-foreground">
            Start your game development journey with our curated collection of templates
          </p>
        </div>

        {/* Search and Filters */}
        <div className="mb-8 space-y-4">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <Input
              placeholder="Search templates..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10"
            />
          </div>

          <div className="flex flex-wrap gap-4">
            <div className="flex flex-wrap gap-2">
              <span className="text-sm font-medium text-muted-foreground">Category:</span>
              {categories.map((category) => (
                <Button
                  key={category}
                  variant={selectedCategory === category ? "default" : "outline"}
                  size="sm"
                  onClick={() => setSelectedCategory(category)}
                >
                  {category}
                </Button>
              ))}
            </div>

            <div className="flex flex-wrap gap-2">
              <span className="text-sm font-medium text-muted-foreground">Difficulty:</span>
              {difficulties.map((difficulty) => (
                <Button
                  key={difficulty}
                  variant={selectedDifficulty === difficulty ? "default" : "outline"}
                  size="sm"
                  onClick={() => setSelectedDifficulty(difficulty)}
                >
                  {difficulty}
                </Button>
              ))}
            </div>
          </div>
        </div>

        {/* Templates Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredTemplates.map((template) => (
            <Card key={template.id} className="overflow-hidden hover:shadow-lg transition-shadow">
              <div className="aspect-video relative overflow-hidden">
                <img
                  src={template.thumbnail || "/placeholder.svg"}
                  alt={template.name}
                  className="w-full h-full object-cover"
                />
                <div className="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center">
                  <Button size="sm" className="bg-white/20 backdrop-blur-sm hover:bg-white/30">
                    <Play className="w-4 h-4 mr-2" />
                    Preview
                  </Button>
                </div>
              </div>

              <CardHeader>
                <div className="flex items-start justify-between">
                  <div>
                    <CardTitle className="text-lg font-serif font-bold">{template.name}</CardTitle>
                    <div className="flex items-center space-x-2 mt-1">
                      <Badge variant="secondary" className="text-xs">
                        {template.category}
                      </Badge>
                      <Badge variant="outline" className="text-xs">
                        {template.difficulty}
                      </Badge>
                    </div>
                  </div>
                  <div className="flex items-center space-x-1 text-sm text-muted-foreground">
                    <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
                    <span>{template.rating}</span>
                  </div>
                </div>
                <CardDescription className="text-sm">{template.description}</CardDescription>
              </CardHeader>

              <CardContent>
                <div className="flex items-center justify-between text-sm text-muted-foreground mb-4">
                  <div className="flex items-center space-x-4">
                    <div className="flex items-center space-x-1">
                      <Download className="w-3 h-3" />
                      <span>{template.downloads.toLocaleString()}</span>
                    </div>
                    <div className="flex items-center space-x-1">
                      <Clock className="w-3 h-3" />
                      <span>{template.estimatedSize}</span>
                    </div>
                  </div>
                </div>

                <div className="flex flex-wrap gap-1 mb-4">
                  {template.tags.map((tag) => (
                    <Badge key={tag} variant="outline" className="text-xs">
                      {tag}
                    </Badge>
                  ))}
                </div>

                <Button asChild className="w-full">
                  <Link href={`/workspaces/new?template=${template.id}`}>Use Template</Link>
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>

        {filteredTemplates.length === 0 && (
          <div className="text-center py-12">
            <p className="text-muted-foreground">No templates found matching your criteria.</p>
            <Button
              variant="outline"
              className="mt-4 bg-transparent"
              onClick={() => {
                setSearchQuery("")
                setSelectedCategory("All")
                setSelectedDifficulty("All")
              }}
            >
              Clear Filters
            </Button>
          </div>
        )}
      </div>
    </MainLayout>
  )
}
