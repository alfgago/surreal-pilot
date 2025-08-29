import { GameCard } from './GameCard';
import { cn } from '@/lib/utils';

interface Game {
  id: number;
  title: string;
  description: string;
  preview_url?: string;
  published_url?: string;
  thumbnail_url?: string;
  metadata?: any;
  created_at: string;
  updated_at: string;
  engine_type: 'unreal' | 'playcanvas';
  is_published: boolean;
  has_preview: boolean;
  has_thumbnail: boolean;
  display_url?: string;
  conversation_id?: number;
  workspace: {
    id: number;
    name: string;
    engine_type: string;
  };
  conversation?: {
    id: number;
    title: string;
    created_at: string;
  };
}

interface GameGridProps {
  games: Game[];
  viewMode: 'grid' | 'list';
}

export function GameGrid({ games, viewMode }: GameGridProps) {
  return (
    <div
      className={cn(
        'gap-6',
        viewMode === 'grid' 
          ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3' 
          : 'flex flex-col space-y-4'
      )}
    >
      {games.map((game) => (
        <GameCard key={game.id} game={game} viewMode={viewMode} />
      ))}
    </div>
  );
}