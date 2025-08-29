"use client"

import { Button } from "@/components/ui/button"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Separator } from "@/components/ui/separator"
import { Badge } from "@/components/ui/badge"
import { Plus, Search, Settings, User, LogOut, Gamepad2, Clock, Trash2, X } from "lucide-react"
import { Input } from "@/components/ui/input"

interface Conversation {
  id: string
  title: string
  lastMessage: string
  timestamp: Date
  engine: "unreal" | "playcanvas"
}

const conversations: Conversation[] = [
  {
    id: "1",
    title: "Car Physics Setup",
    lastMessage: "How do I implement realistic car physics?",
    timestamp: new Date(Date.now() - 300000),
    engine: "playcanvas",
  },
  {
    id: "2",
    title: "Multiplayer Networking",
    lastMessage: "Setting up real-time multiplayer racing",
    timestamp: new Date(Date.now() - 3600000),
    engine: "playcanvas",
  },
  {
    id: "3",
    title: "Track Generation",
    lastMessage: "Procedural race track generation",
    timestamp: new Date(Date.now() - 86400000),
    engine: "playcanvas",
  },
]

interface ChatSidebarProps {
  onClose?: () => void // Added optional onClose prop for mobile
  currentWorkspace?: string // Added workspace context
}

export function ChatSidebar({ onClose, currentWorkspace = "Web Racing Game" }: ChatSidebarProps) {
  return (
    <div className="w-80 border-r border-border bg-sidebar flex flex-col h-full">
      {/* Header - Added close button for mobile */}
      <div className="p-4 border-b border-sidebar-border">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-2">
            <div className="w-8 h-8 bg-sidebar-primary rounded-lg flex items-center justify-center">
              <Gamepad2 className="w-5 h-5 text-sidebar-primary-foreground" />
            </div>
            <span className="font-serif font-black text-sidebar-foreground">SurrealPilot</span>
          </div>
          <div className="flex items-center space-x-1">
            <Button variant="ghost" size="sm" className="text-sidebar-foreground hover:bg-sidebar-accent">
              <Settings className="w-4 h-4" />
            </Button>
            {onClose && (
              <Button
                variant="ghost"
                size="sm"
                className="text-sidebar-foreground hover:bg-sidebar-accent lg:hidden"
                onClick={onClose}
              >
                <X className="w-4 h-4" />
              </Button>
            )}
          </div>
        </div>

        <Button className="w-full bg-sidebar-primary hover:bg-sidebar-primary/90 text-sidebar-primary-foreground">
          <Plus className="w-4 h-4 mr-2" />
          New Chat
        </Button>
      </div>

      {/* Search */}
      <div className="p-4 border-b border-sidebar-border">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <Input
            placeholder="Search conversations..."
            className="pl-10 bg-sidebar-accent border-sidebar-border text-sidebar-foreground placeholder:text-muted-foreground"
          />
        </div>
      </div>

      {/* Conversations */}
      <ScrollArea className="flex-1">
        <div className="p-2">
          <div className="text-xs font-medium text-muted-foreground mb-2 px-2">{currentWorkspace} Conversations</div>
          <div className="space-y-1">
            {conversations.map((conversation) => (
              <div
                key={conversation.id}
                className="p-3 rounded-lg hover:bg-sidebar-accent cursor-pointer transition-colors group"
                onClick={onClose} // Close sidebar when conversation is selected on mobile
              >
                <div className="flex items-start justify-between mb-2">
                  <h3 className="font-medium text-sidebar-foreground text-sm truncate flex-1">{conversation.title}</h3>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="opacity-0 group-hover:opacity-100 transition-opacity h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                    onClick={(e) => e.stopPropagation()} // Prevent closing sidebar when deleting
                  >
                    <Trash2 className="w-3 h-3" />
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground truncate mb-2">{conversation.lastMessage}</p>
                <div className="flex items-center justify-between">
                  <Badge variant="secondary" className="text-xs">
                    {conversation.engine === "unreal" ? "Unreal" : "PlayCanvas"}
                  </Badge>
                  <div className="flex items-center text-xs text-muted-foreground">
                    <Clock className="w-3 h-3 mr-1" />
                    <span className="hidden sm:inline">{conversation.timestamp.toLocaleDateString()}</span>
                    <span className="sm:hidden">
                      {conversation.timestamp.toLocaleDateString("en-US", { month: "short", day: "numeric" })}
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </ScrollArea>

      {/* Footer */}
      <div className="p-4 border-t border-sidebar-border">
        <div className="space-y-2">
          <div className="flex items-center justify-between text-sm">
            <span className="text-muted-foreground">Credits</span>
            <Badge variant="outline" className="text-xs">
              1,247 / 2,000
            </Badge>
          </div>
          <Separator className="bg-sidebar-border" />
          <div className="flex items-center space-x-2">
            <div className="w-8 h-8 bg-sidebar-accent rounded-full flex items-center justify-center">
              <User className="w-4 h-4 text-sidebar-foreground" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-sidebar-foreground truncate">John Doe</p>
              <p className="text-xs text-muted-foreground truncate">john@example.com</p>
            </div>
            <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-sidebar-foreground">
              <LogOut className="w-4 h-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
