/**
 * CHAT PAGE - Core AI conversation interface for game development
 *
 * PURPOSE:
 * - Primary workspace interface for AI-assisted game development
 * - Real-time chat with streaming AI responses
 * - Engine-specific features (Unreal connection detection, PlayCanvas preview)
 * - Context-aware assistance with workspace and engine state
 * - Mobile-responsive with collapsible panels
 *
 * INERTIA.JS DATA REQUIREMENTS:
 * - workspace: Workspace - Current workspace data (id, name, engine, settings)
 * - threads: Array<Thread> - Chat conversation threads for this workspace
 * - messages: Array<Message> - Messages for current thread
 * - user: User - Current user info and permissions
 * - engineStatus: Object - Connection status for Unreal Engine
 * - previewUrl: string - PlayCanvas preview URL if available
 *
 * API CALLS NEEDED:
 * - POST /api/chat/messages - Send new message and get AI response
 * - GET /api/chat/threads/{workspaceId} - Fetch threads for workspace
 * - POST /api/chat/threads - Create new thread
 * - GET /api/unreal/status - Check Unreal Engine connection
 * - POST /api/unreal/connect - Initiate Unreal connection
 * - GET /api/playcanvas/preview/{workspaceId} - Get preview URL
 * - PUT /api/workspaces/{id}/settings - Update workspace settings
 *
 * REAL-TIME FEATURES:
 * - WebSocket connection for streaming AI responses
 * - Live Unreal Engine status updates
 * - PlayCanvas preview auto-refresh
 * - Multi-user collaboration (future)
 *
 * NAVIGATION FLOW:
 * - From: /workspaces (workspace selection)
 * - To: /preview, /publish, /multiplayer (via header buttons)
 * - Query params: ?workspace={id}&engine={type}&thread={id}
 */

"use client"

import { useState, useEffect } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { ScrollArea } from "@/components/ui/scroll-area"
import {
  Send,
  Paperclip,
  Settings,
  Code,
  Globe,
  Gamepad2,
  MoreHorizontal,
  Zap,
  Menu,
  X,
  AlertCircle,
  CheckCircle,
  Loader2,
} from "lucide-react"
import { ChatSidebar } from "@/components/chat/chat-sidebar"
import { MessageList } from "@/components/chat/message-list"
import { EngineContext } from "@/components/chat/engine-context"
import { GamePreview } from "@/components/chat/game-preview"
import { ChatSettingsModal } from "@/components/chat/chat-settings-modal"
import { UnrealConnectionModal } from "@/components/chat/unreal-connection-modal"
import { WorkspaceSwitcher } from "@/components/chat/workspace-switcher"
import { Alert, AlertDescription } from "@/components/ui/alert"

interface Message {
  id: string
  content: string
  role: "user" | "assistant"
  timestamp: Date
  isStreaming?: boolean
  // Additional fields for backend integration:
  // threadId: string
  // patches?: Patch[] // Applied code changes
  // attachments?: Attachment[] // File uploads
  // metadata?: MessageMetadata // Engine context, settings used
}

type ConnectionStatus = "disconnected" | "connecting" | "connected" | "error"

export default function ChatPage() {
  // Message state - In real app, this comes from Inertia props
  const [messages, setMessages] = useState<Message[]>([
    {
      id: "1",
      content:
        "Hello! I'm your AI copilot for game development. I can help you with Unreal Engine C++/Blueprints and PlayCanvas development. What would you like to work on today?",
      role: "assistant",
      timestamp: new Date(Date.now() - 60000),
    },
  ])

  // Chat input and streaming state
  const [inputValue, setInputValue] = useState("")
  const [isStreaming, setIsStreaming] = useState(false)

  // Engine and workspace state - Should come from URL params and Inertia props
  const [selectedEngine, setSelectedEngine] = useState<"unreal" | "playcanvas">("unreal")
  const [currentWorkspace, setCurrentWorkspace] = useState("Web Racing Game")
  const [currentEngine, setCurrentEngine] = useState<"unreal" | "playcanvas">("playcanvas")

  // UI state for responsive design
  const [showSettings, setShowSettings] = useState(false)
  const [showMobileSidebar, setShowMobileSidebar] = useState(false)
  const [showMobilePanel, setShowMobilePanel] = useState(false)

  // Unreal Engine specific state
  const [unrealConnectionStatus, setUnrealConnectionStatus] = useState<ConnectionStatus>("disconnected")
  const [showUnrealModal, setShowUnrealModal] = useState(false)

  // PlayCanvas specific state
  const [showPlayCanvasPreview, setShowPlayCanvasPreview] = useState(true)

  // Initialize workspace and engine from URL parameters
  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search)
    const workspace = urlParams.get("workspace")
    const engine = urlParams.get("engine") as "unreal" | "playcanvas"

    if (workspace) {
      // In real app: Inertia.get(`/chat?workspace=${workspace}`)
      setCurrentWorkspace(
        workspace === "1"
          ? "Space Explorer VR"
          : workspace === "2"
            ? "Web Racing Game"
            : workspace === "3"
              ? "Mobile Puzzle Adventure"
              : workspace,
      )
    }
    if (engine) {
      setCurrentEngine(engine)
      setSelectedEngine(engine)
    }
  }, [])

  // Monitor Unreal Engine connection when engine is selected
  useEffect(() => {
    if (selectedEngine === "unreal") {
      checkUnrealConnection()
    }
  }, [selectedEngine])

  const checkUnrealConnection = async () => {
    setUnrealConnectionStatus("connecting")

    try {
      // Real implementation: await fetch('/api/unreal/status')
      await new Promise((resolve) => setTimeout(resolve, 2000))
      const isConnected = Math.random() > 0.7 // Simulate connection check
      setUnrealConnectionStatus(isConnected ? "connected" : "disconnected")
    } catch (error) {
      setUnrealConnectionStatus("error")
    }
  }

  // Dynamic status badge based on engine and connection state
  const getConnectionStatusBadge = () => {
    if (selectedEngine === "playcanvas") {
      return (
        <Badge variant="outline" className="text-xs">
          <CheckCircle className="w-2 h-2 md:w-3 md:h-3 mr-1 text-green-500" />
          Ready
        </Badge>
      )
    }

    switch (unrealConnectionStatus) {
      case "connected":
        return (
          <Badge variant="outline" className="text-xs">
            <CheckCircle className="w-2 h-2 md:w-3 md:h-3 mr-1 text-green-500" />
            Connected
          </Badge>
        )
      case "connecting":
        return (
          <Badge variant="outline" className="text-xs">
            <Loader2 className="w-2 h-2 md:w-3 md:h-3 mr-1 animate-spin" />
            Connecting...
          </Badge>
        )
      case "error":
        return (
          <Badge variant="destructive" className="text-xs">
            <AlertCircle className="w-2 h-2 md:w-3 md:h-3 mr-1" />
            Error
          </Badge>
        )
      default:
        return (
          <Badge variant="secondary" className="text-xs">
            <AlertCircle className="w-2 h-2 md:w-3 md:h-3 mr-1" />
            Disconnected
          </Badge>
        )
    }
  }

  const handleSendMessage = async () => {
    if (!inputValue.trim()) return

    // Block Unreal messages if not connected
    if (selectedEngine === "unreal" && unrealConnectionStatus !== "connected") {
      setShowUnrealModal(true)
      return
    }

    const userMessage: Message = {
      id: Date.now().toString(),
      content: inputValue,
      role: "user",
      timestamp: new Date(),
    }

    setMessages((prev) => [...prev, userMessage])
    setInputValue("")
    setIsStreaming(true)

    // Real implementation would use WebSocket or Server-Sent Events:
    // const response = await fetch('/api/chat/messages', {
    //   method: 'POST',
    //   body: JSON.stringify({
    //     message: inputValue,
    //     workspaceId: currentWorkspace.id,
    //     threadId: currentThread.id,
    //     engine: selectedEngine
    //   })
    // })

    // Simulate streaming AI response
    const assistantMessage: Message = {
      id: (Date.now() + 1).toString(),
      content: "",
      role: "assistant",
      timestamp: new Date(),
      isStreaming: true,
    }

    setMessages((prev) => [...prev, assistantMessage])

    // Simulate engine-specific responses
    const response =
      selectedEngine === "unreal"
        ? "I understand you want help with that. Let me analyze your Unreal Engine project and provide some guidance. I can assist with both C++ and Blueprint implementations."
        : "Great! I'll help you with your PlayCanvas project. Let me update the preview and show you the changes in real-time."

    // Simulate character-by-character streaming
    for (let i = 0; i <= response.length; i++) {
      await new Promise((resolve) => setTimeout(resolve, 30))
      setMessages((prev) =>
        prev.map((msg) => (msg.id === assistantMessage.id ? { ...msg, content: response.slice(0, i) } : msg)),
      )
    }

    setMessages((prev) => prev.map((msg) => (msg.id === assistantMessage.id ? { ...msg, isStreaming: false } : msg)))
    setIsStreaming(false)
  }

  return (
    <div className="flex h-screen bg-background">
      {/* Mobile Sidebar Overlay - Responsive navigation */}
      {showMobileSidebar && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div className="absolute inset-0 bg-black/50" onClick={() => setShowMobileSidebar(false)} />
          <div className="absolute left-0 top-0 h-full w-80 max-w-[80vw]">
            <ChatSidebar onClose={() => setShowMobileSidebar(false)} currentWorkspace={currentWorkspace} />
          </div>
        </div>
      )}

      {/* Desktop Sidebar - Thread management and navigation */}
      <div className="hidden lg:block">
        <ChatSidebar currentWorkspace={currentWorkspace} />
      </div>

      {/* Main Chat Area - Primary conversation interface */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Header - Workspace info and controls */}
        <header className="border-b border-border bg-card/50 backdrop-blur-sm p-3 md:p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2 md:space-x-3">
              {/* Mobile menu trigger */}
              <Button variant="ghost" size="sm" className="lg:hidden" onClick={() => setShowMobileSidebar(true)}>
                <Menu className="w-4 h-4" />
              </Button>

              {/* Desktop workspace switcher */}
              <div className="hidden lg:block">
                <WorkspaceSwitcher currentWorkspace={currentWorkspace} currentEngine={currentEngine} />
              </div>

              {/* Mobile workspace info - Condensed for small screens */}
              <div className="lg:hidden">
                <div className="w-6 h-6 md:w-8 md:h-8 bg-primary rounded-lg flex items-center justify-center">
                  <Gamepad2 className="w-4 h-4 md:w-5 md:h-5 text-primary-foreground" />
                </div>
              </div>

              <div className="lg:hidden">
                <h1 className="font-serif font-bold text-foreground text-sm md:text-base truncate max-w-32">
                  {currentWorkspace}
                </h1>
                {/* Engine and status badges */}
                <div className="flex items-center space-x-1 md:space-x-2">
                  <Badge variant="secondary" className="text-xs">
                    {selectedEngine === "unreal" ? (
                      <>
                        <Code className="w-2 h-2 md:w-3 md:h-3 mr-1" />
                        <span className="hidden sm:inline">Unreal Engine</span>
                        <span className="sm:hidden">Unreal</span>
                      </>
                    ) : (
                      <>
                        <Globe className="w-2 h-2 md:w-3 md:h-3 mr-1" />
                        <span className="hidden sm:inline">PlayCanvas</span>
                        <span className="sm:hidden">PC</span>
                      </>
                    )}
                  </Badge>
                  {getConnectionStatusBadge()}
                  <Badge variant="outline" className="text-xs">
                    <Zap className="w-2 h-2 md:w-3 md:h-3 mr-1" />
                    GPT-4
                  </Badge>
                </div>
              </div>
            </div>
            {/* Header actions */}
            <div className="flex items-center space-x-1 md:space-x-2">
              <Button variant="ghost" size="sm" className="xl:hidden" onClick={() => setShowMobilePanel(true)}>
                <Settings className="w-4 h-4" />
              </Button>
              <Button variant="ghost" size="sm" className="hidden md:inline-flex" onClick={() => setShowSettings(true)}>
                <Settings className="w-4 h-4" />
              </Button>
              <Button variant="ghost" size="sm" className="hidden md:inline-flex">
                <MoreHorizontal className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </header>

        {/* Unreal Connection Warning - Show when Unreal is not connected */}
        {currentEngine === "unreal" && unrealConnectionStatus !== "connected" && (
          <Alert className="m-4 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              Unreal Engine is not connected. Please open your Unreal Editor and install the SurrealPilot plugin to
              start chatting.
              <Button
                variant="link"
                className="p-0 h-auto ml-2 text-amber-700 dark:text-amber-300"
                onClick={() => setShowUnrealModal(true)}
              >
                Learn how →
              </Button>
            </AlertDescription>
          </Alert>
        )}

        <div className="flex-1 flex min-h-0">
          {/* Chat Messages Area */}
          <div className="flex-1 flex flex-col min-w-0">
            <ScrollArea className="flex-1 p-2 md:p-4">
              <MessageList messages={messages} />
            </ScrollArea>

            {/* Message Input - Composer with attachment support */}
            <div className="border-t border-border bg-card/30 p-2 md:p-4">
              <div className="flex items-end space-x-2">
                <div className="flex-1 relative">
                  <Input
                    value={inputValue}
                    onChange={(e) => setInputValue(e.target.value)}
                    placeholder={
                      currentEngine === "unreal" && unrealConnectionStatus !== "connected"
                        ? "Connect Unreal Engine to start chatting..."
                        : "Ask me anything about game development..."
                    }
                    className="bg-input border-border pr-12 min-h-[40px] md:min-h-[44px] resize-none text-sm md:text-base"
                    onKeyDown={(e) => {
                      if (e.key === "Enter" && !e.shiftKey) {
                        e.preventDefault()
                        handleSendMessage()
                      }
                    }}
                    disabled={isStreaming || (currentEngine === "unreal" && unrealConnectionStatus !== "connected")}
                  />
                  {/* Attachment button */}
                  <Button variant="ghost" size="sm" className="absolute right-1 top-1/2 -translate-y-1/2">
                    <Paperclip className="w-3 h-3 md:w-4 md:h-4" />
                  </Button>
                </div>
                {/* Send button */}
                <Button
                  onClick={handleSendMessage}
                  disabled={
                    !inputValue.trim() ||
                    isStreaming ||
                    (currentEngine === "unreal" && unrealConnectionStatus !== "connected")
                  }
                  size="sm"
                  className="h-[40px] md:h-[44px] px-3 md:px-4"
                >
                  <Send className="w-3 h-3 md:w-4 md:h-4" />
                </Button>
              </div>
            </div>
          </div>

          {/* PlayCanvas Preview Panel - Always visible for PlayCanvas workspaces */}
          {currentEngine === "playcanvas" && showPlayCanvasPreview && (
            <div className="hidden lg:block w-96 border-l border-border bg-card/20">
              <div className="p-4 space-y-4">
                <div className="flex items-center justify-between">
                  <h3 className="font-serif font-semibold">Game Preview</h3>
                  <Button variant="ghost" size="sm" onClick={() => setShowPlayCanvasPreview(false)}>
                    <X className="w-4 h-4" />
                  </Button>
                </div>
                <GamePreview />
                <EngineContext engine={currentEngine} />
              </div>
            </div>
          )}

          {/* Desktop Right Panel - Context and tools */}
          {(currentEngine === "unreal" || !showPlayCanvasPreview) && (
            <div className="hidden xl:block w-80 border-l border-border bg-card/20">
              <div className="p-4 space-y-4">
                <EngineContext engine={currentEngine} />
                {currentEngine === "playcanvas" && !showPlayCanvasPreview && (
                  <Button
                    variant="outline"
                    className="w-full bg-transparent"
                    onClick={() => setShowPlayCanvasPreview(true)}
                  >
                    <Globe className="w-4 h-4 mr-2" />
                    Show Game Preview
                  </Button>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Mobile Right Panel Overlay - Context and preview for mobile */}
      {showMobilePanel && (
        <div className="fixed inset-0 z-50 xl:hidden">
          <div className="absolute inset-0 bg-black/50" onClick={() => setShowMobilePanel(false)} />
          <div className="absolute right-0 top-0 h-full w-80 max-w-[90vw] bg-card border-l border-border">
            <div className="p-4">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-serif font-semibold">Context & Preview</h3>
                <Button variant="ghost" size="sm" onClick={() => setShowMobilePanel(false)}>
                  <X className="w-4 h-4" />
                </Button>
              </div>
              <div className="space-y-4">
                <EngineContext engine={currentEngine} />
                {currentEngine === "playcanvas" && <GamePreview />}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modals */}
      <ChatSettingsModal open={showSettings} onOpenChange={setShowSettings} />
      <UnrealConnectionModal
        open={showUnrealModal}
        onOpenChange={setShowUnrealModal}
        onRetryConnection={checkUnrealConnection}
        connectionStatus={unrealConnectionStatus}
      />
    </div>
  )
}
