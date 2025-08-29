"use client"

import React from "react"
import { useAuth } from "@/hooks/useAuth"
import { useSettings } from "@/hooks/useSettings"
import { ToastContainer } from "react-toastify"
import "react-toastify/dist/ReactToastify.css"

interface Props {
  children: React.ReactNode
}

export default function ClientLayout({ children }: Props) {
  const { auth } = useAuth()
  const { settings } = useSettings()

  React.useEffect(() => {
    // Initial auth check on app load
    // Global settings/preferences fetch
  }, [])

  return (
    <body className="font-sans antialiased">
      {children}
      <ToastContainer />
    </body>
  )
}
