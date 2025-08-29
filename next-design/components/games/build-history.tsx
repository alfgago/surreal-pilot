import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { ScrollArea } from "@/components/ui/scroll-area"
import { CheckCircle, XCircle, Clock, Download, Play, GitCommit } from "lucide-react"

interface Build {
  id: string
  version: string
  status: "success" | "failed" | "building"
  timestamp: Date
  duration: string
  size: string
  commit?: string
  notes?: string
}

const mockBuilds: Build[] = [
  {
    id: "1",
    version: "v0.3.1",
    status: "success",
    timestamp: new Date(Date.now() - 3600000),
    duration: "4m 32s",
    size: "1.2 GB",
    commit: "a1b2c3d",
    notes: "Fixed player movement and added new textures",
  },
  {
    id: "2",
    version: "v0.3.0",
    status: "success",
    timestamp: new Date(Date.now() - 86400000),
    duration: "3m 45s",
    size: "1.1 GB",
    commit: "e4f5g6h",
    notes: "Major update with new AI system",
  },
  {
    id: "3",
    version: "v0.2.9",
    status: "failed",
    timestamp: new Date(Date.now() - 172800000),
    duration: "1m 12s",
    size: "-",
    commit: "i7j8k9l",
    notes: "Build failed due to compilation errors",
  },
  {
    id: "4",
    version: "v0.2.8",
    status: "success",
    timestamp: new Date(Date.now() - 259200000),
    duration: "5m 18s",
    size: "1.0 GB",
    commit: "m0n1o2p",
    notes: "Performance optimizations and bug fixes",
  },
]

interface BuildHistoryProps {
  gameId: string
}

export function BuildHistory({ gameId }: BuildHistoryProps) {
  const getStatusIcon = (status: string) => {
    switch (status) {
      case "success":
        return <CheckCircle className="w-4 h-4 text-green-500" />
      case "failed":
        return <XCircle className="w-4 h-4 text-red-500" />
      case "building":
        return <Clock className="w-4 h-4 text-yellow-500 animate-spin" />
      default:
        return <Clock className="w-4 h-4 text-muted-foreground" />
    }
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case "success":
        return "bg-green-500/10 text-green-500"
      case "failed":
        return "bg-red-500/10 text-red-500"
      case "building":
        return "bg-yellow-500/10 text-yellow-500"
      default:
        return "bg-muted text-muted-foreground"
    }
  }

  return (
    <Card className="border-border bg-card">
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="font-serif font-bold">Build History</CardTitle>
          <Button>New Build</Button>
        </div>
      </CardHeader>
      <CardContent>
        <ScrollArea className="h-96">
          <div className="space-y-4">
            {mockBuilds.map((build) => (
              <div key={build.id} className="border border-border rounded-lg p-4 hover:bg-muted/30 transition-colors">
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center space-x-3">
                    {getStatusIcon(build.status)}
                    <div>
                      <h4 className="font-medium text-foreground">{build.version}</h4>
                      <div className="flex items-center space-x-2 text-sm text-muted-foreground">
                        <span>{build.timestamp.toLocaleString()}</span>
                        <span>•</span>
                        <span>{build.duration}</span>
                        {build.commit && (
                          <>
                            <span>•</span>
                            <div className="flex items-center">
                              <GitCommit className="w-3 h-3 mr-1" />
                              <span className="font-mono">{build.commit}</span>
                            </div>
                          </>
                        )}
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center space-x-2">
                    <Badge className={`text-xs ${getStatusColor(build.status)}`}>{build.status}</Badge>
                    {build.status === "success" && (
                      <div className="flex items-center space-x-1">
                        <Button variant="outline" size="sm">
                          <Play className="w-3 h-3" />
                        </Button>
                        <Button variant="outline" size="sm">
                          <Download className="w-3 h-3" />
                        </Button>
                      </div>
                    )}
                  </div>
                </div>

                {build.notes && <p className="text-sm text-muted-foreground mb-2">{build.notes}</p>}

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                  <span>Build Size: {build.size}</span>
                  {build.status === "success" && <span>Ready for deployment</span>}
                </div>
              </div>
            ))}
          </div>
        </ScrollArea>
      </CardContent>
    </Card>
  )
}
