"use client"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Folder, FileText, ImageIcon, Code, Music, Video, Search, Upload, Download, MoreHorizontal } from "lucide-react"
import { useState } from "react"

interface FileItem {
  id: string
  name: string
  type: "folder" | "file"
  size?: string
  modified: Date
  extension?: string
}

const mockFiles: FileItem[] = [
  { id: "1", name: "Source", type: "folder", modified: new Date(Date.now() - 86400000) },
  { id: "2", name: "Content", type: "folder", modified: new Date(Date.now() - 172800000) },
  { id: "3", name: "Blueprints", type: "folder", modified: new Date(Date.now() - 259200000) },
  {
    id: "4",
    name: "PlayerController.cpp",
    type: "file",
    size: "12.4 KB",
    modified: new Date(Date.now() - 3600000),
    extension: "cpp",
  },
  {
    id: "5",
    name: "GameMode.h",
    type: "file",
    size: "3.2 KB",
    modified: new Date(Date.now() - 7200000),
    extension: "h",
  },
  {
    id: "6",
    name: "MainMenu.uasset",
    type: "file",
    size: "245 KB",
    modified: new Date(Date.now() - 14400000),
    extension: "uasset",
  },
  {
    id: "7",
    name: "SpaceTexture.png",
    type: "file",
    size: "2.1 MB",
    modified: new Date(Date.now() - 28800000),
    extension: "png",
  },
  {
    id: "8",
    name: "BackgroundMusic.wav",
    type: "file",
    size: "8.7 MB",
    modified: new Date(Date.now() - 43200000),
    extension: "wav",
  },
]

interface FileExplorerProps {
  gameId: string
  engine: "unreal" | "playcanvas"
}

export function FileExplorer({ gameId, engine }: FileExplorerProps) {
  const [searchQuery, setSearchQuery] = useState("")
  const [currentPath, setCurrentPath] = useState("/")

  const getFileIcon = (item: FileItem) => {
    if (item.type === "folder") return <Folder className="w-4 h-4 text-primary" />

    switch (item.extension) {
      case "cpp":
      case "h":
      case "js":
      case "ts":
        return <Code className="w-4 h-4 text-blue-500" />
      case "png":
      case "jpg":
      case "jpeg":
        return <ImageIcon className="w-4 h-4 text-green-500" />
      case "wav":
      case "mp3":
        return <Music className="w-4 h-4 text-purple-500" />
      case "mp4":
      case "avi":
        return <Video className="w-4 h-4 text-red-500" />
      default:
        return <FileText className="w-4 h-4 text-muted-foreground" />
    }
  }

  const filteredFiles = mockFiles.filter((file) => file.name.toLowerCase().includes(searchQuery.toLowerCase()))

  return (
    <Card className="border-border bg-card">
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="font-serif font-bold">Project Files</CardTitle>
          <div className="flex items-center space-x-2">
            <Button variant="outline" size="sm">
              <Upload className="w-3 h-3 mr-1" />
              Upload
            </Button>
            <Button variant="outline" size="sm">
              <Download className="w-3 h-3 mr-1" />
              Export
            </Button>
          </div>
        </div>
        <div className="flex items-center space-x-4">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <Input
              placeholder="Search files..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10 bg-input border-border"
            />
          </div>
          <Badge variant="secondary" className="text-xs">
            {filteredFiles.length} items
          </Badge>
        </div>
      </CardHeader>
      <CardContent>
        <div className="mb-4">
          <p className="text-sm text-muted-foreground">
            {currentPath} • {engine === "unreal" ? "Unreal Engine Project" : "PlayCanvas Project"}
          </p>
        </div>

        <ScrollArea className="h-96">
          <div className="space-y-1">
            {filteredFiles.map((item) => (
              <div
                key={item.id}
                className="flex items-center space-x-3 p-2 rounded-lg hover:bg-muted/50 cursor-pointer group"
              >
                {getFileIcon(item)}
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium truncate">{item.name}</p>
                  <div className="flex items-center space-x-2 text-xs text-muted-foreground">
                    <span>{item.modified.toLocaleDateString()}</span>
                    {item.size && (
                      <>
                        <span>•</span>
                        <span>{item.size}</span>
                      </>
                    )}
                  </div>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  className="opacity-0 group-hover:opacity-100 transition-opacity h-6 w-6 p-0"
                >
                  <MoreHorizontal className="w-3 h-3" />
                </Button>
              </div>
            ))}
          </div>
        </ScrollArea>
      </CardContent>
    </Card>
  )
}
