import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import GameSharingModal from './GameSharingModal';
import { useGameSharing } from '@/hooks/useGameSharing';

// Mock game data for testing
const mockGameData = {
  id: 1,
  title: "Tower Defense Demo",
  description: "A sample tower defense game created through PlayCanvas chat",
  preview_url: "https://example.com/game-preview",
  published_url: null,
  thumbnail_url: null,
  metadata: {
    interaction_count: 3,
    thinking_history: [],
    game_mechanics: {
      towers: ['basic', 'cannon', 'laser'],
      enemies: ['soldier', 'tank', 'aircraft'],
      waves: 10
    }
  },
  engine_type: 'playcanvas' as const,
  status: 'published',
  version: 'v1.0.0',
  interaction_count: 3,
  thinking_history: [],
  game_mechanics: {},
  sharing_settings: {
    allowEmbedding: true,
    showControls: true,
    showInfo: true,
    expirationDays: 30,
  },
  build_status: 'success' as const,
  last_build_at: new Date().toISOString(),
  workspace: {
    id: 1,
    name: "Test Workspace",
    engine_type: "playcanvas"
  }
};

export function GameSharingModalTest() {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { 
    shareGame, 
    updateSharingSettings, 
    revokeShareLink,
    loading,
    error 
  } = useGameSharing();

  const handleShare = async (options: any) => {
    // Mock implementation for testing
    console.log('Sharing game with options:', options);
    
    // Simulate API call delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    return {
      success: true,
      share_token: 'mock-token-123',
      share_url: 'https://surreal-pilot.local/games/shared/mock-token-123',
      embed_url: 'https://surreal-pilot.local/games/embed/mock-token-123',
      expires_at: new Date(Date.now() + (options.expirationDays || 30) * 24 * 60 * 60 * 1000).toISOString(),
      options,
      snapshot_path: 'shared-games/mock-token-123/snapshots/2024-01-01_12-00-00',
      created_at: new Date().toISOString(),
    };
  };

  const handleUpdateSettings = async (settings: any) => {
    // Mock implementation for testing
    console.log('Updating sharing settings:', settings);
    
    // Simulate API call delay
    await new Promise(resolve => setTimeout(resolve, 500));
    
    return true;
  };

  const handleRevokeLink = async () => {
    // Mock implementation for testing
    console.log('Revoking share link');
    
    // Simulate API call delay
    await new Promise(resolve => setTimeout(resolve, 500));
    
    return true;
  };

  return (
    <div className="p-8">
      <div className="max-w-md mx-auto space-y-4">
        <h1 className="text-2xl font-bold text-center">Game Sharing Modal Test</h1>
        
        <div className="bg-muted p-4 rounded-lg">
          <h3 className="font-semibold mb-2">Mock Game Data:</h3>
          <p className="text-sm"><strong>Title:</strong> {mockGameData.title}</p>
          <p className="text-sm"><strong>Engine:</strong> {mockGameData.engine_type}</p>
          <p className="text-sm"><strong>Status:</strong> {mockGameData.status}</p>
          <p className="text-sm"><strong>Interactions:</strong> {mockGameData.interaction_count}</p>
        </div>

        <Button 
          onClick={() => setIsModalOpen(true)}
          className="w-full"
          size="lg"
        >
          Open Sharing Modal
        </Button>

        {error && (
          <div className="p-3 bg-destructive/10 border border-destructive/20 rounded-md">
            <p className="text-sm text-destructive">{error}</p>
          </div>
        )}

        <GameSharingModal
          game={mockGameData}
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          onShare={handleShare}
          onUpdateSettings={handleUpdateSettings}
          onRevokeLink={handleRevokeLink}
        />
      </div>
    </div>
  );
}

export default GameSharingModalTest;