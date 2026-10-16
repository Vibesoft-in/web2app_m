import type { Metadata, Viewport } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Turn Your Website into iOS & Android Mobile Apps — AppifyHub",
  description: "Turn your WordPress, WooCommerce or Shopify website into a beautiful mobile app — quickly, easily and without coding.",
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  maximumScale: 5,
};

import { Providers } from "@/components/providers/query-provider";

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <head>
        <link rel="stylesheet" href="/assets/css/app.css" />
      </head>
      <body className="antialiased site-layout">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}

