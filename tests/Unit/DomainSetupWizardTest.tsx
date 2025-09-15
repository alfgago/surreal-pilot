import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import DomainSetupWizard from '../../resources/js/components/games/DomainSetupWizard';

// Mock fetch globally
global.fetch = jest.fn();

// Mock CSRF token
Object.defineProperty(document, 'querySelector', {
  value: jest.fn(() => ({
    getAttribute: () => 'mock-csrf-token'
  })),
  writable: true
});

describe('DomainSetupWizard', () => {
  const mockGame = {
    id: 1,
    title: 'Test Game',
  };

  const mockOnClose = jest.fn();
  const mockOnDomainConfigured = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
    (fetch as jest.Mock).mockClear();
  });

  it('renders the domain setup wizard with initial step', () => {
    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    expect(screen.getByText('Custom Domain Setup')).toBeInTheDocument();
    expect(screen.getByText('Enter Your Custom Domain')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('example.com')).toBeInTheDocument();
  });

  it('shows step indicator with correct states', () => {
    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    // Check step indicators
    expect(screen.getByText('Domain')).toBeInTheDocument();
    expect(screen.getByText('DNS Setup')).toBeInTheDocument();
    expect(screen.getByText('Verification')).toBeInTheDocument();
    expect(screen.getByText('Complete')).toBeInTheDocument();
  });

  it('validates domain input before submission', async () => {
    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    const continueButton = screen.getByText('Continue');
    
    // Button should be disabled when domain is empty
    expect(continueButton).toBeDisabled();

    // Enter a domain
    const domainInput = screen.getByPlaceholderText('example.com');
    fireEvent.change(domainInput, { target: { value: 'test-game.com' } });

    // Button should now be enabled
    expect(continueButton).not.toBeDisabled();
  });

  it('submits domain setup request successfully', async () => {
    const mockResponse = {
      success: true,
      domain: 'test-game.com',
      status: 'pending',
      dns_instructions: {
        type: 'A',
        name: '@',
        value: '127.0.0.1',
        ttl: 300,
        instructions: ['Step 1', 'Step 2'],
        common_providers: {
          'Cloudflare': 'DNS > Records'
        }
      },
      estimated_propagation_time: '5-30 minutes'
    };

    (fetch as jest.Mock).mockResolvedValueOnce({
      ok: true,
      json: async () => mockResponse
    });

    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    // Enter domain and submit
    const domainInput = screen.getByPlaceholderText('example.com');
    fireEvent.change(domainInput, { target: { value: 'test-game.com' } });
    
    const continueButton = screen.getByText('Continue');
    fireEvent.click(continueButton);

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith('/api/games/1/domain', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': 'mock-csrf-token',
        },
        body: JSON.stringify({ domain: 'test-game.com' }),
      });
    });

    // Should advance to DNS setup step
    await waitFor(() => {
      expect(screen.getByText('Configure DNS Settings')).toBeInTheDocument();
    });
  });

  it('handles domain setup errors', async () => {
    const mockErrorResponse = {
      success: false,
      error: 'Domain already in use'
    };

    (fetch as jest.Mock).mockResolvedValueOnce({
      ok: false,
      json: async () => mockErrorResponse
    });

    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    // Enter domain and submit
    const domainInput = screen.getByPlaceholderText('example.com');
    fireEvent.change(domainInput, { target: { value: 'existing-domain.com' } });
    
    const continueButton = screen.getByText('Continue');
    fireEvent.click(continueButton);

    await waitFor(() => {
      expect(screen.getByText('Domain already in use')).toBeInTheDocument();
    });
  });

  it('displays DNS configuration in step 2', async () => {
    const mockGame = {
      id: 1,
      title: 'Test Game',
      custom_domain: 'test-game.com',
      domain_status: 'pending' as const,
    };

    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    // Should start at step 3 (verification) for pending domain
    expect(screen.getByText('Verify Domain Configuration')).toBeInTheDocument();
  });

  it('handles domain verification', async () => {
    const mockVerificationResponse = {
      success: true,
      status: 'active',
      message: 'Domain verified successfully',
      domain_url: 'http://test-game.com',
      verified_at: new Date().toISOString()
    };

    (fetch as jest.Mock).mockResolvedValueOnce({
      ok: true,
      json: async () => mockVerificationResponse
    });

    const mockGame = {
      id: 1,
      title: 'Test Game',
      custom_domain: 'test-game.com',
      domain_status: 'pending' as const,
    };

    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    const verifyButton = screen.getByText('Verify Domain');
    fireEvent.click(verifyButton);

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith('/api/games/1/domain/verify', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': 'mock-csrf-token',
        },
      });
    });

    await waitFor(() => {
      expect(mockOnDomainConfigured).toHaveBeenCalledWith(mockVerificationResponse);
    });
  });

  it('shows completion step for active domain', () => {
    const mockGame = {
      id: 1,
      title: 'Test Game',
      custom_domain: 'test-game.com',
      domain_status: 'active' as const,
    };

    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    expect(screen.getByText('Domain Successfully Configured!')).toBeInTheDocument();
    expect(screen.getByText('Your Game is Live!')).toBeInTheDocument();
  });

  it('handles domain removal', async () => {
    const mockRemovalResponse = {
      success: true,
      message: 'Domain removed successfully'
    };

    (fetch as jest.Mock).mockResolvedValueOnce({
      ok: true,
      json: async () => mockRemovalResponse
    });

    const mockGame = {
      id: 1,
      title: 'Test Game',
      custom_domain: 'test-game.com',
      domain_status: 'active' as const,
    };

    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    const removeButton = screen.getByText('Remove Domain');
    fireEvent.click(removeButton);

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith('/api/games/1/domain', {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': 'mock-csrf-token',
        },
      });
    });

    await waitFor(() => {
      expect(mockOnDomainConfigured).toHaveBeenCalledWith({
        success: true,
        removed: true
      });
      expect(mockOnClose).toHaveBeenCalled();
    });
  });

  it('calls onClose when cancel is clicked', () => {
    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    const cancelButton = screen.getByText('Cancel');
    fireEvent.click(cancelButton);

    expect(mockOnClose).toHaveBeenCalled();
  });

  it('handles network errors gracefully', async () => {
    (fetch as jest.Mock).mockRejectedValueOnce(new Error('Network error'));

    render(
      <DomainSetupWizard
        game={mockGame}
        onClose={mockOnClose}
        onDomainConfigured={mockOnDomainConfigured}
      />
    );

    // Enter domain and submit
    const domainInput = screen.getByPlaceholderText('example.com');
    fireEvent.change(domainInput, { target: { value: 'test-game.com' } });
    
    const continueButton = screen.getByText('Continue');
    fireEvent.click(continueButton);

    await waitFor(() => {
      expect(screen.getByText('Network error occurred. Please try again.')).toBeInTheDocument();
    });
  });
});