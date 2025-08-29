import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { User, Bot, Copy, ThumbsUp, ThumbsDown, RefreshCw } from "lucide-react"
import { cn } from "@/lib/utils"

interface Message {
  id: string
  content: string
  role: "user" | "assistant"
  timestamp: Date
  isStreaming?: boolean
}

interface MessageListProps {
  messages: Message[]
}

export function MessageList({ messages }: MessageListProps) {
  return (
    <div className="space-y-6">
      {messages.map((message) => (
        <div key={message.id} className={cn("flex gap-4", message.role === "user" ? "justify-end" : "justify-start")}>
          {message.role === "assistant" && (
            <Avatar className="w-8 h-8 bg-primary">
              <AvatarFallback className="bg-primary text-primary-foreground">
                <Bot className="w-4 h-4" />
              </AvatarFallback>
            </Avatar>
          )}

          <div className={cn("max-w-[70%] space-y-2", message.role === "user" ? "items-end" : "items-start")}>
            <Card
              className={cn(
                "border-border",
                message.role === "user" ? "bg-primary text-primary-foreground ml-auto" : "bg-card",
              )}
            >
              <CardContent className="p-4">
                <div className="prose prose-sm max-w-none">
                  <p
                    className={cn(
                      "text-sm leading-relaxed",
                      message.role === "user" ? "text-primary-foreground" : "text-card-foreground",
                    )}
                  >
                    {message.content}
                    {message.isStreaming && <span className="inline-block w-2 h-4 bg-current ml-1 animate-pulse" />}
                  </p>
                </div>
              </CardContent>
            </Card>

            <div
              className={cn(
                "flex items-center gap-2 text-xs text-muted-foreground",
                message.role === "user" ? "justify-end" : "justify-start",
              )}
            >
              <span>{message.timestamp.toLocaleTimeString()}</span>

              {message.role === "assistant" && !message.isStreaming && (
                <div className="flex items-center gap-1">
                  <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                    <Copy className="w-3 h-3" />
                  </Button>
                  <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                    <ThumbsUp className="w-3 h-3" />
                  </Button>
                  <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                    <ThumbsDown className="w-3 h-3" />
                  </Button>
                  <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                    <RefreshCw className="w-3 h-3" />
                  </Button>
                </div>
              )}
            </div>
          </div>

          {message.role === "user" && (
            <Avatar className="w-8 h-8 bg-secondary">
              <AvatarFallback className="bg-secondary text-secondary-foreground">
                <User className="w-4 h-4" />
              </AvatarFallback>
            </Avatar>
          )}
        </div>
      ))}
    </div>
  )
}
