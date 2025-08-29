"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group"
import { MainLayout } from "@/components/layout/main-layout"
import {
  Upload,
  Globe,
  Lock,
  ExternalLink,
  Copy,
  QrCode,
  CheckCircle,
  Clock,
  Trash2,
  RotateCcw,
  Download,
} from "lucide-react"

interface PublishedVersion {
  id: string
  version: string
  date: Date
  size: string
  url: string
  visibility: "public" | "private"
  isCurrent: boolean
  downloads: number
}

const publishedVersions: PublishedVersion[] = [
  {
    id: "v12",
    version: "v1.2.0",
    date: new Date(Date.now() - 3600000),
    size: "2.8 MB",
    url: "https://games.playcanvas.com/web-racing-v12",
    visibility: "public",
    isCurrent: true,
    downloads: 1247,
  },
  {
    id: "v11",
    version: "v1.1.0",
    date: new Date(Date.now() - 86400000),
    size: "2.6 MB",
    url: "https://games.playcanvas.com/web-racing-v11",
    visibility: "public",
    isCurrent: false,
    downloads: 892,
  },
  {
    id: "v10",
    version: "v1.0.0",
    date: new Date(Date.now() - 172800000),
    size: "2.4 MB",
    url: "https://games.playcanvas.com/web-racing-v10",
    visibility: "private",
    isCurrent: false,
    downloads: 0,
  },
]

export default function PublishPage() {
  const [isPublishing, setIsPublishing] = useState(false)
  const [publishForm, setPublishForm] = useState({
    version: "v1.3.0",
    visibility: "public",
    customDomain: "",
    releaseNotes: "",
  })

  const handlePublish = async () => {
    setIsPublishing(true)
    // Simulate publishing process
    await new Promise((resolve) => setTimeout(resolve, 3000))
    setIsPublishing(false)
    // In real app, add new version to list and show success
  }

  const handleCopyUrl = (url: string) => {
    navigator.clipboard.writeText(url)
    // In real app, show toast notification
  }

  return (
    <MainLayout currentWorkspace="Web Racing Game" currentEngine="playcanvas">
      <div className="p-6 max-w-6xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-serif font-black text-foreground mb-2">Publish Game</h1>
          <p className="text-muted-foreground">Create versioned, public builds ready to share with the world</p>
        </div>

        <div className="grid lg:grid-cols-2 gap-8">
          {/* Publish Form */}
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle className="text-xl font-serif font-bold flex items-center">
                  <Upload className="w-5 h-5 mr-2" />
                  New Publication
                </CardTitle>
                <CardDescription>Create a new versioned build of your game</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-2">
                  <Label htmlFor="version">Version Name/Number</Label>
                  <Input
                    id="version"
                    value={publishForm.version}
                    onChange={(e) => setPublishForm((prev) => ({ ...prev, version: e.target.value }))}
                    placeholder="e.g., v1.3.0, Beta 2, Release Candidate"
                  />
                </div>

                <div className="space-y-3">
                  <Label>Visibility</Label>
                  <RadioGroup
                    value={publishForm.visibility}
                    onValueChange={(value) => setPublishForm((prev) => ({ ...prev, visibility: value }))}
                  >
                    <div className="flex items-center space-x-2">
                      <RadioGroupItem value="public" id="public" />
                      <Label htmlFor="public" className="flex items-center space-x-2 cursor-pointer">
                        <Globe className="w-4 h-4" />
                        <span>Public - Anyone can access</span>
                      </Label>
                    </div>
                    <div className="flex items-center space-x-2">
                      <RadioGroupItem value="private" id="private" />
                      <Label htmlFor="private" className="flex items-center space-x-2 cursor-pointer">
                        <Lock className="w-4 h-4" />
                        <span>Private - Link only</span>
                      </Label>
                    </div>
                  </RadioGroup>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="domain">Custom Domain (Optional)</Label>
                  <Input
                    id="domain"
                    value={publishForm.customDomain}
                    onChange={(e) => setPublishForm((prev) => ({ ...prev, customDomain: e.target.value }))}
                    placeholder="e.g., racing.yourdomain.com"
                  />
                  <p className="text-xs text-muted-foreground">Configure custom domains in your account settings</p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="notes">Release Notes</Label>
                  <Textarea
                    id="notes"
                    value={publishForm.releaseNotes}
                    onChange={(e) => setPublishForm((prev) => ({ ...prev, releaseNotes: e.target.value }))}
                    placeholder="What's new in this version?"
                    rows={4}
                  />
                </div>

                <Button
                  onClick={handlePublish}
                  disabled={isPublishing || !publishForm.version}
                  className="w-full"
                  size="lg"
                >
                  {isPublishing ? (
                    <>
                      <Clock className="w-4 h-4 mr-2 animate-spin" />
                      Publishing...
                    </>
                  ) : (
                    <>
                      <Upload className="w-4 h-4 mr-2" />
                      Publish Game
                    </>
                  )}
                </Button>

                {isPublishing && (
                  <div className="space-y-2">
                    <div className="text-sm text-muted-foreground">Publishing progress:</div>
                    <div className="space-y-1 text-xs">
                      <div className="flex items-center space-x-2">
                        <CheckCircle className="w-3 h-3 text-green-500" />
                        <span>Building game...</span>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Clock className="w-3 h-3 animate-spin" />
                        <span>Uploading assets...</span>
                      </div>
                      <div className="flex items-center space-x-2 text-muted-foreground">
                        <div className="w-3 h-3 rounded-full border border-muted-foreground" />
                        <span>Invalidating cache...</span>
                      </div>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>

          {/* Published Versions */}
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle className="text-xl font-serif font-bold">Published Versions</CardTitle>
                <CardDescription>Manage your published game versions</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {publishedVersions.map((version) => (
                    <div key={version.id} className="border border-border rounded-lg p-4">
                      <div className="flex items-start justify-between mb-3">
                        <div>
                          <div className="flex items-center space-x-2">
                            <h3 className="font-semibold">{version.version}</h3>
                            {version.isCurrent && (
                              <Badge variant="default" className="text-xs">
                                Current
                              </Badge>
                            )}
                            <Badge
                              variant={version.visibility === "public" ? "secondary" : "outline"}
                              className="text-xs"
                            >
                              {version.visibility === "public" ? (
                                <>
                                  <Globe className="w-2 h-2 mr-1" />
                                  Public
                                </>
                              ) : (
                                <>
                                  <Lock className="w-2 h-2 mr-1" />
                                  Private
                                </>
                              )}
                            </Badge>
                          </div>
                          <div className="text-sm text-muted-foreground mt-1">
                            {version.date.toLocaleDateString()} • {version.size}
                            {version.visibility === "public" && (
                              <span> • {version.downloads.toLocaleString()} downloads</span>
                            )}
                          </div>
                        </div>
                        <div className="flex items-center space-x-1">
                          <Button variant="ghost" size="sm" onClick={() => handleCopyUrl(version.url)}>
                            <Copy className="w-3 h-3" />
                          </Button>
                          <Button variant="ghost" size="sm">
                            <QrCode className="w-3 h-3" />
                          </Button>
                          <Button variant="ghost" size="sm">
                            <ExternalLink className="w-3 h-3" />
                          </Button>
                        </div>
                      </div>

                      <div className="flex items-center space-x-2 text-xs">
                        <Button variant="outline" size="sm" disabled={version.isCurrent}>
                          {version.isCurrent ? "Current" : "Set Current"}
                        </Button>
                        <Button variant="outline" size="sm">
                          <RotateCcw className="w-3 h-3 mr-1" />
                          Rollback
                        </Button>
                        <Button variant="outline" size="sm">
                          <Download className="w-3 h-3 mr-1" />
                          Download
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          className="text-destructive hover:text-destructive bg-transparent"
                        >
                          <Trash2 className="w-3 h-3 mr-1" />
                          Delete
                        </Button>
                      </div>

                      <div className="mt-3 p-2 bg-muted/50 rounded text-xs font-mono truncate">{version.url}</div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </MainLayout>
  )
}
