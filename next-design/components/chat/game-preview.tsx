"use client"

import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Play, RotateCcw, Settings, Pause, ExternalLink, Smartphone, Monitor, Tablet } from "lucide-react"

export function GamePreview() {
  const [isPlaying, setIsPlaying] = useState(false)
  const [isFullscreen, setIsFullscreen] = useState(false)
  const [deviceMode, setDeviceMode] = useState<"desktop" | "tablet" | "mobile">("desktop")
  const [previewUrl] = useState("https://playcanv.as/p/JtL2iqIH/") // Sample PlayCanvas game

  const handlePlayPause = () => {
    setIsPlaying(!isPlaying)
  }

  const handleRestart = () => {
    setIsPlaying(false)
    // In real implementation, this would reload the iframe
    setTimeout(() => setIsPlaying(true), 100)
  }

  const handleFullscreen = () => {
    setIsFullscreen(!isFullscreen)
  }

  const getDeviceClass = () => {
    switch (deviceMode) {
      case "mobile":
        return "aspect-[9/16] max-w-[280px] mx-auto"
      case "tablet":
        return "aspect-[4/3] max-w-[400px] mx-auto"
      default:
        return "aspect-video"
    }
  }

  return (
    <Card className="border-border bg-card">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="text-sm font-serif font-bold">Game Preview</CardTitle>
          <div className="flex items-center space-x-1">
            <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
              <Settings className="w-3 h-3" />
            </Button>
            <Button variant="ghost" size="sm" className="h-6 w-6 p-0" onClick={handleFullscreen}>
              <ExternalLink className="w-3 h-3" />
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Device Mode Selector */}
        <div className="flex items-center justify-center space-x-1 bg-muted rounded-lg p-1">
          <Button
            variant={deviceMode === "desktop" ? "default" : "ghost"}
            size="sm"
            className="h-7 px-2"
            onClick={() => setDeviceMode("desktop")}
          >
            <Monitor className="w-3 h-3" />
          </Button>
          <Button
            variant={deviceMode === "tablet" ? "default" : "ghost"}
            size="sm"
            className="h-7 px-2"
            onClick={() => setDeviceMode("tablet")}
          >
            <Tablet className="w-3 h-3" />
          </Button>
          <Button
            variant={deviceMode === "mobile" ? "default" : "ghost"}
            size="sm"
            className="h-7 px-2"
            onClick={() => setDeviceMode("mobile")}
          >
            <Smartphone className="w-3 h-3" />
          </Button>
        </div>

        <div className={`bg-muted rounded-lg border border-border overflow-hidden ${getDeviceClass()}`}>
          {isPlaying ? (
            <iframe
              src={previewUrl}
              className="w-full h-full border-0"
              allow="gamepad; microphone; camera"
              title="PlayCanvas Game Preview"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center">
              <div className="text-center">
                <div className="w-16 h-16 bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-2">
                  <Play className="w-8 h-8 text-primary" />
                </div>
                <p className="text-sm text-muted-foreground">Click Play to start preview</p>
                <p className="text-xs text-muted-foreground mt-1">
                  {deviceMode === "mobile" && "Mobile View (9:16)"}
                  {deviceMode === "tablet" && "Tablet View (4:3)"}
                  {deviceMode === "desktop" && "Desktop View (16:9)"}
                </p>
              </div>
            </div>
          )}
        </div>

        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-2">
            <Button variant="outline" size="sm" onClick={handlePlayPause}>
              {isPlaying ? (
                <>
                  <Pause className="w-3 h-3 mr-1" />
                  Pause
                </>
              ) : (
                <>
                  <Play className="w-3 h-3 mr-1" />
                  Play
                </>
              )}
            </Button>
            <Button variant="outline" size="sm" onClick={handleRestart}>
              <RotateCcw className="w-3 h-3" />
            </Button>
            <Button variant="outline" size="sm" onClick={handleFullscreen}>
              <ExternalLink className="w-3 h-3" />
            </Button>
          </div>
          <Badge variant={isPlaying ? "default" : "secondary"} className="text-xs">
            {isPlaying ? "Playing" : "Ready"}
          </Badge>
        </div>

        <div className="grid grid-cols-2 gap-4 text-xs">
          <div>
            <span className="text-muted-foreground">Resolution</span>
            <p className="font-medium">
              {deviceMode === "mobile" && "360x640"}
              {deviceMode === "tablet" && "768x1024"}
              {deviceMode === "desktop" && "1280x720"}
            </p>
          </div>
          <div>
            <span className="text-muted-foreground">Build Size</span>
            <p className="font-medium">2.4 MB</p>
          </div>
          <div>
            <span className="text-muted-foreground">Last Updated</span>
            <p className="font-medium">2 min ago</p>
          </div>
          <div>
            <span className="text-muted-foreground">Status</span>
            <p className="font-medium text-green-600">Live</p>
          </div>
        </div>

        <div className="pt-2 border-t border-border">
          <div className="flex items-center justify-between">
            <span className="text-xs text-muted-foreground">Share Preview</span>
            <div className="flex space-x-1">
              <Button variant="ghost" size="sm" className="h-6 px-2 text-xs">
                Copy Link
              </Button>
              <Button variant="ghost" size="sm" className="h-6 px-2 text-xs">
                QR Code
              </Button>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
