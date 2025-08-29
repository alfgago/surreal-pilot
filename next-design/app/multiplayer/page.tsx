"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group"
import { MainLayout } from "@/components/layout/main-layout"
import { Users, Play, Copy, QrCode, Clock, Globe, Settings, StopCircle, Plus, Gamepad2 } from "lucide-react"

interface ActiveSession {
  id: string
  name: string
  profile: string
  players: number
  maxPlayers: number
  region: string
  timeLeft: string
  joinLink: string
  isActive: boolean
}

const activeSessions: ActiveSession[] = [
  {
    id: "session-1",
    name: "Racing Tournament",
    profile: "Real-time action",
    players: 6,
    maxPlayers: 8,
    region: "US East",
    timeLeft: "32 min",
    joinLink: "https://play.surrealpilot.com/join/abc123",
    isActive: true,
  },
  {
    id: "session-2",
    name: "Practice Session",
    profile: "Co-op small-room",
    players: 2,
    maxPlayers: 4,
    region: "EU West",
    timeLeft: "18 min",
    joinLink: "https://play.surrealpilot.com/join/def456",
    isActive: true,
  },
]

export default function MultiplayerPage() {
  const [isStarting, setIsStarting] = useState(false)
  const [sessionForm, setSessionForm] = useState({
    profile: "real-time",
    capacity: "8",
    region: "us-east",
    timeLimit: "40",
    peerToPeer: false,
    turnRelay: true,
  })

  const handleStartSession = async () => {
    setIsStarting(true)
    // Simulate session creation
    await new Promise((resolve) => setTimeout(resolve, 2000))
    setIsStarting(false)
    // In real app, add new session to active sessions
  }

  const handleCopyJoinLink = (link: string) => {
    navigator.clipboard.writeText(link)
    // In real app, show toast notification
  }

  const handleStopSession = (sessionId: string) => {
    // In real app, stop the session
    console.log("Stopping session:", sessionId)
  }

  const handleExtendTime = (sessionId: string) => {
    // In real app, extend session time
    console.log("Extending time for session:", sessionId)
  }

  return (
    <MainLayout currentWorkspace="Web Racing Game" currentEngine="playcanvas">
      <div className="p-6 max-w-6xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-serif font-black text-foreground mb-2">Multiplayer Testing</h1>
          <p className="text-muted-foreground">
            Create temporary multiplayer sessions for playtesting and collaboration
          </p>
        </div>

        <div className="grid lg:grid-cols-2 gap-8">
          {/* Start New Session */}
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle className="text-xl font-serif font-bold flex items-center">
                  <Play className="w-5 h-5 mr-2" />
                  Start Test Session
                </CardTitle>
                <CardDescription>Create a temporary multiplayer room for testing</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="space-y-3">
                  <Label>Session Profile</Label>
                  <RadioGroup
                    value={sessionForm.profile}
                    onValueChange={(value) => setSessionForm((prev) => ({ ...prev, profile: value }))}
                  >
                    <div className="flex items-center space-x-2">
                      <RadioGroupItem value="turn-based" id="turn-based" />
                      <Label htmlFor="turn-based" className="cursor-pointer">
                        <div>
                          <div className="font-medium">Turn-based</div>
                          <div className="text-xs text-muted-foreground">Low latency, perfect for strategy games</div>
                        </div>
                      </Label>
                    </div>
                    <div className="flex items-center space-x-2">
                      <RadioGroupItem value="co-op" id="co-op" />
                      <Label htmlFor="co-op" className="cursor-pointer">
                        <div>
                          <div className="font-medium">Co-op small-room</div>
                          <div className="text-xs text-muted-foreground">2-4 players, cooperative gameplay</div>
                        </div>
                      </Label>
                    </div>
                    <div className="flex items-center space-x-2">
                      <RadioGroupItem value="real-time" id="real-time" />
                      <Label htmlFor="real-time" className="cursor-pointer">
                        <div>
                          <div className="font-medium">Real-time action</div>
                          <div className="text-xs text-muted-foreground">Fast-paced, up to 8 players</div>
                        </div>
                      </Label>
                    </div>
                  </RadioGroup>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="capacity">Max Players</Label>
                    <Select
                      value={sessionForm.capacity}
                      onValueChange={(value) => setSessionForm((prev) => ({ ...prev, capacity: value }))}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="2">2 players</SelectItem>
                        <SelectItem value="4">4 players</SelectItem>
                        <SelectItem value="6">6 players</SelectItem>
                        <SelectItem value="8">8 players</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="region">Region</Label>
                    <Select
                      value={sessionForm.region}
                      onValueChange={(value) => setSessionForm((prev) => ({ ...prev, region: value }))}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="us-east">US East</SelectItem>
                        <SelectItem value="us-west">US West</SelectItem>
                        <SelectItem value="eu-west">EU West</SelectItem>
                        <SelectItem value="asia-pacific">Asia Pacific</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="time-limit">Time Limit</Label>
                  <Select
                    value={sessionForm.timeLimit}
                    onValueChange={(value) => setSessionForm((prev) => ({ ...prev, timeLimit: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="15">15 minutes</SelectItem>
                      <SelectItem value="30">30 minutes</SelectItem>
                      <SelectItem value="40">40 minutes</SelectItem>
                      <SelectItem value="60">1 hour</SelectItem>
                      <SelectItem value="120">2 hours</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-4 p-4 border border-border rounded-lg">
                  <h4 className="font-medium">Advanced Settings</h4>
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <div>
                        <div className="font-medium text-sm">Peer-to-peer</div>
                        <div className="text-xs text-muted-foreground">Direct player connections (PlayCanvas only)</div>
                      </div>
                      <Button
                        variant={sessionForm.peerToPeer ? "default" : "outline"}
                        size="sm"
                        onClick={() => setSessionForm((prev) => ({ ...prev, peerToPeer: !prev.peerToPeer }))}
                      >
                        {sessionForm.peerToPeer ? "On" : "Off"}
                      </Button>
                    </div>
                    <div className="flex items-center justify-between">
                      <div>
                        <div className="font-medium text-sm">TURN relay</div>
                        <div className="text-xs text-muted-foreground">Fallback for NAT traversal</div>
                      </div>
                      <Button
                        variant={sessionForm.turnRelay ? "default" : "outline"}
                        size="sm"
                        onClick={() => setSessionForm((prev) => ({ ...prev, turnRelay: !prev.turnRelay }))}
                      >
                        {sessionForm.turnRelay ? "On" : "Off"}
                      </Button>
                    </div>
                  </div>
                </div>

                <Button onClick={handleStartSession} disabled={isStarting} className="w-full" size="lg">
                  {isStarting ? (
                    <>
                      <Clock className="w-4 h-4 mr-2 animate-spin" />
                      Starting Session...
                    </>
                  ) : (
                    <>
                      <Play className="w-4 h-4 mr-2" />
                      Start Session
                    </>
                  )}
                </Button>
              </CardContent>
            </Card>
          </div>

          {/* Active Sessions */}
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle className="text-xl font-serif font-bold flex items-center justify-between">
                  <div className="flex items-center">
                    <Users className="w-5 h-5 mr-2" />
                    Active Sessions
                  </div>
                  <Badge variant="secondary" className="text-xs">
                    {activeSessions.length} running
                  </Badge>
                </CardTitle>
                <CardDescription>Manage your running multiplayer test sessions</CardDescription>
              </CardHeader>
              <CardContent>
                {activeSessions.length === 0 ? (
                  <div className="text-center py-8">
                    <Gamepad2 className="w-12 h-12 mx-auto text-muted-foreground mb-4" />
                    <p className="text-muted-foreground">No active sessions</p>
                    <p className="text-sm text-muted-foreground">Start a session to begin multiplayer testing</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {activeSessions.map((session) => (
                      <div key={session.id} className="border border-border rounded-lg p-4">
                        <div className="flex items-start justify-between mb-3">
                          <div>
                            <h3 className="font-semibold">{session.name}</h3>
                            <div className="text-sm text-muted-foreground">
                              {session.profile} • {session.region}
                            </div>
                          </div>
                          <Badge variant={session.isActive ? "default" : "secondary"} className="text-xs">
                            {session.isActive ? "Active" : "Ended"}
                          </Badge>
                        </div>

                        <div className="flex items-center space-x-4 mb-3 text-sm">
                          <div className="flex items-center space-x-1">
                            <Users className="w-4 h-4" />
                            <span>
                              {session.players}/{session.maxPlayers}
                            </span>
                          </div>
                          <div className="flex items-center space-x-1">
                            <Clock className="w-4 h-4" />
                            <span>{session.timeLeft} left</span>
                          </div>
                          <div className="flex items-center space-x-1">
                            <Globe className="w-4 h-4" />
                            <span>{session.region}</span>
                          </div>
                        </div>

                        <div className="flex items-center space-x-2 mb-3">
                          <div className="flex-1 px-3 py-2 bg-muted rounded text-xs font-mono truncate">
                            {session.joinLink}
                          </div>
                          <Button variant="outline" size="sm" onClick={() => handleCopyJoinLink(session.joinLink)}>
                            <Copy className="w-3 h-3" />
                          </Button>
                          <Button variant="outline" size="sm">
                            <QrCode className="w-3 h-3" />
                          </Button>
                        </div>

                        <div className="flex items-center space-x-2">
                          <Button variant="outline" size="sm" onClick={() => handleExtendTime(session.id)}>
                            <Plus className="w-3 h-3 mr-1" />
                            Extend
                          </Button>
                          <Button variant="outline" size="sm">
                            <Settings className="w-3 h-3 mr-1" />
                            Settings
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive hover:text-destructive bg-transparent"
                            onClick={() => handleStopSession(session.id)}
                          >
                            <StopCircle className="w-3 h-3 mr-1" />
                            Stop
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </MainLayout>
  )
}
