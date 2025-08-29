"use client"

import { MainLayout } from "@/components/layout/main-layout"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { User, Bell, Zap, Gamepad2, Save } from "lucide-react"
import { useState } from "react"

export default function SettingsPage() {
  const [settings, setSettings] = useState({
    // General
    theme: "dark",
    language: "en",

    // Notifications
    emailNotifications: true,
    pushNotifications: false,
    patchNotifications: true,

    // AI & Chat
    defaultModel: "gpt-4",
    temperature: "0.7",
    autoPreview: true,
    includeContext: true,

    // Workspace
    autoSave: true,
    previewRefresh: true,
    debugMode: false,
  })

  const handleSave = () => {
    // In real app, save settings
    console.log("Saving settings:", settings)
  }

  return (
    <MainLayout currentWorkspace="Web Racing Game" currentEngine="playcanvas">
      <div className="p-6 max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-serif font-black text-foreground mb-2">Settings</h1>
          <p className="text-muted-foreground">Customize your SurrealPilot experience</p>
        </div>

        <div className="space-y-6">
          {/* General Settings */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center">
                <User className="w-5 h-5 mr-2" />
                General
              </CardTitle>
              <CardDescription>Basic application preferences</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label htmlFor="theme">Theme</Label>
                  <Select
                    value={settings.theme}
                    onValueChange={(value) => setSettings((prev) => ({ ...prev, theme: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="light">Light</SelectItem>
                      <SelectItem value="dark">Dark</SelectItem>
                      <SelectItem value="system">System</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="language">Language</Label>
                  <Select
                    value={settings.language}
                    onValueChange={(value) => setSettings((prev) => ({ ...prev, language: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="en">English</SelectItem>
                      <SelectItem value="es">Español</SelectItem>
                      <SelectItem value="fr">Français</SelectItem>
                      <SelectItem value="de">Deutsch</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Notifications */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center">
                <Bell className="w-5 h-5 mr-2" />
                Notifications
              </CardTitle>
              <CardDescription>Control how and when you receive notifications</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-medium">Email Notifications</div>
                    <div className="text-sm text-muted-foreground">Receive updates via email</div>
                  </div>
                  <Switch
                    checked={settings.emailNotifications}
                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, emailNotifications: checked }))}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-medium">Push Notifications</div>
                    <div className="text-sm text-muted-foreground">Browser push notifications</div>
                  </div>
                  <Switch
                    checked={settings.pushNotifications}
                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, pushNotifications: checked }))}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-medium">Patch Notifications</div>
                    <div className="text-sm text-muted-foreground">Notify when patches are applied</div>
                  </div>
                  <Switch
                    checked={settings.patchNotifications}
                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, patchNotifications: checked }))}
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* AI & Chat Settings */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center">
                <Zap className="w-5 h-5 mr-2" />
                AI & Chat
              </CardTitle>
              <CardDescription>Configure AI behavior and chat preferences</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label htmlFor="model">Default AI Model</Label>
                  <Select
                    value={settings.defaultModel}
                    onValueChange={(value) => setSettings((prev) => ({ ...prev, defaultModel: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="gpt-4">GPT-4</SelectItem>
                      <SelectItem value="gpt-3.5-turbo">GPT-3.5 Turbo</SelectItem>
                      <SelectItem value="claude-3">Claude 3</SelectItem>
                      <SelectItem value="gemini-pro">Gemini Pro</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="temperature">Temperature</Label>
                  <Select
                    value={settings.temperature}
                    onValueChange={(value) => setSettings((prev) => ({ ...prev, temperature: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="0.1">0.1 (Focused)</SelectItem>
                      <SelectItem value="0.5">0.5 (Balanced)</SelectItem>
                      <SelectItem value="0.7">0.7 (Creative)</SelectItem>
                      <SelectItem value="1.0">1.0 (Very Creative)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-medium">Auto-update Preview</div>
                    <div className="text-sm text-muted-foreground">Automatically update preview after patches</div>
                  </div>
                  <Switch
                    checked={settings.autoPreview}
                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, autoPreview: checked }))}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-medium">Include Context</div>
                    <div className="text-sm text-muted-foreground">Include project context in AI requests</div>
                  </div>
                  <Switch
                    checked={settings.includeContext}
                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, includeContext: checked }))}
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Workspace Settings */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center">
                <Gamepad2 className="w-5 h-5 mr-2" />
                Workspace
              </CardTitle>
              <CardDescription>Project and development preferences</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-medium">Auto-save</div>
                    <div className="text-sm text-muted-foreground">Automatically save changes</div>
                  </div>
                  <Switch
                    checked={settings.autoSave}
                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, autoSave: checked }))}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-medium">Preview Auto-refresh</div>
                    <div className="text-sm text-muted-foreground">Refresh preview when code changes</div>
                  </div>
                  <Switch
                    checked={settings.previewRefresh}
                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, previewRefresh: checked }))}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <div className="font-medium">Debug Mode</div>
                    <div className="text-sm text-muted-foreground">Show detailed logs and debug information</div>
                  </div>
                  <Switch
                    checked={settings.debugMode}
                    onCheckedChange={(checked) => setSettings((prev) => ({ ...prev, debugMode: checked }))}
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Save Button */}
          <div className="flex justify-end">
            <Button onClick={handleSave} size="lg">
              <Save className="w-4 h-4 mr-2" />
              Save Settings
            </Button>
          </div>
        </div>
      </div>
    </MainLayout>
  )
}
