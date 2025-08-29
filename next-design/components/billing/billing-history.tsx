"use client"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Download, Receipt } from "lucide-react"

interface Invoice {
  id: string
  date: Date
  amount: number
  status: "paid" | "pending" | "failed"
  description: string
  downloadUrl?: string
}

const invoices: Invoice[] = [
  {
    id: "inv_001",
    date: new Date(Date.now() - 86400000 * 5),
    amount: 29.0,
    status: "paid",
    description: "Pro Plan - March 2024",
    downloadUrl: "#",
  },
  {
    id: "inv_002",
    date: new Date(Date.now() - 86400000 * 35),
    amount: 29.0,
    status: "paid",
    description: "Pro Plan - February 2024",
    downloadUrl: "#",
  },
  {
    id: "inv_003",
    date: new Date(Date.now() - 86400000 * 65),
    amount: 29.0,
    status: "paid",
    description: "Pro Plan - January 2024",
    downloadUrl: "#",
  },
  {
    id: "inv_004",
    date: new Date(Date.now() - 86400000 * 95),
    amount: 0.0,
    status: "paid",
    description: "Starter Plan - December 2023",
  },
]

export function BillingHistory() {
  const getStatusColor = (status: string) => {
    switch (status) {
      case "paid":
        return "bg-green-500/10 text-green-500"
      case "pending":
        return "bg-yellow-500/10 text-yellow-500"
      case "failed":
        return "bg-red-500/10 text-red-500"
      default:
        return "bg-muted text-muted-foreground"
    }
  }

  return (
    <Card className="border-border bg-card">
      <CardHeader>
        <CardTitle className="font-serif font-bold">Billing History</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {invoices.map((invoice) => (
            <div key={invoice.id} className="flex items-center justify-between p-4 border border-border rounded-lg">
              <div className="flex items-center space-x-4">
                <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                  <Receipt className="w-5 h-5 text-primary" />
                </div>
                <div>
                  <h4 className="font-medium text-foreground">{invoice.description}</h4>
                  <div className="flex items-center space-x-2 mt-1">
                    <span className="text-sm text-muted-foreground">{invoice.date.toLocaleDateString()}</span>
                    <Badge className={`text-xs ${getStatusColor(invoice.status)}`}>{invoice.status}</Badge>
                  </div>
                </div>
              </div>
              <div className="flex items-center space-x-4">
                <span className="font-medium">${invoice.amount.toFixed(2)}</span>
                {invoice.downloadUrl && (
                  <Button variant="outline" size="sm">
                    <Download className="w-3 h-3 mr-1" />
                    PDF
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>

        {invoices.length === 0 && (
          <div className="text-center py-8">
            <Receipt className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
            <h3 className="font-medium text-foreground mb-2">No billing history</h3>
            <p className="text-sm text-muted-foreground">Your invoices will appear here once you start a paid plan</p>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
