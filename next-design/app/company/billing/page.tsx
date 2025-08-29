"use client"

import type React from "react"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Progress } from "@/components/ui/progress"
import { Separator } from "@/components/ui/separator"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { UsageChart } from "@/components/billing/usage-chart"
import { PaymentMethods } from "@/components/billing/payment-methods"
import { BillingHistory } from "@/components/billing/billing-history"
import { PlanUpgradeModal } from "@/components/billing/plan-upgrade-modal"
import {
  CreditCard,
  TrendingUp,
  Calendar,
  Zap,
  Users,
  ArrowLeft,
  Crown,
  Star,
  Rocket,
  AlertTriangle,
  CheckCircle,
} from "lucide-react"
import Link from "next/link"

interface Plan {
  id: string
  name: string
  price: number
  credits: number
  features: string[]
  icon: React.ReactNode
  popular?: boolean
}

const plans: Plan[] = [
  {
    id: "starter",
    name: "Starter",
    price: 0,
    credits: 500,
    features: ["500 AI credits/month", "2 team members", "Basic support", "Community access"],
    icon: <Star className="w-5 h-5" />,
  },
  {
    id: "pro",
    name: "Pro",
    price: 29,
    credits: 2000,
    features: [
      "2,000 AI credits/month",
      "10 team members",
      "Priority support",
      "Advanced analytics",
      "Custom integrations",
    ],
    icon: <Rocket className="w-5 h-5" />,
    popular: true,
  },
  {
    id: "enterprise",
    name: "Enterprise",
    price: 99,
    credits: 10000,
    features: [
      "10,000 AI credits/month",
      "Unlimited team members",
      "24/7 dedicated support",
      "Custom deployment",
      "SLA guarantee",
    ],
    icon: <Crown className="w-5 h-5" />,
  },
]

export default function BillingPage() {
  const [currentPlan] = useState("pro")
  const [showUpgradeModal, setShowUpgradeModal] = useState(false)
  const [usageData] = useState({
    creditsUsed: 1247,
    creditsTotal: 2000,
    teamMembers: 4,
    teamLimit: 10,
    billingCycle: "monthly",
    nextBilling: new Date(Date.now() + 86400000 * 15),
  })

  const currentPlanData = plans.find((p) => p.id === currentPlan)
  const creditsPercentage = (usageData.creditsUsed / usageData.creditsTotal) * 100

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
                  <CreditCard className="w-5 h-5 text-primary-foreground" />
                </div>
                <div>
                  <h1 className="text-xl font-serif font-black text-foreground">Billing & Usage</h1>
                  <p className="text-sm text-muted-foreground">Manage your subscription and usage</p>
                </div>
              </div>
            </div>
            <Button onClick={() => setShowUpgradeModal(true)}>Upgrade Plan</Button>
          </div>
        </div>
      </header>

      <div className="container mx-auto px-4 py-8 max-w-6xl">
        <div className="grid lg:grid-cols-3 gap-8">
          {/* Current Plan & Usage */}
          <div className="lg:col-span-2 space-y-6">
            {/* Current Plan */}
            <Card className="border-border bg-card">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="font-serif font-bold">Current Plan</CardTitle>
                  <Badge className="bg-primary/10 text-primary">Active</Badge>
                </div>
              </CardHeader>
              <CardContent>
                <div className="flex items-center space-x-4 mb-6">
                  <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                    {currentPlanData?.icon}
                  </div>
                  <div>
                    <h3 className="text-2xl font-serif font-black text-foreground">{currentPlanData?.name}</h3>
                    <p className="text-muted-foreground">
                      ${currentPlanData?.price}/{usageData.billingCycle === "monthly" ? "month" : "year"}
                    </p>
                  </div>
                </div>

                <div className="grid md:grid-cols-2 gap-6">
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium">AI Credits</span>
                      <span className="text-sm text-muted-foreground">
                        {usageData.creditsUsed.toLocaleString()} / {usageData.creditsTotal.toLocaleString()}
                      </span>
                    </div>
                    <Progress value={creditsPercentage} className="mb-2" />
                    <p className="text-xs text-muted-foreground">
                      {creditsPercentage > 80 ? (
                        <span className="text-yellow-500">
                          <AlertTriangle className="w-3 h-3 inline mr-1" />
                          Running low on credits
                        </span>
                      ) : (
                        <span className="text-green-500">
                          <CheckCircle className="w-3 h-3 inline mr-1" />
                          Good usage level
                        </span>
                      )}
                    </p>
                  </div>

                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium">Team Members</span>
                      <span className="text-sm text-muted-foreground">
                        {usageData.teamMembers} / {usageData.teamLimit}
                      </span>
                    </div>
                    <Progress value={(usageData.teamMembers / usageData.teamLimit) * 100} className="mb-2" />
                    <p className="text-xs text-muted-foreground">
                      {usageData.teamLimit - usageData.teamMembers} slots available
                    </p>
                  </div>
                </div>

                <Separator className="my-6" />

                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium">Next billing date</p>
                    <p className="text-sm text-muted-foreground">{usageData.nextBilling.toLocaleDateString()}</p>
                  </div>
                  <Button variant="outline">Manage Subscription</Button>
                </div>
              </CardContent>
            </Card>

            {/* Usage Analytics */}
            <Card className="border-border bg-card">
              <CardHeader>
                <div className="flex items-center space-x-2">
                  <TrendingUp className="w-5 h-5 text-primary" />
                  <CardTitle className="font-serif font-bold">Usage Analytics</CardTitle>
                </div>
              </CardHeader>
              <CardContent>
                <UsageChart />
              </CardContent>
            </Card>

            {/* Tabs for detailed views */}
            <Tabs defaultValue="history" className="w-full">
              <TabsList className="grid w-full grid-cols-2">
                <TabsTrigger value="history">Billing History</TabsTrigger>
                <TabsTrigger value="payments">Payment Methods</TabsTrigger>
              </TabsList>
              <TabsContent value="history">
                <BillingHistory />
              </TabsContent>
              <TabsContent value="payments">
                <PaymentMethods />
              </TabsContent>
            </Tabs>
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            {/* Quick Stats */}
            <Card className="border-border bg-card">
              <CardHeader>
                <CardTitle className="font-serif font-bold text-sm">This Month</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <Zap className="w-4 h-4 text-primary" />
                    <span className="text-sm">Credits Used</span>
                  </div>
                  <span className="font-medium">{usageData.creditsUsed.toLocaleString()}</span>
                </div>
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <Users className="w-4 h-4 text-primary" />
                    <span className="text-sm">Active Users</span>
                  </div>
                  <span className="font-medium">{usageData.teamMembers}</span>
                </div>
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <Calendar className="w-4 h-4 text-primary" />
                    <span className="text-sm">Days Left</span>
                  </div>
                  <span className="font-medium">15</span>
                </div>
              </CardContent>
            </Card>

            {/* Available Plans */}
            <Card className="border-border bg-card">
              <CardHeader>
                <CardTitle className="font-serif font-bold text-sm">Available Plans</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {plans.map((plan) => (
                  <div
                    key={plan.id}
                    className={`p-3 rounded-lg border transition-colors ${
                      plan.id === currentPlan
                        ? "border-primary bg-primary/5"
                        : "border-border hover:bg-muted/50 cursor-pointer"
                    }`}
                    onClick={() => plan.id !== currentPlan && setShowUpgradeModal(true)}
                  >
                    <div className="flex items-center justify-between mb-2">
                      <div className="flex items-center space-x-2">
                        <div className="w-6 h-6 bg-primary/10 rounded flex items-center justify-center">
                          {plan.icon}
                        </div>
                        <span className="font-medium text-sm">{plan.name}</span>
                      </div>
                      {plan.id === currentPlan ? (
                        <Badge variant="secondary" className="text-xs">
                          Current
                        </Badge>
                      ) : (
                        <span className="text-sm font-medium">${plan.price}/mo</span>
                      )}
                    </div>
                    <p className="text-xs text-muted-foreground">{plan.credits.toLocaleString()} credits/month</p>
                  </div>
                ))}
              </CardContent>
            </Card>

            {/* Support */}
            <Card className="border-border bg-card">
              <CardHeader>
                <CardTitle className="font-serif font-bold text-sm">Need Help?</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <Button variant="outline" className="w-full justify-start bg-transparent">
                  Contact Support
                </Button>
                <Button variant="outline" className="w-full justify-start bg-transparent">
                  View Documentation
                </Button>
                <Button variant="outline" className="w-full justify-start bg-transparent">
                  Request Feature
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <PlanUpgradeModal open={showUpgradeModal} onOpenChange={setShowUpgradeModal} plans={plans} />
    </div>
  )
}
