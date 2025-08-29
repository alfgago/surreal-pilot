"use client"

import type React from "react"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Slider } from "@/components/ui/slider"
import { ArrowLeft, Save, Key, Zap, Brain, Globe, Shield, AlertTriangle, CheckCircle } from "lucide-react"
import Link from "next/link"

interface Provider {
  id: string
  name: string
  icon: React.ReactNode
  enabled: boolean
  hasApiKey: boolean
  models: string[]
  defaultModel: string
  usageLimit?: number
  currentUsage?: number
}

export default function ProviderSettingsPage() {
  const [providers, setProviders] = useState<Provider[]>([
    {
      id: "openai",
      name: "OpenAI",
      icon: <Brain className="w-5 h-5" />,
      enabled: true,
      hasApiKey: true,
      models: ["gpt-4", "gpt-4-turbo", "gpt-3.5-turbo"],
      defaultModel: "gpt-4",
      usageLimit: 1000,
      currentUsage: 247,
    },
    {
      id: "anthropic",
      name: "Anthropic",
      icon: <Zap className="w-5 h-5" />,
      enabled: false,
      hasApiKey: false,
      models: ["claude-3-opus", "claude-3-sonnet", "claude-3-haiku"],
      defaultModel: "claude-3-sonnet",
    },
    {
      id: "gemini",
      name: "Google Gemini",
      icon: <Globe className="w-5 h-5" />,
      enabled: false,
      hasApiKey: false,
      models: ["gemini-pro", "gemini-pro-vision"],
      defaultModel: "gemini-pro",
    },
    {
      id: "ollama",
      name: "Ollama (Local)",
      icon: <Shield className="w-5 h-5" />,
      enabled: false,
      hasApiKey: true,
      models: ["llama2", "codellama", "mistral"],
      defaultModel: "llama2",
    },
  ])

  const [globalSettings, setGlobalSettings] = useState({
    defaultProvider: "openai",
    temperature: [0.7],
    maxTokens: [2048],
    enableFallback: true,
    logRequests: false,
  })

  const toggleProvider = (providerId: string) => {
    setProviders(providers.map((p) => (p.id === providerId ? { ...p, enabled: !p.enabled } : p)))
  }

  const updateProviderModel = (providerId: string, model: string) => {
    setProviders(providers.map((p) => (p.id === providerId ? { ...p, defaultModel: model } : p)))
  }

  const handleSave = () => {
    console.log("Saving provider settings:", { providers, globalSettings })
  }

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b border-border bg-card/50 backdrop-blur-sm">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <Link
                href="/company/settings"
                className="flex items-center space-x-2 text-muted-foreground hover:text-foreground transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                <span>Back to Company</span>
              </Link>
              <div className="flex items-center space-x-3">
                <div className="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
                  <Key className="w-5 h-5 text-primary-foreground" />
                </div>
                <div>
                  <h1 className="text-xl font-serif font-black text-foreground">AI Provider Settings</h1>
                  <p className="text-sm text-muted-foreground">Configure AI models and API keys</p>
                </div>
              </div>
            </div>
            <Button onClick={handleSave}>
              <Save className="w-4 h-4 mr-2" />
              Save Settings
            </Button>
          </div>
        </div>
      </header>

      <div className="container mx-auto px-4 py-8 max-w-4xl">
        <div className="space-y-6">
          {/* Global Settings */}
          <Card className="border-border bg-card">
            <CardHeader>
              <CardTitle className="font-serif font-bold">Global Settings</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label htmlFor="defaultProvider">Default Provider</Label>
                  <Select
                    value={globalSettings.defaultProvider}
                    onValueChange={(value) => setGlobalSettings({ ...globalSettings, defaultProvider: value })}
                  >
                    <SelectTrigger className="bg-input border-border">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {providers
                        .filter((p) => p.enabled)
                        .map((provider) => (
                          <SelectItem key={provider.id} value={provider.id}>
                            {provider.name}
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>Temperature: {globalSettings.temperature[0]}</Label>
                  <Slider
                    value={globalSettings.temperature}
                    onValueChange={(value) => setGlobalSettings({ ...globalSettings, temperature: value })}
                    max={2}
                    min={0}
                    step={0.1}
                    className="w-full"
                  />
                  <p className="text-xs text-muted-foreground">Controls randomness in AI responses</p>
                </div>
              </div>

              <div className="space-y-2">
                <Label>Max Tokens: {globalSettings.maxTokens[0]}</Label>
                <Slider
                  value={globalSettings.maxTokens}
                  onValueChange={(value) => setGlobalSettings({ ...globalSettings, maxTokens: value })}
                  max={4096}
                  min={256}
                  step={256}
                  className="w-full"
                />
                <p className="text-xs text-muted-foreground">Maximum length of AI responses</p>
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-1">
                  <Label className="text-sm font-medium">Enable Fallback</Label>
                  <p className="text-xs text-muted-foreground">
                    Automatically switch to backup provider if primary fails
                  </p>
                </div>
                <Switch
                  checked={globalSettings.enableFallback}
                  onCheckedChange={(checked) => setGlobalSettings({ ...globalSettings, enableFallback: checked })}
                />
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-1">
                  <Label className="text-sm font-medium">Log Requests</Label>
                  <p className="text-xs text-muted-foreground">Keep logs of API requests for debugging</p>
                </div>
                <Switch
                  checked={globalSettings.logRequests}
                  onCheckedChange={(checked) => setGlobalSettings({ ...globalSettings, logRequests: checked })}
                />
              </div>
            </CardContent>
          </Card>

          {/* Provider Configuration */}
          <div className="space-y-4">
            <h2 className="text-lg font-serif font-bold text-foreground">AI Providers</h2>
            {providers.map((provider) => (
              <Card key={provider.id} className="border-border bg-card">
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                      <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                        {provider.icon}
                      </div>
                      <div>
                        <h3 className="font-serif font-bold text-foreground">{provider.name}</h3>
                        <div className="flex items-center space-x-2">
                          <Badge
                            variant={provider.enabled ? "default" : "secondary"}
                            className={`text-xs ${provider.enabled ? "bg-primary/10 text-primary" : ""}`}
                          >
                            {provider.enabled ? "Enabled" : "Disabled"}
                          </Badge>
                          {provider.hasApiKey ? (
                            <Badge variant="outline" className="text-xs">
                              <CheckCircle className="w-3 h-3 mr-1 text-green-500" />
                              API Key Set
                            </Badge>
                          ) : (
                            <Badge variant="outline" className="text-xs">
                              <AlertTriangle className="w-3 h-3 mr-1 text-yellow-500" />
                              No API Key
                            </Badge>
                          )}
                        </div>
                      </div>
                    </div>
                    <Switch checked={provider.enabled} onCheckedChange={() => toggleProvider(provider.id)} />
                  </div>
                </CardHeader>
                {provider.enabled && (
                  <CardContent className="space-y-4">
                    <div className="grid md:grid-cols-2 gap-4">
                      <div className="space-y-2">
                        <Label htmlFor={`${provider.id}-key`}>API Key</Label>
                        <Input
                          id={`${provider.id}-key`}
                          type="password"
                          placeholder={provider.hasApiKey ? "••••••••••••••••" : "Enter API key"}
                          className="bg-input border-border"
                        />
                      </div>
                      <div className="space-y-2">
                        <Label htmlFor={`${provider.id}-model`}>Default Model</Label>
                        <Select
                          value={provider.defaultModel}
                          onValueChange={(value) => updateProviderModel(provider.id, value)}
                        >
                          <SelectTrigger className="bg-input border-border">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            {provider.models.map((model) => (
                              <SelectItem key={model} value={model}>
                                {model}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    {provider.usageLimit && (
                      <>
                        <Separator />
                        <div className="space-y-2">
                          <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Usage This Month</span>
                            <span className="font-medium">
                              {provider.currentUsage} / {provider.usageLimit} requests
                            </span>
                          </div>
                          <div className="w-full bg-muted rounded-full h-2">
                            <div
                              className="bg-primary h-2 rounded-full transition-all"
                              style={{
                                width: `${((provider.currentUsage || 0) / provider.usageLimit) * 100}%`,
                              }}
                            />
                          </div>
                        </div>
                      </>
                    )}
                  </CardContent>
                )}
              </Card>
            ))}
          </div>

          {/* Usage Warning */}
          <Card className="border-yellow-500/20 bg-yellow-500/5">
            <CardContent className="p-4">
              <div className="flex items-start space-x-3">
                <AlertTriangle className="w-5 h-5 text-yellow-500 mt-0.5" />
                <div>
                  <h4 className="font-medium text-foreground">API Key Security</h4>
                  <p className="text-sm text-muted-foreground mt-1">
                    Your API keys are encrypted and stored securely. Never share your keys with unauthorized users. You
                    can revoke access at any time from your provider's dashboard.
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
