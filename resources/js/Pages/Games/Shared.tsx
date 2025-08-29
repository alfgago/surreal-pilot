import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import PlayCanvasPreview from '@/components/engine/PlayCanvasPreview';
import { ArrowLeft, Maximize, Share, Play, ExternalLink } from 'lucide-react';

interface Game {
  id: string;
  title: string;
  description: string;
  engine: 'playcanvas' | 'unreal';
  status: string;
  thumbnail_url?: string;
  published_url?: string;
  preview_url?: string;
  created_at: string;
  updated_at: string;
  metadata: {
    version: string;
    tags: string[];
    play_count: number;
    last_played?: string;
  };
  sharing_settings: {
    allow_embedding: boolean;
    show_controls: boolean;
    show_info: boolean;
  };
}

interface Workspace {
  name: string;
  engine_type: string;
}

interface Props {
  game: Game;
  workspace: Workspace;
}

export default function SharedGame({ game, workspace }: Props) {
  const handleFullscreen = () => {
    const gameContainer = document.getElementById('game-container');
    if (gameContainer) {
      if (gameContainer.requestFullscreen) {
        gameContainer.requestFullscreen();
      }
    }
  };

  const handleShare = () => {
    if (navigator.share) {
      navigator.share({
        title: game.title,
        text: game.description,
        url: window.location.href,
      });
    } else {
      navigator.clipboard.writeText(window.location.href);
      // Could show a toast notification here
    }
  };

  const gameUrl = game.published_url || game.preview_url;

  return (
    <>
      <Head title={`${game.title} - Shared Game`} />
      
      <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
        {/* Header */}
        <div className="bg-white border-b shadow-sm">
          <div className="container mx-auto px-4 py-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-4">
                <div>
                  <h1 className="text-2xl font-bold text-gray-900">{game.title}</h1>
                  <div className="flex items-center space-x-2 mt-1">
                    <Badge variant="outline" className="text-xs">
                      {game.engine}
                    </Badge>
                    <Badge variant="default" className="text-xs">
                      v{game.metadata.version}
                    </Badge>
                    <span className="text-sm text-gray-500">
                      by {workspace.name}
                    </span>
                  </div>
                </div>
              </div>
              
              <div className="flex items-center space-x-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleShare}
                >
                  <Share className="w-4 h-4 mr-2" />
                  Share
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleFullscreen}
                >
                  <Maximize className="w-4 h-4 mr-2" />
                  Fullscreen
                </Button>
                {gameUrl && (
                  <Button
                    size="sm"
                    onClick={() => window.open(gameUrl, '_blank')}
                  >
                    <ExternalLink className="w-4 h-4 mr-2" />
                    Open Game
                  </Button>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Game Container */}
        <div className="container mx-auto px-4 py-6">
          <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {/* Main Game Area */}
            <div className="lg:col-span-3">
              <Card className="overflow-hidden">
                <CardContent className="p-0">
                  <div id="game-container" className="relative">
                    {game.engine === 'playcanvas' && gameUrl ? (
                      <div className="aspect-video bg-black">
                        <iframe
                          src={gameUrl}
                          className="w-full h-full border-0"
                          title={game.title}
                          allowFullScreen
                        />
                      </div>
                    ) : game.engine === 'playcanvas' ? (
                      <div className="aspect-video">
                        <PlayCanvasPreview 
                          workspaceId={parseInt(game.id)} 
                          previewUrl={game.preview_url}
                        />
                      </div>
                    ) : (
                      <div className="aspect-video bg-gray-900 flex items-center justify-center">
                        <div className="text-center text-white">
                          <Play className="w-16 h-16 mx-auto mb-4 text-gray-400" />
                          <h3 className="text-xl font-semibold mb-2">Unreal Engine Game</h3>
                          <p className="text-gray-400 mb-4">
                            This game requires the desktop application to play.
                          </p>
                          <Button variant="outline" className="text-white border-gray-600 hover:bg-gray-800">
                            Download Desktop App
                          </Button>
                        </div>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>

              {/* Game Controls */}
              {game.sharing_settings.show_controls && (
                <Card className="mt-4">
                  <CardHeader>
                    <CardTitle>Controls</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                      <div className="text-center p-3 bg-gray-50 rounded-lg">
                        <div className="font-medium">Move</div>
                        <div className="text-gray-600">WASD / Arrow Keys</div>
                      </div>
                      <div className="text-center p-3 bg-gray-50 rounded-lg">
                        <div className="font-medium">Action</div>
                        <div className="text-gray-600">Space / Click</div>
                      </div>
                      <div className="text-center p-3 bg-gray-50 rounded-lg">
                        <div className="font-medium">Pause</div>
                        <div className="text-gray-600">ESC</div>
                      </div>
                      <div className="text-center p-3 bg-gray-50 rounded-lg">
                        <div className="font-medium">Fullscreen</div>
                        <div className="text-gray-600">F11</div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              )}
            </div>

            {/* Sidebar */}
            <div className="space-y-4">
              {/* Game Info */}
              {game.sharing_settings.show_info && (
                <Card>
                  <CardHeader>
                    <CardTitle>About This Game</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <p className="text-gray-700">{game.description}</p>
                    
                    {game.metadata.tags.length > 0 && (
                      <div>
                        <h4 className="text-sm font-medium mb-2">Tags</h4>
                        <div className="flex flex-wrap gap-2">
                          {game.metadata.tags.map((tag, index) => (
                            <Badge key={index} variant="outline" className="text-xs">
                              {tag}
                            </Badge>
                          ))}
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>
              )}

              {/* Game Stats */}
              <Card>
                <CardHeader>
                  <CardTitle>Game Stats</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between">
                    <span className="text-gray-600">Engine:</span>
                    <span className="font-medium capitalize">{game.engine}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Version:</span>
                    <span className="font-medium">{game.metadata.version}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Plays:</span>
                    <span className="font-medium">{game.metadata.play_count}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Created:</span>
                    <span className="font-medium">{new Date(game.created_at).toLocaleDateString()}</span>
                  </div>
                  {game.metadata.last_played && (
                    <div className="flex justify-between">
                      <span className="text-gray-600">Last Played:</span>
                      <span className="font-medium">{new Date(game.metadata.last_played).toLocaleDateString()}</span>
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Creator Info */}
              <Card>
                <CardHeader>
                  <CardTitle>Created By</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center space-x-3">
                    <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold">
                      {workspace.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                      <p className="font-medium">{workspace.name}</p>
                      <p className="text-sm text-gray-600 capitalize">{workspace.engine_type} Developer</p>
                    </div>
                  </div>
                </CardContent>
              </Card>

              {/* Powered By */}
              <Card>
                <CardContent className="pt-6">
                  <div className="text-center">
                    <p className="text-sm text-gray-600 mb-2">Powered by</p>
                    <div className="font-bold text-lg bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                      SurrealPilot
                    </div>
                    <p className="text-xs text-gray-500 mt-1">AI Game Development Platform</p>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}