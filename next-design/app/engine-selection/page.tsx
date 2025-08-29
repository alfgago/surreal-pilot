import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Gamepad2, Code, Globe, Zap, Users } from "lucide-react"
import Link from "next/link"

export default function EngineSelectionPage() {
  return (
    <div className="min-h-screen bg-background">
      <div className="container mx-auto px-4 py-12 max-w-4xl">
        <div className="text-center mb-12">
          <div className="flex items-center justify-center mb-6">
            <div className="w-16 h-16 bg-primary rounded-xl flex items-center justify-center">
              <Gamepad2 className="w-8 h-8 text-primary-foreground" />
            </div>
          </div>
          <h1 className="text-3xl md:text-4xl font-serif font-black text-foreground mb-4">Choose Your Game Engine</h1>
          <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
            Select the game engine you'll be working with. You can always switch or add more engines later.
          </p>
        </div>

        <div className="grid md:grid-cols-2 gap-8 mb-12">
          {/* Unreal Engine Card */}
          <Card className="border-border bg-card hover:bg-card/80 transition-all duration-200 hover:scale-[1.02] cursor-pointer">
            <CardHeader className="text-center pb-4">
              <div className="w-20 h-20 bg-gradient-to-br from-primary to-accent rounded-xl flex items-center justify-center mx-auto mb-4">
                <Code className="w-10 h-10 text-primary-foreground" />
              </div>
              <CardTitle className="text-2xl font-serif font-black">Unreal Engine</CardTitle>
              <CardDescription className="text-base">
                Professional game development with C++ and Blueprint support
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex flex-wrap gap-2">
                <Badge variant="secondary">C++</Badge>
                <Badge variant="secondary">Blueprints</Badge>
                <Badge variant="secondary">AAA Games</Badge>
                <Badge variant="secondary">VR/AR</Badge>
              </div>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li className="flex items-center">
                  <Zap className="w-4 h-4 mr-2 text-primary" />
                  Context-aware code suggestions
                </li>
                <li className="flex items-center">
                  <Code className="w-4 h-4 mr-2 text-primary" />
                  Blueprint and C++ integration
                </li>
                <li className="flex items-center">
                  <Users className="w-4 h-4 mr-2 text-primary" />
                  Project file management
                </li>
              </ul>
              <Button className="w-full" size="lg" asChild>
                <Link href="/workspace-selection?engine=unreal">Choose Unreal Engine</Link>
              </Button>
            </CardContent>
          </Card>

          {/* PlayCanvas Card */}
          <Card className="border-border bg-card hover:bg-card/80 transition-all duration-200 hover:scale-[1.02] cursor-pointer">
            <CardHeader className="text-center pb-4">
              <div className="w-20 h-20 bg-gradient-to-br from-accent to-primary rounded-xl flex items-center justify-center mx-auto mb-4">
                <Globe className="w-10 h-10 text-primary-foreground" />
              </div>
              <CardTitle className="text-2xl font-serif font-black">PlayCanvas</CardTitle>
              <CardDescription className="text-base">Web and mobile game development with live preview</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex flex-wrap gap-2">
                <Badge variant="secondary">JavaScript</Badge>
                <Badge variant="secondary">WebGL</Badge>
                <Badge variant="secondary">Mobile</Badge>
                <Badge variant="secondary">Web</Badge>
              </div>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li className="flex items-center">
                  <Globe className="w-4 h-4 mr-2 text-primary" />
                  Live game preview
                </li>
                <li className="flex items-center">
                  <Zap className="w-4 h-4 mr-2 text-primary" />
                  Real-time collaboration
                </li>
                <li className="flex items-center">
                  <Code className="w-4 h-4 mr-2 text-primary" />
                  Scene hierarchy management
                </li>
              </ul>
              <Button className="w-full" size="lg" asChild>
                <Link href="/workspace-selection?engine=playcanvas">Choose PlayCanvas</Link>
              </Button>
            </CardContent>
          </Card>
        </div>

        <div className="text-center">
          <p className="text-sm text-muted-foreground mb-4">
            Not sure which engine to choose? You can always change this later in your settings.
          </p>
          <Button variant="outline" asChild>
            <Link href="/workspace-selection">Skip for now</Link>
          </Button>
        </div>
      </div>
    </div>
  )
}
