"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Slider } from "@/components/ui/slider"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog"
import { Separator } from "@/components/ui/separator"
import { Brain, Zap, Globe, Shield, Save } from "lucide-react"

interface ChatSettingsModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function ChatSettingsModal({ open, onOpenChange }: ChatSettingsModalProps) {
  const [settings, setSettings] = useState({
    provider: "openai",
    model: "gpt-4",
    temperature: [0.7],
    maxTokens: [2048],
    systemPrompt:
      "You are an AI assistant specialized in game development. Help users with Unreal Engine and PlayCanvas development.",
    streamResponses: true,
    showTokenCount: false,
    autoSave: true,
  })

  const providers = [
    {
      id: "openai",
      name: "OpenAI",
      icon: <Brain className="w-4 h-4" />,
      models: ["gpt-4", "gpt-4-turbo", "gpt-3.5-turbo"],
    },
    {
      id: "anthropic",
      name: "Anthropic",
      icon: <Zap className="w-4 h-4" />,
      models: ["claude-3-opus", "claude-3-sonnet"],
    },
    {
      id: "gemini",
      name: "Google Gemini",
      icon: <Globe className="w-4 h-4" />,
      models: ["gemini-pro", "gemini-pro-vision"],
    },
    {
      id: "ollama",
      name: "Ollama (Local)",
      icon: <Shield className="w-4 h-4" />,
      models: ["llama2", "codellama", "mistral"],
    },
  ]

  const selectedProvider = providers.find((p) => p.id === settings.provider)

  const handleSave = () => {
    console.log("Saving chat settings:", settings)
    onOpenChange(false)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle className="font-serif font-bold">Chat Settings</DialogTitle>
          <DialogDescription>Configure AI models and chat behavior</DialogDescription>
        </DialogHeader>

        <div className="space-y-6 py-4">
          {/* Provider Selection */}
          <div className="space-y-4">
            <Label className="text-base font-medium">AI Provider</Label>
            <div className="grid grid-cols-2 gap-3">
              {providers.map((provider) => (
                <div
                  key={provider.id}
                  className={`p-3 border rounded-lg cursor-pointer transition-colors ${
                    settings.provider === provider.id
                      ? "border-primary bg-primary/5"
                      : "border-border hover:bg-muted/50"
                  }`}
                  onClick={() => setSettings({ ...settings, provider: provider.id, model: provider.models[0] })}
                >
                  <div className="flex items-center space-x-2">
                    {provider.icon}
                    <span className="font-medium text-sm">{provider.name}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <Separator />

          {/* Model Selection */}
          <div className="space-y-2">
            <Label htmlFor="model">Model</Label>
            <Select value={settings.model} onValueChange={(value) => setSettings({ ...settings, model: value })}>
              <SelectTrigger className="bg-input border-border">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {selectedProvider?.models.map((model) => (
                  <SelectItem key={model} value={model}>
                    {model}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Parameters */}
          <div className="grid md:grid-cols-2 gap-6">
            <div className="space-y-2">
              <Label>Temperature: {settings.temperature[0]}</Label>
              <Slider
                value={settings.temperature}
                onValueChange={(value) => setSettings({ ...settings, temperature: value })}
                max={2}
                min={0}
                step={0.1}
                className="w-full"
              />
              <p className="text-xs text-muted-foreground">Controls creativity and randomness</p>
            </div>

            <div className="space-y-2">
              <Label>Max Tokens: {settings.maxTokens[0]}</Label>
              <Slider
                value={settings.maxTokens}
                onValueChange={(value) => setSettings({ ...settings, maxTokens: value })}
                max={4096}
                min={256}
                step={256}
                className="w-full"
              />
              <p className="text-xs text-muted-foreground">Maximum response length</p>
            </div>
          </div>

          <Separator />

          {/* System Prompt */}
          <div className="space-y-2">
            <Label htmlFor="systemPrompt">System Prompt</Label>
            <Textarea
              id="systemPrompt"
              value={settings.systemPrompt}
              onChange={(e) => setSettings({ ...settings, systemPrompt: e.target.value })}
              className="bg-input border-border min-h-[100px]"
              placeholder="Define the AI's role and behavior..."
            />
            <p className="text-xs text-muted-foreground">This message defines how the AI should behave and respond</p>
          </div>

          <Separator />

          {/* Preferences */}
          <div className="space-y-4">
            <Label className="text-base font-medium">Preferences</Label>

            <div className="flex items-center justify-between">
              <div className="space-y-1">
                <Label className="text-sm font-medium">Stream Responses</Label>
                <p className="text-xs text-muted-foreground">Show responses as they're generated</p>
              </div>
              <Switch
                checked={settings.streamResponses}
                onCheckedChange={(checked) => setSettings({ ...settings, streamResponses: checked })}
              />
            </div>

            <div className="flex items-center justify-between">
              <div className="space-y-1">
                <Label className="text-sm font-medium">Show Token Count</Label>
                <p className="text-xs text-muted-foreground">Display token usage for each message</p>
              </div>
              <Switch
                checked={settings.showTokenCount}
                onCheckedChange={(checked) => setSettings({ ...settings, showTokenCount: checked })}
              />
            </div>

            <div className="flex items-center justify-between">
              <div className="space-y-1">
                <Label className="text-sm font-medium">Auto Save Conversations</Label>
                <p className="text-xs text-muted-foreground">Automatically save chat history</p>
              </div>
              <Switch
                checked={settings.autoSave}
                onCheckedChange={(checked) => setSettings({ ...settings, autoSave: checked })}
              />
            </div>
          </div>
        </div>

        <div className="flex justify-end space-x-2 pt-4">
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSave}>
            <Save className="w-4 h-4 mr-2" />
            Save Settings
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
