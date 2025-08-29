"use client"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Separator } from "@/components/ui/separator"
import { Badge } from "@/components/ui/badge"
import { Copy, Share, Globe, Lock, Download, QrCode } from "lucide-react"
import { useState } from "react"

interface Game {
  id: string
  title: string
  engine: "unreal" | "playcanvas"
}

interface ShareOptionsProps {
  game: Game
}

export function ShareOptions({ game }: ShareOptionsProps) {
  const [isPublic, setIsPublic] = useState(false)
  const [allowComments, setAllowComments] = useState(true)
  const [shareUrl] = useState(`https://surrealpilot.com/play/${game.id}`)

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text)
  }

  return (
    <div className="space-y-6">
      <Card className="border-border bg-card">
        <CardHeader>
          <CardTitle className="font-serif font-bold">Share Settings</CardTitle>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="flex items-center justify-between">
            <div className="space-y-1">
              <Label className="text-sm font-medium">Public Access</Label>
              <p className="text-xs text-muted-foreground">Allow anyone with the link to play your game</p>
            </div>
            <Switch checked={isPublic} onCheckedChange={setIsPublic} />
          </div>

          <div className="flex items-center justify-between">
            <div className="space-y-1">
              <Label className="text-sm font-medium">Allow Comments</Label>
              <p className="text-xs text-muted-foreground">Let players leave feedback and comments</p>
            </div>
            <Switch checked={allowComments} onCheckedChange={setAllowComments} />
          </div>

          <Separator />

          <div className="space-y-3">
            <Label className="text-sm font-medium">Share URL</Label>
            <div className="flex items-center space-x-2">
              <Input value={shareUrl} readOnly className="bg-input border-border font-mono text-sm" />
              <Button variant="outline" size="sm" onClick={() => copyToClipboard(shareUrl)}>
                <Copy className="w-3 h-3" />
              </Button>
            </div>
            <div className="flex items-center space-x-2">
              <Badge variant={isPublic ? "default" : "secondary"} className="text-xs">
                {isPublic ? (
                  <>
                    <Globe className="w-3 h-3 mr-1" />
                    Public
                  </>
                ) : (
                  <>
                    <Lock className="w-3 h-3 mr-1" />
                    Private
                  </>
                )}
              </Badge>
              <span className="text-xs text-muted-foreground">
                {isPublic ? "Anyone can access" : "Only you can access"}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card className="border-border bg-card">
        <CardHeader>
          <CardTitle className="font-serif font-bold">Export Options</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {game.engine === "playcanvas" && (
            <Button variant="outline" className="w-full justify-start bg-transparent">
              <Globe className="w-4 h-4 mr-2" />
              Export as Web Build
            </Button>
          )}

          <Button variant="outline" className="w-full justify-start bg-transparent">
            <Download className="w-4 h-4 mr-2" />
            Download Source Files
          </Button>

          <Button variant="outline" className="w-full justify-start bg-transparent">
            <QrCode className="w-4 h-4 mr-2" />
            Generate QR Code
          </Button>

          <Button variant="outline" className="w-full justify-start bg-transparent">
            <Share className="w-4 h-4 mr-2" />
            Share on Social Media
          </Button>
        </CardContent>
      </Card>

      <Card className="border-border bg-card">
        <CardHeader>
          <CardTitle className="font-serif font-bold">Embed Code</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <Label className="text-sm font-medium">HTML Embed</Label>
          <div className="relative">
            <Input
              value={`<iframe src="${shareUrl}" width="800" height="600"></iframe>`}
              readOnly
              className="bg-input border-border font-mono text-xs pr-10"
            />
            <Button
              variant="ghost"
              size="sm"
              className="absolute right-1 top-1/2 -translate-y-1/2 h-6 w-6 p-0"
              onClick={() => copyToClipboard(`<iframe src="${shareUrl}" width="800" height="600"></iframe>`)}
            >
              <Copy className="w-3 h-3" />
            </Button>
          </div>
          <p className="text-xs text-muted-foreground">Embed this game directly into your website or blog</p>
        </CardContent>
      </Card>
    </div>
  )
}
