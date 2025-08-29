"use client"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { ChevronDown, Gamepad2, Globe, Zap, Plus } from "lucide-react"
import Link from "next/link"

interface Workspace {
  id: string
  name: string
  engine: "playcanvas" | "unreal"
}

const workspaces: Workspace[] = [
  { id: "1", name: "Space Explorer VR", engine: "unreal" },
  { id: "2", name: "Web Racing Game", engine: "playcanvas" },
  { id: "3", name: "Mobile Puzzle Adventure", engine: "playcanvas" },
]

interface WorkspaceSwitcherProps {
  currentWorkspace?: string
  currentEngine?: string
}

export function WorkspaceSwitcher({
  currentWorkspace = "Web Racing Game",
  currentEngine = "playcanvas",
}: WorkspaceSwitcherProps) {
  const getEngineIcon = (engine: string) => {
    return engine === "playcanvas" ? Globe : Zap
  }

  const getEngineColor = (engine: string) => {
    return engine === "playcanvas" ? "text-blue-500" : "text-purple-500"
  }

  const CurrentEngineIcon = getEngineIcon(currentEngine)

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-auto p-2 justify-start space-x-2 max-w-64">
          <div className="flex items-center space-x-2 min-w-0">
            <CurrentEngineIcon className={`w-4 h-4 ${getEngineColor(currentEngine)} flex-shrink-0`} />
            <div className="min-w-0 text-left">
              <div className="font-medium text-sm truncate">{currentWorkspace}</div>
              <div className="text-xs text-muted-foreground">
                {currentEngine === "playcanvas" ? "PlayCanvas" : "Unreal Engine"}
              </div>
            </div>
          </div>
          <ChevronDown className="w-4 h-4 text-muted-foreground flex-shrink-0" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-64">
        <DropdownMenuLabel>Switch Workspace</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {workspaces.map((workspace) => {
          const EngineIcon = getEngineIcon(workspace.engine)
          const isActive = workspace.name === currentWorkspace
          return (
            <DropdownMenuItem key={workspace.id} className={`cursor-pointer ${isActive ? "bg-accent" : ""}`} asChild>
              <Link href={`/chat?workspace=${workspace.id}&engine=${workspace.engine}`}>
                <div className="flex items-center space-x-2 w-full">
                  <EngineIcon className={`w-4 h-4 ${getEngineColor(workspace.engine)}`} />
                  <div className="flex-1 min-w-0">
                    <div className="font-medium text-sm truncate">{workspace.name}</div>
                    <Badge variant="secondary" className="text-xs">
                      {workspace.engine === "playcanvas" ? "PlayCanvas" : "Unreal"}
                    </Badge>
                  </div>
                </div>
              </Link>
            </DropdownMenuItem>
          )
        })}
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <Link href="/workspaces/new" className="cursor-pointer">
            <Plus className="w-4 h-4 mr-2" />
            Create New Workspace
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href="/workspaces" className="cursor-pointer">
            <Gamepad2 className="w-4 h-4 mr-2" />
            Manage Workspaces
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
