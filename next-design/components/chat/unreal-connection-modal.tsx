"use client"

import { useState } from "react"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { CheckCircle, Download, ExternalLink, Loader2, AlertCircle, Copy } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

interface UnrealConnectionModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onRetryConnection: () => void
  connectionStatus: "disconnected" | "connecting" | "connected" | "error"
}

export function UnrealConnectionModal({
  open,
  onOpenChange,
  onRetryConnection,
  connectionStatus,
}: UnrealConnectionModalProps) {
  const [step, setStep] = useState(1)
  const [pluginToken] = useState("sp_" + Math.random().toString(36).substring(2, 15))

  const steps = [
    {
      title: "Download Plugin",
      description: "Download and install the SurrealPilot plugin for Unreal Engine",
      completed: false,
    },
    {
      title: "Install Plugin",
      description: "Place the plugin in your project's Plugins folder",
      completed: false,
    },
    {
      title: "Configure Connection",
      description: "Enter the connection token in the plugin settings",
      completed: false,
    },
    {
      title: "Test Connection",
      description: "Verify the connection between Unreal Engine and SurrealPilot",
      completed: connectionStatus === "connected",
    },
  ]

  const copyToken = () => {
    navigator.clipboard.writeText(pluginToken)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center space-x-2">
            <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
              <ExternalLink className="w-4 h-4 text-primary-foreground" />
            </div>
            <span>Connect Unreal Engine</span>
          </DialogTitle>
          <DialogDescription>
            Follow these steps to connect your Unreal Engine project with SurrealPilot
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-6">
          {/* Connection Status */}
          <Alert
            className={
              connectionStatus === "connected"
                ? "border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950"
                : ""
            }
          >
            <div className="flex items-center space-x-2">
              {connectionStatus === "connecting" && <Loader2 className="h-4 w-4 animate-spin" />}
              {connectionStatus === "connected" && <CheckCircle className="h-4 w-4 text-green-500" />}
              {connectionStatus === "error" && <AlertCircle className="h-4 w-4 text-red-500" />}
              {connectionStatus === "disconnected" && <AlertCircle className="h-4 w-4" />}
              <span className="font-medium">
                {connectionStatus === "connecting" && "Connecting to Unreal Engine..."}
                {connectionStatus === "connected" && "Successfully connected to Unreal Engine!"}
                {connectionStatus === "error" && "Connection failed"}
                {connectionStatus === "disconnected" && "Unreal Engine not detected"}
              </span>
            </div>
            <AlertDescription className="mt-2">
              {connectionStatus === "connected" && "You can now start chatting with your AI copilot."}
              {connectionStatus === "error" &&
                "Please check that the plugin is installed and Unreal Engine is running."}
              {connectionStatus === "disconnected" &&
                "Make sure Unreal Engine is open and the plugin is properly configured."}
              {connectionStatus === "connecting" && "Please wait while we establish the connection..."}
            </AlertDescription>
          </Alert>

          {/* Setup Steps */}
          <div className="space-y-4">
            <h3 className="font-serif font-semibold">Setup Steps</h3>

            {/* Step 1: Download Plugin */}
            <div className="border border-border rounded-lg p-4">
              <div className="flex items-start space-x-3">
                <div className="w-6 h-6 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-medium">
                  1
                </div>
                <div className="flex-1">
                  <h4 className="font-medium">Download SurrealPilot Plugin</h4>
                  <p className="text-sm text-muted-foreground mt-1">
                    Download the latest version of the SurrealPilot plugin for Unreal Engine 5.1+
                  </p>
                  <div className="mt-3 flex space-x-2">
                    <Button size="sm" className="bg-primary hover:bg-primary/90">
                      <Download className="w-4 h-4 mr-2" />
                      Download Plugin v1.2.0
                    </Button>
                    <Button variant="outline" size="sm">
                      <ExternalLink className="w-4 h-4 mr-2" />
                      View Documentation
                    </Button>
                  </div>
                </div>
              </div>
            </div>

            {/* Step 2: Install Plugin */}
            <div className="border border-border rounded-lg p-4">
              <div className="flex items-start space-x-3">
                <div className="w-6 h-6 rounded-full bg-muted text-muted-foreground flex items-center justify-center text-sm font-medium">
                  2
                </div>
                <div className="flex-1">
                  <h4 className="font-medium">Install Plugin</h4>
                  <p className="text-sm text-muted-foreground mt-1">
                    Extract and place the plugin in your project's Plugins folder
                  </p>
                  <div className="mt-3 bg-muted rounded-md p-3 font-mono text-sm">
                    YourProject/Plugins/SurrealPilot/
                  </div>
                  <p className="text-xs text-muted-foreground mt-2">
                    Restart Unreal Engine after installing the plugin
                  </p>
                </div>
              </div>
            </div>

            {/* Step 3: Configure Connection */}
            <div className="border border-border rounded-lg p-4">
              <div className="flex items-start space-x-3">
                <div className="w-6 h-6 rounded-full bg-muted text-muted-foreground flex items-center justify-center text-sm font-medium">
                  3
                </div>
                <div className="flex-1">
                  <h4 className="font-medium">Configure Connection Token</h4>
                  <p className="text-sm text-muted-foreground mt-1">
                    Copy this token and paste it in the SurrealPilot plugin settings in Unreal Engine
                  </p>
                  <div className="mt-3">
                    <Label htmlFor="token" className="text-sm font-medium">
                      Connection Token
                    </Label>
                    <div className="flex space-x-2 mt-1">
                      <Input id="token" value={pluginToken} readOnly className="font-mono text-sm" />
                      <Button variant="outline" size="sm" onClick={copyToken}>
                        <Copy className="w-4 h-4" />
                      </Button>
                    </div>
                  </div>
                  <p className="text-xs text-muted-foreground mt-2">
                    Find this setting in: Edit → Project Settings → Plugins → SurrealPilot
                  </p>
                </div>
              </div>
            </div>

            {/* Step 4: Test Connection */}
            <div className="border border-border rounded-lg p-4">
              <div className="flex items-start space-x-3">
                <div className="w-6 h-6 rounded-full bg-muted text-muted-foreground flex items-center justify-center text-sm font-medium">
                  4
                </div>
                <div className="flex-1">
                  <h4 className="font-medium">Test Connection</h4>
                  <p className="text-sm text-muted-foreground mt-1">
                    Click "Test Connection" in the plugin settings or use the button below
                  </p>
                  <div className="mt-3">
                    <Button onClick={onRetryConnection} disabled={connectionStatus === "connecting"} size="sm">
                      {connectionStatus === "connecting" ? (
                        <>
                          <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                          Testing Connection...
                        </>
                      ) : (
                        <>
                          <CheckCircle className="w-4 h-4 mr-2" />
                          Test Connection
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Action Buttons */}
          <div className="flex justify-between pt-4 border-t border-border">
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <div className="space-x-2">
              <Button variant="outline" onClick={onRetryConnection}>
                Retry Connection
              </Button>
              {connectionStatus === "connected" && <Button onClick={() => onOpenChange(false)}>Start Chatting</Button>}
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
