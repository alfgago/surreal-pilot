import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Play, MoreHorizontal, Share, Download, Trash2, Archive, Code, Globe, Clock, ExternalLink } from 'lucide-react';
import { router } from '@inertiajs/react';
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

interface GameCardProps {
  game: Game;
  viewMode: 'grid' | 'list';
}

export function GameCard({ game, viewMode }: GameCardProps) {
  const getStatusColor = (game: Game) => {
    if (game.is_published) {
      return 'bg-green-500/10 text-green-500';
    }
    if (game.has_preview) {
      return 'bg-primary/10 text-primary';
    }
    return 'bg-muted text-muted-foreground';
  };

  const getStatusText = (game: Game) => {
    if (game.is_published) return 'Published';
    if (game.has_preview) return 'Active';
    return 'Draft';
  };

  const getBuildSize = (game: Game) => {
    if (game.metadata?.build_size) {
      return game.metadata.build_size;
    }
    return game.engine_type === 'unreal' ? '~2.5 GB' : '~15 MB';
  };

  const getVersion = (game: Game) => {
    return game.metadata?.version || 'v1.0.0';
  };

  const handleOpenGame = () => {
    router.visit(`/games/${game.id}`);
  };

  const handleShareGame = () => {
    if (game.display_url) {
      navigator.clipboard.writeText(game.display_url);
      // TODO: Add toast notification
    }
  };

  const handleExportGame = () => {
    // TODO: Implement export functionality
    console.log('Export game:', game.id);
  };

  const handleArchiveGame = () => {
    // TODO: Implement archive functionality
    console.log('Archive game:', game.id);
  };

  const handleDeleteGame = () => {
    if (confirm('Are you sure you want to delete this game? This action cannot be undone.')) {
      router.delete(`/games/${game.id}`);
    }
  };

  if (viewMode === 'list') {
    return (
      <Card className="border-border bg-card hover:bg-card/80 transition-colors">
        <CardContent className="p-6">
          <div className="flex items-center space-x-4">
            <div className="relative w-24 h-16 rounded-lg overflow-hidden bg-muted">
              {game.thumbnail_url ? (
                <img 
                  src={game.thumbnail_url} 
                  alt={game.title} 
                  className="w-full h-full object-cover" 
                />
              ) : (
                <div className="w-full h-full flex items-center justify-center">
                  {game.engine_type === 'unreal' ? (
                    <Code className="w-6 h-6 text-muted-foreground" />
                  ) : (
                    <Globe className="w-6 h-6 text-muted-foreground" />
                  )}
                </div>
              )}
            </div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center space-x-2 mb-1">
                <h3 className="font-serif font-bold text-foreground truncate">{game.title}</h3>
                <Badge variant="secondary" className="text-xs">
                  {game.engine_type === 'unreal' ? (
                    <>
                      <Code className="w-3 h-3 mr-1" />
                      Unreal
                    </>
                  ) : (
                    <>
                      <Globe className="w-3 h-3 mr-1" />
                      PlayCanvas
                    </>
                  )}
                </Badge>
                <Badge className={cn('text-xs', getStatusColor(game))}>
                  {getStatusText(game)}
                </Badge>
              </div>
              <p className="text-sm text-muted-foreground truncate mb-2">{game.description}</p>
              <div className="flex items-center space-x-4 text-xs text-muted-foreground">
                <div className="flex items-center">
                  <Clock className="w-3 h-3 mr-1" />
                  {new Date(game.updated_at).toLocaleDateString()}
                </div>
                <span>{getVersion(game)}</span>
                <span>{getBuildSize(game)}</span>
                <span className="text-muted-foreground">
                  Workspace: {game.workspace.name}
                </span>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <Button variant="outline" size="sm" onClick={handleOpenGame}>
                <Play className="w-3 h-3 mr-1" />
                Open
              </Button>
              {game.display_url && (
                <Button variant="outline" size="sm" asChild>
                  <a href={game.display_url} target="_blank" rel="noopener noreferrer">
                    <ExternalLink className="w-3 h-3 mr-1" />
                    View
                  </a>
                </Button>
              )}
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="sm">
                    <MoreHorizontal className="w-4 h-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onClick={handleShareGame}>
                    <Share className="w-4 h-4 mr-2" />
                    Share
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={handleExportGame}>
                    <Download className="w-4 h-4 mr-2" />
                    Export
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={handleArchiveGame}>
                    <Archive className="w-4 h-4 mr-2" />
                    Archive
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={handleDeleteGame} className="text-destructive">
                    <Trash2 className="w-4 h-4 mr-2" />
                    Delete
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="border-border bg-card hover:bg-card/80 transition-all duration-200 hover:scale-[1.02] group">
      <CardHeader className="p-0">
        <div className="relative aspect-video rounded-t-lg overflow-hidden bg-muted">
          {game.thumbnail_url ? (
            <img 
              src={game.thumbnail_url} 
              alt={game.title} 
              className="w-full h-full object-cover" 
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center">
              {game.engine_type === 'unreal' ? (
                <Code className="w-12 h-12 text-muted-foreground" />
              ) : (
                <Globe className="w-12 h-12 text-muted-foreground" />
              )}
            </div>
          )}
          <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
            <Button
              variant="secondary"
              size="sm"
              className="opacity-0 group-hover:opacity-100 transition-opacity"
              onClick={handleOpenGame}
            >
              <Play className="w-4 h-4 mr-1" />
              Open
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent className="p-4">
        <div className="flex items-start justify-between mb-2">
          <h3 className="font-serif font-bold text-foreground truncate flex-1">{game.title}</h3>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                <MoreHorizontal className="w-4 h-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={handleShareGame}>
                <Share className="w-4 h-4 mr-2" />
                Share
              </DropdownMenuItem>
              <DropdownMenuItem onClick={handleExportGame}>
                <Download className="w-4 h-4 mr-2" />
                Export
              </DropdownMenuItem>
              <DropdownMenuItem onClick={handleArchiveGame}>
                <Archive className="w-4 h-4 mr-2" />
                Archive
              </DropdownMenuItem>
              <DropdownMenuItem onClick={handleDeleteGame} className="text-destructive">
                <Trash2 className="w-4 h-4 mr-2" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
        <p className="text-sm text-muted-foreground mb-3 line-clamp-2">{game.description}</p>
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center space-x-2">
            <Badge variant="secondary" className="text-xs">
              {game.engine_type === 'unreal' ? (
                <>
                  <Code className="w-3 h-3 mr-1" />
                  Unreal
                </>
              ) : (
                <>
                  <Globe className="w-3 h-3 mr-1" />
                  PlayCanvas
                </>
              )}
            </Badge>
            <Badge className={cn('text-xs', getStatusColor(game))}>
              {getStatusText(game)}
            </Badge>
          </div>
          {game.display_url && (
            <Button variant="outline" size="sm" asChild>
              <a href={game.display_url} target="_blank" rel="noopener noreferrer">
                <ExternalLink className="w-3 h-3" />
              </a>
            </Button>
          )}
        </div>
        <div className="flex items-center justify-between text-xs text-muted-foreground">
          <div className="flex items-center">
            <Clock className="w-3 h-3 mr-1" />
            {new Date(game.updated_at).toLocaleDateString()}
          </div>
          <div className="flex items-center space-x-2">
            <span>{getVersion(game)}</span>
            <span>•</span>
            <span>{getBuildSize(game)}</span>
          </div>
        </div>
        <div className="mt-2 text-xs text-muted-foreground">
          Workspace: {game.workspace.name}
        </div>
      </CardContent>
    </Card>
  );
}