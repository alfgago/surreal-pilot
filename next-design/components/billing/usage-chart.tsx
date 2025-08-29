"use client"

import { Card, CardContent } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts"

const usageData = [
  { date: "Jan 1", credits: 45, requests: 12 },
  { date: "Jan 8", credits: 120, requests: 28 },
  { date: "Jan 15", credits: 89, requests: 22 },
  { date: "Jan 22", credits: 156, requests: 35 },
  { date: "Jan 29", credits: 203, requests: 48 },
  { date: "Feb 5", credits: 178, requests: 41 },
  { date: "Feb 12", credits: 234, requests: 52 },
  { date: "Feb 19", credits: 189, requests: 44 },
  { date: "Feb 26", credits: 267, requests: 61 },
  { date: "Mar 5", credits: 298, requests: 68 },
]

export function UsageChart() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h3 className="font-medium">Credit Usage Over Time</h3>
        <div className="flex items-center space-x-2">
          <Badge variant="secondary" className="text-xs">
            Last 30 days
          </Badge>
        </div>
      </div>

      <div className="h-64">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={usageData}>
            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
            <XAxis dataKey="date" className="text-xs" />
            <YAxis className="text-xs" />
            <Tooltip
              contentStyle={{
                backgroundColor: "hsl(var(--card))",
                border: "1px solid hsl(var(--border))",
                borderRadius: "8px",
              }}
            />
            <Line
              type="monotone"
              dataKey="credits"
              stroke="hsl(var(--primary))"
              strokeWidth={2}
              dot={{ fill: "hsl(var(--primary))", strokeWidth: 2, r: 4 }}
            />
          </LineChart>
        </ResponsiveContainer>
      </div>

      <div className="grid md:grid-cols-2 gap-4">
        <Card className="border-border bg-muted/30">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Peak Usage Day</span>
              <span className="font-medium">Mar 5</span>
            </div>
            <p className="text-xs text-muted-foreground mt-1">298 credits used</p>
          </CardContent>
        </Card>
        <Card className="border-border bg-muted/30">
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Average Daily</span>
              <span className="font-medium">187 credits</span>
            </div>
            <p className="text-xs text-muted-foreground mt-1">Based on last 30 days</p>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
