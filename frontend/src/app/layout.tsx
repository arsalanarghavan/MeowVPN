import type { Metadata } from "next"

export const metadata: Metadata = {
  title: "MeowVPN",
  description: "MeowVPN admin dashboard",
}

/** Root shell — locale layouts own <html>/<body>. */
export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return children
}
