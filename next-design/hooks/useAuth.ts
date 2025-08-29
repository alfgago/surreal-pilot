"use client"

/**
 * AUTHENTICATION HOOK - Manages user authentication state
 *
 * PURPOSE:
 * - Provide authentication state and methods across the app
 * - Handle login, logout, and user session management
 * - Integrate with Inertia.js authentication flow
 *
 * INERTIA.JS INTEGRATION:
 * - Should use Inertia's shared data for auth state
 * - Login/logout should use Inertia.post/delete for server-side auth
 * - User data comes from Laravel backend via Inertia props
 */

import { useState, useEffect } from "react"

interface User {
  id: string
  name: string
  email: string
  avatar?: string
  role: "owner" | "developer" | "reviewer"
  company?: {
    id: string
    name: string
  }
}

interface AuthState {
  user: User | null
  isAuthenticated: boolean
  isLoading: boolean
}

export function useAuth() {
  const [auth, setAuth] = useState<AuthState>({
    user: null,
    isAuthenticated: false,
    isLoading: true,
  })

  useEffect(() => {
    // In real Inertia.js app, this would come from shared data:
    // const { auth } = usePage().props

    // Simulate auth check
    const checkAuth = async () => {
      try {
        // Real implementation: const response = await fetch('/api/user')
        // For now, simulate logged in user
        const mockUser: User = {
          id: "1",
          name: "John Developer",
          email: "john@example.com",
          avatar: "/diverse-user-avatars.png",
          role: "developer",
          company: {
            id: "1",
            name: "Game Studio Inc",
          },
        }

        setAuth({
          user: mockUser,
          isAuthenticated: true,
          isLoading: false,
        })
      } catch (error) {
        setAuth({
          user: null,
          isAuthenticated: false,
          isLoading: false,
        })
      }
    }

    checkAuth()
  }, [])

  const login = async (email: string, password: string) => {
    // Real implementation: Inertia.post('/login', { email, password })
    console.log("Login attempt:", email)
  }

  const logout = async () => {
    // Real implementation: Inertia.delete('/logout')
    setAuth({
      user: null,
      isAuthenticated: false,
      isLoading: false,
    })
  }

  return {
    ...auth,
    auth, // For backward compatibility
    login,
    logout,
  }
}
