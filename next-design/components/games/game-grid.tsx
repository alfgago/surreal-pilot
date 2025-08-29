import { GameCard } from "./game-card"
import { cn } from "@/lib/utils"

interface Game {
  id: string
  title: string
  description: string
  engine: "unreal" | "playcanvas"
  thumbnail: string
  lastModified: Date
  status: "active" | "archived" | "published"
  buildSize: string
  version: string
}

interface GameGridProps {
  games: Game[]
  viewMode: "grid" | "list"
}

export function GameGrid({ games, viewMode }: GameGridProps) {
  return (
    <div
      className={cn(
        "gap-6",
        viewMode === "grid" ? "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3" : "flex flex-col space-y-4",
      )}
    >
      {games.map((game) => (
        <GameCard key={game.id} game={game} viewMode={viewMode} />
      ))}
    </div>
  )
}
