"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Badge } from "@/components/ui/badge"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { TeamInviteModal } from "@/components/company/team-invite-modal"
import { Building, Users, Plus, MoreHorizontal, Crown, Shield, User, Trash2, Mail, ArrowLeft, Save } from "lucide-react"
import Link from "next/link"

interface TeamMember {
  id: string
  name: string
  email: string
  role: "owner" | "admin" | "developer" | "viewer"
  avatar?: string
  joinedAt: Date
  lastActive: Date
}

const mockTeamMembers: TeamMember[] = [
  {
    id: "1",
    name: "John Doe",
    email: "john@example.com",
    role: "owner",
    joinedAt: new Date(Date.now() - 86400000 * 30),
    lastActive: new Date(Date.now() - 3600000),
  },
  {
    id: "2",
    name: "Alice Smith",
    email: "alice@example.com",
    role: "admin",
    joinedAt: new Date(Date.now() - 86400000 * 15),
    lastActive: new Date(Date.now() - 7200000),
  },
  {
    id: "3",
    name: "Bob Johnson",
    email: "bob@example.com",
    role: "developer",
    joinedAt: new Date(Date.now() - 86400000 * 7),
    lastActive: new Date(Date.now() - 14400000),
  },
  {
    id: "4",
    name: "Carol Wilson",
    email: "carol@example.com",
    role: "developer",
    joinedAt: new Date(Date.now() - 86400000 * 3),
    lastActive: new Date(Date.now() - 28800000),
  },
]

export default function CompanySettingsPage() {
  const [company, setCompany] = useState({
    name: "Indie Game Studio",
    description: "Creating innovative games with cutting-edge technology",
    website: "https://indiegamestudio.com",
    industry: "gaming",
    size: "small",
  })

  const [teamMembers, setTeamMembers] = useState<TeamMember[]>(mockTeamMembers)
  const [showInviteModal, setShowInviteModal] = useState(false)

  const getRoleIcon = (role: string) => {
    switch (role) {
      case "owner":
        return <Crown className="w-4 h-4 text-yellow-500" />
      case "admin":
        return <Shield className="w-4 h-4 text-blue-500" />
      default:
        return <User className="w-4 h-4 text-muted-foreground" />
    }
  }

  const getRoleBadgeColor = (role: string) => {
    switch (role) {
      case "owner":
        return "bg-yellow-500/10 text-yellow-500"
      case "admin":
        return "bg-blue-500/10 text-blue-500"
      case "developer":
        return "bg-primary/10 text-primary"
      case "viewer":
        return "bg-muted text-muted-foreground"
      default:
        return "bg-muted text-muted-foreground"
    }
  }

  const handleSave = () => {
    console.log("Saving company settings:", company)
  }

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b border-border bg-card/50 backdrop-blur-sm">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <Link
                href="/chat"
                className="flex items-center space-x-2 text-muted-foreground hover:text-foreground transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                <span>Back to Chat</span>
              </Link>
              <div className="flex items-center space-x-3">
                <div className="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
                  <Building className="w-5 h-5 text-primary-foreground" />
                </div>
                <div>
                  <h1 className="text-xl font-serif font-black text-foreground">Company Settings</h1>
                  <p className="text-sm text-muted-foreground">Manage your company and team</p>
                </div>
              </div>
            </div>
            <Button onClick={handleSave}>
              <Save className="w-4 h-4 mr-2" />
              Save Changes
            </Button>
          </div>
        </div>
      </header>

      <div className="container mx-auto px-4 py-8 max-w-6xl">
        <div className="grid lg:grid-cols-3 gap-8">
          {/* Company Info */}
          <div className="lg:col-span-2 space-y-6">
            <Card className="border-border bg-card">
              <CardHeader>
                <CardTitle className="font-serif font-bold">Company Information</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="companyName">Company Name</Label>
                  <Input
                    id="companyName"
                    value={company.name}
                    onChange={(e) => setCompany({ ...company, name: e.target.value })}
                    className="bg-input border-border"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="description">Description</Label>
                  <Textarea
                    id="description"
                    value={company.description}
                    onChange={(e) => setCompany({ ...company, description: e.target.value })}
                    className="bg-input border-border min-h-[80px]"
                    placeholder="Describe your company..."
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="website">Website</Label>
                  <Input
                    id="website"
                    type="url"
                    value={company.website}
                    onChange={(e) => setCompany({ ...company, website: e.target.value })}
                    className="bg-input border-border"
                    placeholder="https://yourcompany.com"
                  />
                </div>
                <div className="grid md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="industry">Industry</Label>
                    <Select
                      value={company.industry}
                      onValueChange={(value) => setCompany({ ...company, industry: value })}
                    >
                      <SelectTrigger className="bg-input border-border">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="gaming">Gaming</SelectItem>
                        <SelectItem value="entertainment">Entertainment</SelectItem>
                        <SelectItem value="education">Education</SelectItem>
                        <SelectItem value="simulation">Simulation</SelectItem>
                        <SelectItem value="other">Other</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="size">Company Size</Label>
                    <Select value={company.size} onValueChange={(value) => setCompany({ ...company, size: value })}>
                      <SelectTrigger className="bg-input border-border">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="solo">Solo (1)</SelectItem>
                        <SelectItem value="small">Small (2-10)</SelectItem>
                        <SelectItem value="medium">Medium (11-50)</SelectItem>
                        <SelectItem value="large">Large (51+)</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Team Management */}
            <Card className="border-border bg-card">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <Users className="w-5 h-5 text-primary" />
                    <CardTitle className="font-serif font-bold">Team Members</CardTitle>
                  </div>
                  <Button onClick={() => setShowInviteModal(true)}>
                    <Plus className="w-4 h-4 mr-2" />
                    Invite Member
                  </Button>
                </div>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {teamMembers.map((member) => (
                    <div
                      key={member.id}
                      className="flex items-center justify-between p-4 border border-border rounded-lg"
                    >
                      <div className="flex items-center space-x-4">
                        <Avatar className="w-10 h-10">
                          <AvatarImage src={member.avatar || "/placeholder.svg"} />
                          <AvatarFallback className="bg-secondary text-secondary-foreground">
                            {member.name
                              .split(" ")
                              .map((n) => n[0])
                              .join("")}
                          </AvatarFallback>
                        </Avatar>
                        <div className="flex-1">
                          <div className="flex items-center space-x-2">
                            <h4 className="font-medium text-foreground">{member.name}</h4>
                            {getRoleIcon(member.role)}
                          </div>
                          <p className="text-sm text-muted-foreground">{member.email}</p>
                          <div className="flex items-center space-x-2 mt-1">
                            <Badge className={`text-xs ${getRoleBadgeColor(member.role)}`}>{member.role}</Badge>
                            <span className="text-xs text-muted-foreground">
                              Last active {member.lastActive.toLocaleDateString()}
                            </span>
                          </div>
                        </div>
                      </div>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            <MoreHorizontal className="w-4 h-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem>Change Role</DropdownMenuItem>
                          <DropdownMenuItem>
                            <Mail className="w-4 h-4 mr-2" />
                            Send Message
                          </DropdownMenuItem>
                          {member.role !== "owner" && (
                            <DropdownMenuItem className="text-destructive">
                              <Trash2 className="w-4 h-4 mr-2" />
                              Remove Member
                            </DropdownMenuItem>
                          )}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Company Stats */}
          <div className="space-y-6">
            <Card className="border-border bg-card">
              <CardHeader>
                <CardTitle className="font-serif font-bold text-sm">Company Overview</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="text-center">
                  <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                    <Building className="w-8 h-8 text-primary" />
                  </div>
                  <h3 className="font-serif font-bold text-foreground">{company.name}</h3>
                  <p className="text-sm text-muted-foreground">{company.industry}</p>
                </div>
                <div className="space-y-3">
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Team Size</span>
                    <span className="font-medium">{teamMembers.length} members</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Active Projects</span>
                    <span className="font-medium">8</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Total Games</span>
                    <span className="font-medium">24</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Credits Used</span>
                    <span className="font-medium">1,247 / 2,000</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card className="border-border bg-card">
              <CardHeader>
                <CardTitle className="font-serif font-bold text-sm">Quick Actions</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <Button variant="outline" className="w-full justify-start bg-transparent" asChild>
                  <Link href="/company/provider-settings">
                    <Shield className="w-4 h-4 mr-2" />
                    Provider Settings
                  </Link>
                </Button>
                <Button variant="outline" className="w-full justify-start bg-transparent" asChild>
                  <Link href="/company/billing">
                    <Building className="w-4 h-4 mr-2" />
                    Billing & Usage
                  </Link>
                </Button>
                <Button variant="outline" className="w-full justify-start bg-transparent">
                  <Users className="w-4 h-4 mr-2" />
                  Export Team Data
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <TeamInviteModal open={showInviteModal} onOpenChange={setShowInviteModal} />
    </div>
  )
}
