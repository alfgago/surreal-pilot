"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { MainLayout } from "@/components/layout/main-layout"
import { History, Search, CheckCircle, XCircle, Clock, Eye, RotateCcw, Filter, Calendar, Code, Zap } from "lucide-react"

interface Patch {
  id: string
  intent: string
  result: "success" | "failed" | "pending"
  duration: string
  timestamp: Date
  notes: string
  changes: string[]
  canUndo: boolean
}

const patches: Patch[] = [
  {
    id: "patch-001",
    intent: "Improve car physics handling",
    result: "success",
    duration: "2.3s",
    timestamp: new Date(Date.now() - 300000),
    notes: "Applied realistic suspension and tire grip calculations",
    changes: [
      "Updated CarController.js physics parameters",
      "Modified tire friction coefficients",
      "Added suspension damping system",
    ],
    canUndo: true,
  },
  {
    id: "patch-002",
    intent: "Add new racing track",
    result: "success",
    duration: "4.1s",
    timestamp: new Date(Date.now() - 900000),
    notes: "Created mountain circuit with elevation changes",
    changes: ["Added MountainTrack.json scene", "Imported track mesh and textures", "Configured checkpoint system"],
    canUndo: true,
  },
  {
    id: "patch-003",
    intent: "Fix multiplayer synchronization",
    result: "failed",
    duration: "1.8s",
    timestamp: new Date(Date.now() - 1800000),
    notes: "Network sync failed - reverted safely",
    changes: ["Attempted NetworkManager.js update", "Reverted due to compilation errors"],
    canUndo: false,
  },
  {
    id: "patch-004",
    intent: "Update UI styling",
    result: "success",
    duration: "0.9s",
    timestamp: new Date(Date.now() - 3600000),
    notes: "Modernized HUD and menu interfaces",
    changes: ["Updated CSS styles for HUD elements", "Redesigned main menu layout", "Added responsive breakpoints"],
    canUndo: true,
  },
  {
    id: "patch-005",
    intent: "Optimize rendering performance",
    result: "success",
    duration: "3.2s",
    timestamp: new Date(Date.now() - 7200000),
    notes: "Reduced draw calls and improved LOD system",
    changes: ["Implemented mesh batching", "Added distance-based LOD", "Optimized shader compilation"],
    canUndo: false,
  },
]

export default function HistoryPage() {
  const [searchQuery, setSearchQuery] = useState("")
  const [filterResult, setFilterResult] = useState("all")
  const [selectedPatch, setSelectedPatch] = useState<Patch | null>(null)

  const filteredPatches = patches.filter((patch) => {
    const matchesSearch =
      patch.intent.toLowerCase().includes(searchQuery.toLowerCase()) ||
      patch.notes.toLowerCase().includes(searchQuery.toLowerCase())
    const matchesFilter = filterResult === "all" || patch.result === filterResult

    return matchesSearch && matchesFilter
  })

  const handleUndo = (patchId: string) => {
    // In real app, undo the patch
    console.log("Undoing patch:", patchId)
  }

  const getResultIcon = (result: string) => {
    switch (result) {
      case "success":
        return <CheckCircle className="w-4 h-4 text-green-500" />
      case "failed":
        return <XCircle className="w-4 h-4 text-red-500" />
      case "pending":
        return <Clock className="w-4 h-4 text-yellow-500 animate-spin" />
      default:
        return <Clock className="w-4 h-4 text-muted-foreground" />
    }
  }

  const getResultBadge = (result: string) => {
    switch (result) {
      case "success":
        return (
          <Badge variant="default" className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
            Success
          </Badge>
        )
      case "failed":
        return <Badge variant="destructive">Failed</Badge>
      case "pending":
        return <Badge variant="secondary">Pending</Badge>
      default:
        return <Badge variant="outline">Unknown</Badge>
    }
  }

  return (
    <MainLayout currentWorkspace="Web Racing Game" currentEngine="playcanvas">
      <div className="p-6 max-w-7xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-serif font-black text-foreground mb-2">Patch History</h1>
          <p className="text-muted-foreground">View and manage all AI-generated changes to your project</p>
        </div>

        {/* Search and Filters */}
        <div className="mb-6 space-y-4">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
              <Input
                placeholder="Search patches by intent or notes..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-10"
              />
            </div>
            <div className="flex items-center space-x-2">
              <Filter className="w-4 h-4 text-muted-foreground" />
              <Select value={filterResult} onValueChange={setFilterResult}>
                <SelectTrigger className="w-32">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Results</SelectItem>
                  <SelectItem value="success">Success</SelectItem>
                  <SelectItem value="failed">Failed</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>

        <div className="grid lg:grid-cols-3 gap-6">
          {/* Patches List */}
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <CardTitle className="text-xl font-serif font-bold flex items-center">
                  <History className="w-5 h-5 mr-2" />
                  Patches ({filteredPatches.length})
                </CardTitle>
                <CardDescription>Chronological list of all AI-generated changes</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {filteredPatches.map((patch) => (
                    <div
                      key={patch.id}
                      className={`border border-border rounded-lg p-4 cursor-pointer transition-colors hover:bg-muted/50 ${
                        selectedPatch?.id === patch.id ? "bg-muted/50 border-primary" : ""
                      }`}
                      onClick={() => setSelectedPatch(patch)}
                    >
                      <div className="flex items-start justify-between mb-2">
                        <div className="flex items-start space-x-3 flex-1">
                          {getResultIcon(patch.result)}
                          <div className="flex-1 min-w-0">
                            <h3 className="font-medium text-sm truncate">{patch.intent}</h3>
                            <p className="text-xs text-muted-foreground mt-1">{patch.notes}</p>
                          </div>
                        </div>
                        <div className="flex items-center space-x-2 ml-2">{getResultBadge(patch.result)}</div>
                      </div>

                      <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <div className="flex items-center space-x-4">
                          <div className="flex items-center space-x-1">
                            <Calendar className="w-3 h-3" />
                            <span>{patch.timestamp.toLocaleString()}</span>
                          </div>
                          <div className="flex items-center space-x-1">
                            <Zap className="w-3 h-3" />
                            <span>{patch.duration}</span>
                          </div>
                        </div>
                        <div className="flex items-center space-x-1">
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 px-2 text-xs"
                            onClick={(e) => {
                              e.stopPropagation()
                              setSelectedPatch(patch)
                            }}
                          >
                            <Eye className="w-3 h-3 mr-1" />
                            View
                          </Button>
                          {patch.canUndo && (
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-6 px-2 text-xs"
                              onClick={(e) => {
                                e.stopPropagation()
                                handleUndo(patch.id)
                              }}
                            >
                              <RotateCcw className="w-3 h-3 mr-1" />
                              Undo
                            </Button>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}

                  {filteredPatches.length === 0 && (
                    <div className="text-center py-8">
                      <History className="w-12 h-12 mx-auto text-muted-foreground mb-4" />
                      <p className="text-muted-foreground">No patches found</p>
                      <p className="text-sm text-muted-foreground">Try adjusting your search or filters</p>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Patch Details */}
          <div>
            <Card className="sticky top-24">
              <CardHeader>
                <CardTitle className="text-lg font-serif font-bold">Patch Details</CardTitle>
              </CardHeader>
              <CardContent>
                {selectedPatch ? (
                  <div className="space-y-4">
                    <div>
                      <div className="flex items-center space-x-2 mb-2">
                        {getResultIcon(selectedPatch.result)}
                        <span className="font-medium text-sm">{selectedPatch.intent}</span>
                      </div>
                      {getResultBadge(selectedPatch.result)}
                    </div>

                    <div className="space-y-2">
                      <h4 className="font-medium text-sm">Timeline</h4>
                      <div className="text-xs text-muted-foreground space-y-1">
                        <div>Started: {selectedPatch.timestamp.toLocaleString()}</div>
                        <div>Duration: {selectedPatch.duration}</div>
                        <div>Status: {selectedPatch.result}</div>
                      </div>
                    </div>

                    <div className="space-y-2">
                      <h4 className="font-medium text-sm">Notes</h4>
                      <p className="text-xs text-muted-foreground">{selectedPatch.notes}</p>
                    </div>

                    <div className="space-y-2">
                      <h4 className="font-medium text-sm">Changes Made</h4>
                      <div className="space-y-1">
                        {selectedPatch.changes.map((change, index) => (
                          <div key={index} className="flex items-start space-x-2 text-xs">
                            <Code className="w-3 h-3 mt-0.5 text-muted-foreground flex-shrink-0" />
                            <span className="text-muted-foreground">{change}</span>
                          </div>
                        ))}
                      </div>
                    </div>

                    <div className="pt-4 border-t border-border">
                      <div className="flex space-x-2">
                        <Button variant="outline" size="sm" className="flex-1 bg-transparent">
                          <Eye className="w-3 h-3 mr-1" />
                          View Diff
                        </Button>
                        {selectedPatch.canUndo && (
                          <Button
                            variant="outline"
                            size="sm"
                            className="flex-1 bg-transparent"
                            onClick={() => handleUndo(selectedPatch.id)}
                          >
                            <RotateCcw className="w-3 h-3 mr-1" />
                            Undo
                          </Button>
                        )}
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="text-center py-8">
                    <History className="w-8 h-8 mx-auto text-muted-foreground mb-2" />
                    <p className="text-sm text-muted-foreground">Select a patch to view details</p>
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
