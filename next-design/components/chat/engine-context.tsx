import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import { Code, Globe, FileText, Folder, Settings, Play, Database, Layers, Cpu } from "lucide-react"

interface EngineContextProps {
  engine: "unreal" | "playcanvas"
}

export function EngineContext({ engine }: EngineContextProps) {
  if (engine === "unreal") {
    return (
      <Card className="border-border bg-card">
        <CardHeader className="pb-3">
          <div className="flex items-center space-x-2">
            <Code className="w-5 h-5 text-primary" />
            <CardTitle className="text-sm font-serif font-bold">Unreal Engine Context</CardTitle>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-muted-foreground">Current Project</span>
              <Badge variant="secondary" className="text-xs">
                Active
              </Badge>
            </div>
            <p className="text-sm font-medium">MyAwesomeGame</p>
            <p className="text-xs text-muted-foreground">UE 5.3 • C++ & Blueprints</p>
          </div>

          <Separator />

          <div>
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-muted-foreground">Recent Files</span>
              <Button variant="ghost" size="sm" className="h-6 text-xs">
                View All
              </Button>
            </div>
            <div className="space-y-2">
              <div className="flex items-center space-x-2 text-xs">
                <FileText className="w-3 h-3 text-muted-foreground" />
                <span className="flex-1 truncate">PlayerController.cpp</span>
              </div>
              <div className="flex items-center space-x-2 text-xs">
                <Layers className="w-3 h-3 text-muted-foreground" />
                <span className="flex-1 truncate">AI_EnemyBehavior.uasset</span>
              </div>
              <div className="flex items-center space-x-2 text-xs">
                <Database className="w-3 h-3 text-muted-foreground" />
                <span className="flex-1 truncate">GameInstance.h</span>
              </div>
            </div>
          </div>

          <Separator />

          <div>
            <span className="text-xs font-medium text-muted-foreground">Build Status</span>
            <div className="flex items-center space-x-2 mt-1">
              <div className="w-2 h-2 bg-primary rounded-full"></div>
              <span className="text-xs">Compiled Successfully</span>
            </div>
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className="border-border bg-card">
      <CardHeader className="pb-3">
        <div className="flex items-center space-x-2">
          <Globe className="w-5 h-5 text-primary" />
          <CardTitle className="text-sm font-serif font-bold">PlayCanvas Context</CardTitle>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-muted-foreground">Current Scene</span>
            <Badge variant="secondary" className="text-xs">
              Live
            </Badge>
          </div>
          <p className="text-sm font-medium">MainGameScene</p>
          <p className="text-xs text-muted-foreground">WebGL • JavaScript</p>
        </div>

        <Separator />

        <div>
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-muted-foreground">Scene Hierarchy</span>
            <Button variant="ghost" size="sm" className="h-6 text-xs">
              <Settings className="w-3 h-3" />
            </Button>
          </div>
          <div className="space-y-2">
            <div className="flex items-center space-x-2 text-xs">
              <Folder className="w-3 h-3 text-muted-foreground" />
              <span className="flex-1 truncate">Root</span>
            </div>
            <div className="flex items-center space-x-2 text-xs pl-4">
              <Cpu className="w-3 h-3 text-muted-foreground" />
              <span className="flex-1 truncate">Player</span>
            </div>
            <div className="flex items-center space-x-2 text-xs pl-4">
              <Layers className="w-3 h-3 text-muted-foreground" />
              <span className="flex-1 truncate">Environment</span>
            </div>
            <div className="flex items-center space-x-2 text-xs pl-4">
              <Play className="w-3 h-3 text-muted-foreground" />
              <span className="flex-1 truncate">UI Canvas</span>
            </div>
          </div>
        </div>

        <Separator />

        <div>
          <span className="text-xs font-medium text-muted-foreground">Performance</span>
          <div className="space-y-1 mt-1">
            <div className="flex justify-between text-xs">
              <span>FPS</span>
              <span className="text-primary">60</span>
            </div>
            <div className="flex justify-between text-xs">
              <span>Draw Calls</span>
              <span>24</span>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
