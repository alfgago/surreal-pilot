import { Head } from '@inertiajs/react';
import PlayCanvasPreview from '@/components/engine/PlayCanvasPreview';

interface Game {
  id: string;
  title: string;
  description: string;
  engine: 'playcanvas' | 'unreal';
  published_url?: string;
  preview_url?: string;
  metadata: {
    version: string;
    tags: string[];
  };
  sharing_settings: {
    allow_embedding: boolean;
    show_controls: boolean;
    show_info: boolean;
  };
}

interface Props {
  game: Game;
}

export default function EmbedGame({ game }: Props) {
  const gameUrl = game.published_url || game.preview_url;

  return (
    <>
      <Head title={`${game.title} - Embedded Game`} />
      
      <div className="w-full h-screen bg-black overflow-hidden">
        {game.engine === 'playcanvas' && gameUrl ? (
          <iframe
            src={gameUrl}
            className="w-full h-full border-0"
            title={game.title}
            allowFullScreen
            allow="gamepad; microphone; camera"
          />
        ) : game.engine === 'playcanvas' ? (
          <div className="w-full h-full">
            <PlayCanvasPreview 
              workspaceId={parseInt(game.id)} 
              previewUrl={game.preview_url}
            />
          </div>
        ) : (
          <div className="w-full h-full flex items-center justify-center bg-gray-900 text-white">
            <div className="text-center">
              <h3 className="text-xl font-semibold mb-2">Unreal Engine Game</h3>
              <p className="text-gray-400">
                This game cannot be embedded and requires the desktop application.
              </p>
            </div>
          </div>
        )}
      </div>
    </>
  );
}