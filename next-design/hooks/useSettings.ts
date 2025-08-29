"use client"

/**
 * SETTINGS HOOK - Manages user and application settings
 *
 * PURPOSE:
 * - Provide global settings state and management
 * - Handle user preferences, theme, and app configuration
 * - Integrate with backend settings storage
 *
 * INERTIA.JS INTEGRATION:
 * - Settings should be stored in database and fetched via Inertia
 * - Updates should use Inertia.put/patch for server persistence
 * - Global settings come from Laravel config via Inertia props
 */

import { useState, useEffect } from "react"

interface UserSettings {
  theme: "light" | "dark" | "system"
  language: string
  notifications: {
    email: boolean
    push: boolean
    desktop: boolean
  }
  ai: {
    model: "gpt-4" | "claude-3" | "gemini-pro"
    temperature: number
    maxTokens: number
  }
  editor: {
    fontSize: number
    tabSize: number
    wordWrap: boolean
  }
}

interface AppSettings {
  features: {
    multiplayer: boolean
    preview: boolean
    collaboration: boolean
  }
  limits: {
    maxWorkspaces: number
    maxThreads: number
    creditsPerMonth: number
  }
}

interface SettingsState {
  user: UserSettings
  app: AppSettings
  isLoading: boolean
}

export function useSettings() {
  const [settings, setSettings] = useState<SettingsState>({
    user: {
      theme: "dark",
      language: "en",
      notifications: {
        email: true,
        push: true,
        desktop: false,
      },
      ai: {
        model: "gpt-4",
        temperature: 0.7,
        maxTokens: 2000,
      },
      editor: {
        fontSize: 14,
        tabSize: 2,
        wordWrap: true,
      },
    },
    app: {
      features: {
        multiplayer: true,
        preview: true,
        collaboration: true,
      },
      limits: {
        maxWorkspaces: 10,
        maxThreads: 50,
        creditsPerMonth: 1000,
      },
    },
    isLoading: false,
  })

  useEffect(() => {
    // In real Inertia.js app, this would come from props or API:
    // const { settings } = usePage().props

    // Load settings from localStorage as fallback
    const savedSettings = localStorage.getItem("surreal-pilot-settings")
    if (savedSettings) {
      try {
        const parsed = JSON.parse(savedSettings)
        setSettings((prev) => ({
          ...prev,
          user: { ...prev.user, ...parsed.user },
        }))
      } catch (error) {
        console.error("Failed to parse saved settings:", error)
      }
    }
  }, [])

  const updateUserSettings = async (updates: Partial<UserSettings>) => {
    // Real implementation: Inertia.put('/settings', updates)
    const newSettings = {
      ...settings,
      user: { ...settings.user, ...updates },
    }

    setSettings(newSettings)

    // Save to localStorage as fallback
    localStorage.setItem("surreal-pilot-settings", JSON.stringify(newSettings))
  }

  const resetSettings = async () => {
    // Real implementation: Inertia.delete('/settings')
    localStorage.removeItem("surreal-pilot-settings")
    window.location.reload()
  }

  return {
    settings,
    updateUserSettings,
    resetSettings,
    isLoading: settings.isLoading,
  }
}
