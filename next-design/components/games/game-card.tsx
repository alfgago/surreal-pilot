import { Card, CardContent, CardHeader } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { Play, MoreHorizontal, Share, Download, Trash2, Archive, Code, Globe, Clock } from "lucide-react"
import Link from "next/link"
import Image from "next/image"
import { cn } from "@/lib/utils"

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

interface GameCardProps {
  game: Game
  viewMode: "grid" | "list"
}

export function GameCard({ game, viewMode }: GameCardProps) {
  const getStatusColor = (status: string) => {
    switch (status) {
      case "active":
        return "bg-primary/10 text-primary"
      case "published":
        return "bg-green-500/10 text-green-500"
      case "archived":
        return "bg-muted text-muted-foreground"
      default:
        return "bg-muted text-muted-foreground"
    }
  }

  if (viewMode === "list") {
    return (
      <Card className="border-border bg-card hover:bg-card/80 transition-colors">
        <CardContent className="p-6">
          <div className="flex items-center space-x-4">
            <div className="relative w-24 h-16 rounded-lg overflow-hidden bg-muted">
              <Image src={game.thumbnail || "/placeholder.svg"} alt={game.title} fill className="object-cover" />
            </div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center space-x-2 mb-1">
                <h3 className="font-serif font-bold text-foreground truncate">{game.title}</h3>
                <Badge variant="secondary" className="text-xs">
                  {game.engine === "unreal" ? (
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
                <Badge className={cn("text-xs", getStatusColor(game.status))}>{game.status}</Badge>
              </div>
              <p className="text-sm text-muted-foreground truncate mb-2">{game.description}</p>
              <div className="flex items-center space-x-4 text-xs text-muted-foreground">
                <div className="flex items-center">
                  <Clock className="w-3 h-3 mr-1" />
                  {game.lastModified.toLocaleDateString()}
                </div>
                <span>{game.version}</span>
                <span>{game.buildSize}</span>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <Button variant="outline" size="sm" asChild>
                <Link href={`/games/${game.id}`}>
                  <Play className="w-3 h-3 mr-1" />
                  Open
                </Link>
              </Button>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="sm">
                    <MoreHorizontal className="w-4 h-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem>
                    <Share className="w-4 h-4 mr-2" />
                    Share
                  </DropdownMenuItem>
                  <DropdownMenuItem>
                    <Download className="w-4 h-4 mr-2" />
                    Export
                  </DropdownMenuItem>
                  <DropdownMenuItem>
                    <Archive className="w-4 h-4 mr-2" />
                    Archive
                  </DropdownMenuItem>
                  <DropdownMenuItem className="text-destructive">
                    <Trash2 className="w-4 h-4 mr-2" />
                    Delete
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className="border-border bg-card hover:bg-card/80 transition-all duration-200 hover:scale-[1.02] group">
      <CardHeader className="p-0">
        <div className="relative aspect-video rounded-t-lg overflow-hidden bg-muted">
          <Image src={game.thumbnail || "/placeholder.svg"} alt={game.title} fill className="object-cover" />
          <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
            <Button
              variant="secondary"
              size="sm"
              className="opacity-0 group-hover:opacity-100 transition-opacity"
              asChild
            >
              <Link href={`/games/${game.id}`}>
                <Play className="w-4 h-4 mr-1" />
                Open
              </Link>
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent className="p-4">
        <div className="flex items-start justify-between mb-2">
          <h3 className="font-serif font-bold text-foreground truncate flex-1">{game.title}</h3>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                <MoreHorizontal className="w-4 h-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem>
                <Share className="w-4 h-4 mr-2" />
                Share
              </DropdownMenuItem>
              <DropdownMenuItem>
                <Download className="w-4 h-4 mr-2" />
                Export
              </DropdownMenuItem>
              <DropdownMenuItem>
                <Archive className="w-4 h-4 mr-2" />
                Archive
              </DropdownMenuItem>
              <DropdownMenuItem className="text-destructive">
                <Trash2 className="w-4 h-4 mr-2" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
        <p className="text-sm text-muted-foreground mb-3 line-clamp-2">{game.description}</p>
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center space-x-2">
            <Badge variant="secondary" className="text-xs">
              {game.engine === "unreal" ? (
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
            <Badge className={cn("text-xs", getStatusColor(game.status))}>{game.status}</Badge>
          </div>
        </div>
        <div className="flex items-center justify-between text-xs text-muted-foreground">
          <div className="flex items-center">
            <Clock className="w-3 h-3 mr-1" />
            {game.lastModified.toLocaleDateString()}
          </div>
          <div className="flex items-center space-x-2">
            <span>{game.version}</span>
            <span>•</span>
            <span>{game.buildSize}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
