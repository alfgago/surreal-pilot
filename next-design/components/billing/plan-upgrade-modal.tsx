"use client"

import type React from "react"

import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog"
import { CheckCircle, Star } from "lucide-react"

interface Plan {
  id: string
  name: string
  price: number
  credits: number
  features: string[]
  icon: React.ReactNode
  popular?: boolean
}

interface PlanUpgradeModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  plans: Plan[]
}

export function PlanUpgradeModal({ open, onOpenChange, plans }: PlanUpgradeModalProps) {
  const handleSelectPlan = (planId: string) => {
    console.log("Selected plan:", planId)
    onOpenChange(false)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-4xl">
        <DialogHeader>
          <DialogTitle className="font-serif font-bold">Choose Your Plan</DialogTitle>
          <DialogDescription>Select the plan that best fits your team's needs</DialogDescription>
        </DialogHeader>

        <div className="grid md:grid-cols-3 gap-6 py-6">
          {plans.map((plan) => (
            <div
              key={plan.id}
              className={`relative p-6 border rounded-lg transition-all hover:shadow-lg ${
                plan.popular ? "border-primary bg-primary/5" : "border-border bg-card"
              }`}
            >
              {plan.popular && (
                <Badge className="absolute -top-2 left-1/2 -translate-x-1/2 bg-primary text-primary-foreground">
                  <Star className="w-3 h-3 mr-1" />
                  Most Popular
                </Badge>
              )}

              <div className="text-center mb-6">
                <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mx-auto mb-4">
                  {plan.icon}
                </div>
                <h3 className="text-xl font-serif font-bold text-foreground mb-2">{plan.name}</h3>
                <div className="mb-4">
                  <span className="text-3xl font-bold">${plan.price}</span>
                  <span className="text-muted-foreground">/month</span>
                </div>
                <p className="text-sm text-muted-foreground">{plan.credits.toLocaleString()} AI credits per month</p>
              </div>

              <ul className="space-y-3 mb-6">
                {plan.features.map((feature, index) => (
                  <li key={index} className="flex items-center text-sm">
                    <CheckCircle className="w-4 h-4 text-green-500 mr-2 flex-shrink-0" />
                    {feature}
                  </li>
                ))}
              </ul>

              <Button
                className="w-full"
                variant={plan.popular ? "default" : "outline"}
                onClick={() => handleSelectPlan(plan.id)}
              >
                {plan.price === 0 ? "Get Started" : "Upgrade to " + plan.name}
              </Button>
            </div>
          ))}
        </div>

        <div className="text-center text-sm text-muted-foreground">
          <p>All plans include a 14-day free trial. Cancel anytime.</p>
        </div>
      </DialogContent>
    </Dialog>
  )
}
