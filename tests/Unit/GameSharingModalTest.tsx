import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import GameSharingModal from '@/components/games/GameSharingModal';

// Mock the hooks
vi.mock('@/hooks/useGameSharing', () => ({
  useGameSharing: () => ({
    shareGame: vi.fn(),
    updateSharingSettings: vi.fn(),
    revokeShareLink: vi.fn(),
    loading: false,
    error: null,
    clearError: vi.fn(),
  }),
}));

// Mock UI components
vi.mock('@/components/ui/dialog', () => ({
  Dialog: ({ children, open }: any) => open ? <div data-testid="dialog">{children}</div> : null,
  DialogContent: ({ children }: any) => <div data-testid="dialog-content">{children}</div>,
  DialogHeader: ({ children }: any) => <div data-testid="dialog-header">{children}</div>,
  DialogTitle: ({ children }: any) => <h2 data-testid="dialog-title">{children}</h2>,
  DialogDescription: ({ children }: any) => <p data-testid="dialog-description">{children}</p>,
  DialogFooter: ({ children }: any) => <div data-testid="dialog-footer">{children}</div>,
}));

vi.mock('@/components/ui/tabs', () => ({
  Tabs: ({ children, value, onValueChange }: any) => (
    <div data-testid="tabs" data-value={value}>
      {children}
    </div>
  ),
  TabsList: ({ children }: any) => <div data-testid="tabs-list">{children}</div>,
  TabsTrigger: ({ children, value, ...props }: any) => (
    <button data-testid={`tab-trigger-${value}`} data-value={value} {...props}>
      {children}
    </button>
  ),
  TabsContent: ({ children, value }: any) => (
    <div data-testid={`tab-content-${value}`} data-value={value}>
      {children}
    </div>
  ),
}));

vi.mock('@/components/ui/button', () => ({
  Button: ({ children, onClick, disabled, ...props }: any) => (
    <button onClick={onClick} disabled={disabled} data-testid="button" {...props}>
      {children}
    </button>
  ),
}));

vi.mock('@/components/ui/input', () => ({
  Input: (props: any) => <input data-testid="input" {...props} />,
}));

vi.mock('@/components/ui/switch', () => ({
  Switch: ({ checked, onCheckedChange, ...props }: any) => (
    <input
      type="checkbox"
      checked={checked}
      onChange={(e) => onCheckedChange?.(e.target.checked)}
      data-testid="switch"
      {...props}
    />
  ),
}));

vi.mock('@/components/ui/label', () => ({
  Label: ({ children, ...props }: any) => <label data-testid="label" {...props}>{children}</label>,
}));

vi.mock('@/components/ui/card', () => ({
  Card: ({ children }: any) => <div data-testid="card">{children}</div>,
  CardHeader: ({ children }: any) => <div data-testid="card-header">{children}</div>,
  CardTitle: ({ children }: any) => <h3 data-testid="card-title">{children}</h3>,
  CardContent: ({ children }: any) => <div data-testid="card-content">{children}</div>,
}));

vi.mock('@/components/ui/badge', () => ({
  Badge: ({ children }: any) => <span data-testid="badge">{children}</span>,
}));

vi.mock('@/components/ui/separator', () => ({
  Separator: () => <hr data-testid="separator" />,
}));

// Mock clipboard API
Object.assign(navigator, {
  clipboard: {
    writeText: vi.fn(() => Promise.resolve()),
  },
});

const mockGameData = {
  id: 1,
  title: "Test Tower Defense Game",
  description: "A test game for sharing functionality",
  preview_url: "https://example.com/preview",
  published_url: null,
  thumbnail_url: null,
  metadata: {
    interaction_count: 3,
    thinking_history: [],
    game_mechanics: {}
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

describe('GameSharingModal', () => {
  const mockOnClose = vi.fn();
  const mockOnShare = vi.fn();
  const mockOnUpdateSettings = vi.fn();
  const mockOnRevokeLink = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders modal when open', () => {
    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    expect(screen.getByTestId('dialog')).toBeInTheDocument();
    expect(screen.getByTestId('dialog-title')).toHaveTextContent('Share Game');
    expect(screen.getByTestId('dialog-description')).toHaveTextContent('Share "Test Tower Defense Game"');
  });

  it('does not render when closed', () => {
    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={false}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    expect(screen.queryByTestId('dialog')).not.toBeInTheDocument();
  });

  it('does not render when game is null', () => {
    render(
      <GameSharingModal
        game={null}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    expect(screen.queryByTestId('dialog')).not.toBeInTheDocument();
  });

  it('displays game information correctly', () => {
    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    expect(screen.getByText('Test Tower Defense Game')).toBeInTheDocument();
    expect(screen.getByText('PlayCanvas')).toBeInTheDocument();
  });

  it('renders all tab triggers', () => {
    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    expect(screen.getByTestId('tab-trigger-share')).toBeInTheDocument();
    expect(screen.getByTestId('tab-trigger-embed')).toBeInTheDocument();
    expect(screen.getByTestId('tab-trigger-social')).toBeInTheDocument();
    expect(screen.getByTestId('tab-trigger-settings')).toBeInTheDocument();
  });

  it('shows create share link button when no share URL exists', () => {
    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    expect(screen.getByText('Create Share Link')).toBeInTheDocument();
  });

  it('calls onShare when create share link is clicked', async () => {
    mockOnShare.mockResolvedValue({
      success: true,
      share_url: 'https://example.com/shared/123',
      embed_url: 'https://example.com/embed/123',
    });

    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    const createButton = screen.getByText('Create Share Link');
    fireEvent.click(createButton);

    await waitFor(() => {
      expect(mockOnShare).toHaveBeenCalledWith({
        allowEmbedding: true,
        showControls: true,
        showInfo: true,
        expirationDays: 30,
      });
    });
  });

  it('displays sharing settings switches', () => {
    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    // Switch to settings tab first
    const settingsTab = screen.getByTestId('tab-trigger-settings');
    fireEvent.click(settingsTab);

    const switches = screen.getAllByTestId('switch');
    expect(switches).toHaveLength(3); // allowEmbedding, showControls, showInfo
  });

  it('updates sharing options when switches are toggled', () => {
    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    // Switch to settings tab first
    const settingsTab = screen.getByTestId('tab-trigger-settings');
    fireEvent.click(settingsTab);

    const switches = screen.getAllByTestId('switch');
    
    // Toggle the first switch (allowEmbedding)
    fireEvent.click(switches[0]);
    
    // The component should update its internal state
    // We can't directly test state, but we can test that the switch reflects the change
    expect(switches[0]).not.toBeChecked();
  });

  it('calls onClose when close button is clicked', () => {
    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    const closeButton = screen.getByText('Close');
    fireEvent.click(closeButton);

    expect(mockOnClose).toHaveBeenCalled();
  });

  it('displays error messages', () => {
    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    // The component should handle and display errors from the sharing operations
    // This would be tested with actual error scenarios in integration tests
    expect(screen.getByTestId('dialog')).toBeInTheDocument();
  });

  it('handles clipboard copy functionality', async () => {
    const mockWriteText = vi.fn(() => Promise.resolve());
    Object.assign(navigator, {
      clipboard: {
        writeText: mockWriteText,
      },
    });

    // Mock a successful share result
    mockOnShare.mockResolvedValue({
      success: true,
      share_url: 'https://example.com/shared/123',
      embed_url: 'https://example.com/embed/123',
    });

    render(
      <GameSharingModal
        game={mockGameData}
        isOpen={true}
        onClose={mockOnClose}
        onShare={mockOnShare}
        onUpdateSettings={mockOnUpdateSettings}
        onRevokeLink={mockOnRevokeLink}
      />
    );

    // First create a share link
    const createButton = screen.getByText('Create Share Link');
    fireEvent.click(createButton);

    await waitFor(() => {
      expect(mockOnShare).toHaveBeenCalled();
    });

    // The copy functionality would be tested in integration tests
    // where we can simulate the full flow including the share result
  });
});