"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { MainLayout } from "@/components/layout/main-layout"
import {
  Play,
  Pause,
  RotateCcw,
  Share2,
  ExternalLink,
  Smartphone,
  Tablet,
  Monitor,
  QrCode,
  Copy,
  RefreshCw,
  Settings,
  Fullscreen,
} from "lucide-react"
import { GamePreview } from "@/components/chat/game-preview"

export default function PreviewPage() {
  const [isPlaying, setIsPlaying] = useState(true)
  const [deviceFrame, setDeviceFrame] = useState<"desktop" | "tablet" | "mobile">("desktop")
  const [autoRefresh, setAutoRefresh] = useState(true)

  const previewUrl = "https://preview.playcanvas.com/web-racing-game-v1"

  const handleCopyUrl = () => {
    navigator.clipboard.writeText(previewUrl)
    // In real app, show toast notification
  }

  const handleRefresh = () => {
    // In real app, refresh the preview iframe
    console.log("Refreshing preview...")
  }

  return (
    <MainLayout currentWorkspace="Web Racing Game" currentEngine="playcanvas">
      <div className="h-full flex flex-col">
        {/* Header */}
        <div className="border-b border-border bg-card/30 p-4">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-serif font-black text-foreground">Game Preview</h1>
              <p className="text-sm text-muted-foreground">Test your game in real-time</p>
            </div>

            <div className="flex items-center space-x-2">
              <Badge variant="outline" className="text-xs">
                Preview Updated • 2 min ago
              </Badge>
              <Button variant="outline" size="sm" onClick={handleRefresh}>
                <RefreshCw className="w-4 h-4 mr-2" />
                Refresh
              </Button>
              <Button variant="outline" size="sm">
                <Settings className="w-4 h-4 mr-2" />
                Settings
              </Button>
            </div>
          </div>
        </div>

        <div className="flex-1 flex">
          {/* Main Preview Area */}
          <div className="flex-1 flex flex-col bg-muted/20">
            {/* Preview Controls */}
            <div className="border-b border-border bg-card/50 p-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Button variant="outline" size="sm" onClick={() => setIsPlaying(!isPlaying)}>
                    {isPlaying ? (
                      <>
                        <Pause className="w-4 h-4 mr-2" />
                        Pause
                      </>
                    ) : (
                      <>
                        <Play className="w-4 h-4 mr-2" />
                        Play
                      </>
                    )}
                  </Button>
                  <Button variant="outline" size="sm">
                    <RotateCcw className="w-4 h-4 mr-2" />
                    Restart
                  </Button>
                  <Button variant="outline" size="sm">
                    <Fullscreen className="w-4 h-4 mr-2" />
                    Fullscreen
                  </Button>
                </div>

                <div className="flex items-center space-x-2">
                  <div className="flex items-center border border-border rounded-lg p-1">
                    <Button
                      variant={deviceFrame === "desktop" ? "default" : "ghost"}
                      size="sm"
                      onClick={() => setDeviceFrame("desktop")}
                    >
                      <Monitor className="w-4 h-4" />
                    </Button>
                    <Button
                      variant={deviceFrame === "tablet" ? "default" : "ghost"}
                      size="sm"
                      onClick={() => setDeviceFrame("tablet")}
                    >
                      <Tablet className="w-4 h-4" />
                    </Button>
                    <Button
                      variant={deviceFrame === "mobile" ? "default" : "ghost"}
                      size="sm"
                      onClick={() => setDeviceFrame("mobile")}
                    >
                      <Smartphone className="w-4 h-4" />
                    </Button>
                  </div>
                </div>
              </div>
            </div>

            {/* Preview Frame */}
            <div className="flex-1 flex items-center justify-center p-8">
              <div
                className={`
                ${
                  deviceFrame === "mobile"
                    ? "w-80 h-[640px]"
                    : deviceFrame === "tablet"
                      ? "w-[768px] h-[1024px] max-h-[600px]"
                      : "w-full h-full max-w-6xl"
                }
                relative bg-black rounded-lg overflow-hidden shadow-2xl
              `}
              >
                <GamePreview className="w-full h-full" />
                {!isPlaying && (
                  <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                    <Button onClick={() => setIsPlaying(true)}>
                      <Play className="w-6 h-6 mr-2" />
                      Resume Game
                    </Button>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Right Panel */}
          <div className="w-80 border-l border-border bg-card/20 p-4 space-y-6">
            {/* Share Panel */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg font-serif font-bold flex items-center">
                  <Share2 className="w-5 h-5 mr-2" />
                  Share Preview
                </CardTitle>
                <CardDescription>Share your game preview with others</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <label className="text-sm font-medium">Preview URL</label>
                  <div className="flex items-center space-x-2">
                    <div className="flex-1 px-3 py-2 bg-muted rounded-md text-sm font-mono truncate">{previewUrl}</div>
                    <Button variant="outline" size="sm" onClick={handleCopyUrl}>
                      <Copy className="w-4 h-4" />
                    </Button>
                  </div>
                </div>

                <div className="flex space-x-2">
                  <Button variant="outline" className="flex-1 bg-transparent">
                    <QrCode className="w-4 h-4 mr-2" />
                    QR Code
                  </Button>
                  <Button variant="outline" className="flex-1 bg-transparent">
                    <ExternalLink className="w-4 h-4 mr-2" />
                    Open
                  </Button>
                </div>

                <div className="p-3 bg-muted/50 rounded-lg">
                  <p className="text-xs text-muted-foreground">
                    💡 <strong>PWA Tip:</strong> Add to Home Screen for faster reloads on mobile devices
                  </p>
                </div>
              </CardContent>
            </Card>

            {/* Preview Settings */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg font-serif font-bold">Preview Settings</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Auto-refresh</span>
                  <Button
                    variant={autoRefresh ? "default" : "outline"}
                    size="sm"
                    onClick={() => setAutoRefresh(!autoRefresh)}
                  >
                    {autoRefresh ? "On" : "Off"}
                  </Button>
                </div>

                <div className="space-y-2">
                  <span className="text-sm font-medium">Performance</span>
                  <div className="space-y-1 text-xs text-muted-foreground">
                    <div className="flex justify-between">
                      <span>FPS:</span>
                      <span>60</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Memory:</span>
                      <span>45.2 MB</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Load Time:</span>
                      <span>2.3s</span>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Recent Updates */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg font-serif font-bold">Recent Updates</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  <div className="flex items-start space-x-3">
                    <div className="w-2 h-2 bg-green-500 rounded-full mt-2" />
                    <div>
                      <p className="text-sm font-medium">Car physics improved</p>
                      <p className="text-xs text-muted-foreground">2 minutes ago</p>
                    </div>
                  </div>
                  <div className="flex items-start space-x-3">
                    <div className="w-2 h-2 bg-blue-500 rounded-full mt-2" />
                    <div>
                      <p className="text-sm font-medium">New track added</p>
                      <p className="text-xs text-muted-foreground">15 minutes ago</p>
                    </div>
                  </div>
                  <div className="flex items-start space-x-3">
                    <div className="w-2 h-2 bg-purple-500 rounded-full mt-2" />
                    <div>
                      <p className="text-sm font-medium">UI improvements</p>
                      <p className="text-xs text-muted-foreground">1 hour ago</p>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </MainLayout>
  )
}
