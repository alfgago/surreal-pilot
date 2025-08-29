import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import PlayCanvasPreview from '@/components/engine/PlayCanvasPreview';
import { ArrowLeft, Maximize, Settings, Share } from 'lucide-react';

interface Game {
  id: string;
  title: string;
  description: string;
  engine: 'playcanvas' | 'unreal';
  status: 'draft' | 'published' | 'archived';
  thumbnail?: string;
  createdAt: string;
  updatedAt: string;
  metadata: {
    version: string;
    tags: string[];
    playCount: number;
    lastPlayed?: string;
  };
}

interface Props {
  game: Game;
}

export default function GamePlay({ game }: Props) {
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
      // Fallback: copy to clipboard
      navigator.clipboard.writeText(window.location.href);
      // You could show a toast here
    }
  };

  return (
    <>
      <Head title={`Play ${game.title}`} />
      
      <div className="min-h-screen bg-black">
        {/* Game Header */}
        <div className="bg-gray-900 border-b border-gray-800 p-4">
          <div className="container mx-auto flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => window.history.back()}
                className="text-white hover:bg-gray-800"
              >
                <ArrowLeft className="w-4 h-4 mr-2" />
                Back
              </Button>
              <div>
                <h1 className="text-xl font-bold text-white">{game.title}</h1>
                <div className="flex items-center space-x-2 mt-1">
                  <Badge variant="outline" className="text-xs">
                    {game.engine}
                  </Badge>
                  <Badge variant={game.status === 'published' ? 'default' : 'secondary'} className="text-xs">
                    {game.status}
                  </Badge>
                  <span className="text-sm text-gray-400">v{game.metadata.version}</span>
                </div>
              </div>
            </div>
            
            <div className="flex items-center space-x-2">
              <Button
                variant="outline"
                size="sm"
                onClick={handleShare}
                className="text-white border-gray-600 hover:bg-gray-800"
              >
                <Share className="w-4 h-4 mr-2" />
                Share
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={handleFullscreen}
                className="text-white border-gray-600 hover:bg-gray-800"
              >
                <Maximize className="w-4 h-4 mr-2" />
                Fullscreen
              </Button>
            </div>
          </div>
        </div>

        {/* Game Container */}
        <div className="container mx-auto p-4">
          <div id="game-container" className="relative">
            {game.engine === 'playcanvas' ? (
              <div className="aspect-video bg-gray-900 rounded-lg overflow-hidden">
                <PlayCanvasPreview 
                  gameId={game.id} 
                  previewUrl={game.published_url || game.preview_url}
                />
              </div>
            ) : (
              <div className="aspect-video bg-gray-900 rounded-lg flex items-center justify-center">
                <div className="text-center text-white">
                  <Settings className="w-16 h-16 mx-auto mb-4 text-gray-400" />
                  <h3 className="text-xl font-semibold mb-2">Unreal Engine Game</h3>
                  <p className="text-gray-400 mb-4">
                    This Unreal Engine game needs to be played in the desktop application.
                  </p>
                  <Button variant="outline" className="text-white border-gray-600 hover:bg-gray-800">
                    Open in Desktop App
                  </Button>
                </div>
              </div>
            )}
          </div>

          {/* Game Info */}
          <div className="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2">
              <Card className="bg-gray-900 border-gray-800">
                <CardHeader>
                  <CardTitle className="text-white">About This Game</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-gray-300">{game.description}</p>
                  
                  {game.metadata.tags.length > 0 && (
                    <div className="mt-4">
                      <h4 className="text-sm font-medium text-white mb-2">Tags</h4>
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
            </div>

            <div className="space-y-4">
              <Card className="bg-gray-900 border-gray-800">
                <CardHeader>
                  <CardTitle className="text-white">Game Stats</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between">
                    <span className="text-gray-400">Engine:</span>
                    <span className="text-white capitalize">{game.engine}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400">Version:</span>
                    <span className="text-white">{game.metadata.version}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400">Plays:</span>
                    <span className="text-white">{game.metadata.playCount}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400">Created:</span>
                    <span className="text-white">{new Date(game.createdAt).toLocaleDateString()}</span>
                  </div>
                  {game.metadata.lastPlayed && (
                    <div className="flex justify-between">
                      <span className="text-gray-400">Last Played:</span>
                      <span className="text-white">{new Date(game.metadata.lastPlayed).toLocaleDateString()}</span>
                    </div>
                  )}
                </CardContent>
              </Card>

              <Card className="bg-gray-900 border-gray-800">
                <CardHeader>
                  <CardTitle className="text-white">Controls</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-2 text-sm">
                    <div className="flex justify-between">
                      <span className="text-gray-400">Move:</span>
                      <span className="text-white">WASD / Arrow Keys</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-400">Action:</span>
                      <span className="text-white">Space / Click</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-400">Pause:</span>
                      <span className="text-white">ESC</span>
                    </div>
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