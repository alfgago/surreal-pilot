"use client"

import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Play, Pause, RotateCcw, Maximize, Volume2, Settings, ExternalLink } from "lucide-react"
import Image from "next/image"
import { useState } from "react"

interface Game {
  id: string
  title: string
  engine: "unreal" | "playcanvas"
  thumbnail: string
  playcanvasUrl?: string // Added PlayCanvas URL for iframe preview
}

interface GamePlayerProps {
  game: Game
  isPlaying: boolean
  onPlayToggle: () => void
}

export function GamePlayer({ game, isPlaying, onPlayToggle }: GamePlayerProps) {
  const [isFullscreen, setIsFullscreen] = useState(false)
  const [volume, setVolume] = useState(1)

  const renderGameContent = () => {
    if (game.engine === "playcanvas" && isPlaying && game.playcanvasUrl) {
      return (
        <iframe
          src={game.playcanvasUrl}
          className="w-full h-full border-0"
          allow="fullscreen; gamepad; microphone; camera"
          title={`${game.title} - PlayCanvas Game`}
        />
      )
    }

    return (
      <>
        <Image src={game.thumbnail || "/placeholder.svg"} alt={game.title} fill className="object-cover" />
        {/* Play Overlay */}
        {!isPlaying && (
          <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
            <Button size="lg" onClick={onPlayToggle} className="h-12 w-12 md:h-16 md:w-16 rounded-full">
              <Play className="w-6 h-6 md:w-8 md:h-8" />
            </Button>
          </div>
        )}
      </>
    )
  }

  return (
    <Card className="border-border bg-card">
      <CardContent className="p-0">
        <div
          className={`relative ${isFullscreen ? "fixed inset-0 z-50" : "aspect-video"} bg-muted rounded-lg overflow-hidden`}
        >
          {renderGameContent()}

          {/* Controls Overlay - Made responsive for mobile */}
          <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-2 md:p-4">
            <div className="flex items-center justify-between flex-wrap gap-2">
              <div className="flex items-center space-x-2 md:space-x-3">
                <Button variant="secondary" size="sm" onClick={onPlayToggle}>
                  {isPlaying ? <Pause className="w-3 h-3 md:w-4 md:h-4" /> : <Play className="w-3 h-3 md:w-4 md:h-4" />}
                </Button>
                <Button variant="secondary" size="sm">
                  <RotateCcw className="w-3 h-3 md:w-4 md:h-4" />
                </Button>
                <Button variant="secondary" size="sm">
                  <Volume2 className="w-3 h-3 md:w-4 md:h-4" />
                </Button>
                {game.engine === "playcanvas" && game.playcanvasUrl && (
                  <Button variant="secondary" size="sm" asChild>
                    <a href={game.playcanvasUrl} target="_blank" rel="noopener noreferrer">
                      <ExternalLink className="w-3 h-3 md:w-4 md:h-4" />
                    </a>
                  </Button>
                )}
              </div>

              <div className="flex items-center space-x-2">
                <Badge variant="secondary" className="text-xs">
                  {isPlaying ? "Playing" : "Paused"}
                </Badge>
                <Button variant="secondary" size="sm" className="hidden md:inline-flex">
                  <Settings className="w-4 h-4" />
                </Button>
                <Button variant="secondary" size="sm" onClick={() => setIsFullscreen(!isFullscreen)}>
                  <Maximize className="w-3 h-3 md:w-4 md:h-4" />
                </Button>
              </div>
            </div>
          </div>
        </div>

        {/* Game Info Bar - Made responsive */}
        <div className="p-3 md:p-4 border-t border-border">
          <div className="flex items-center justify-between flex-wrap gap-2">
            <div className="flex items-center space-x-4">
              <div>
                <h3 className="font-serif font-bold text-foreground text-sm md:text-base">{game.title}</h3>
                <p className="text-xs md:text-sm text-muted-foreground">
                  {game.engine === "unreal" ? "Unreal Engine Build" : "PlayCanvas WebGL Build"}
                </p>
              </div>
            </div>
            <div className="flex items-center space-x-2 text-xs md:text-sm text-muted-foreground">
              <span className="hidden md:inline">Resolution: 1920x1080</span>
              <span className="hidden md:inline">•</span>
              <span>FPS: {isPlaying ? "60" : "0"}</span>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
