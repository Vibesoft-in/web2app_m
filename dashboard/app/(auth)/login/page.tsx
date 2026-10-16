export const dynamic = "force-dynamic";

import { Suspense } from "react";
import { Loader2 } from "lucide-react";
import { LoginClient } from "./login-client";

interface LoginPageProps {
  searchParams?: {
    theme?: string;
  };
}

export default function LoginPage({ searchParams }: LoginPageProps) {
  const initialTheme = searchParams?.theme;

  return (
    <Suspense
      fallback={
        <div className="min-h-screen flex items-center justify-center bg-slate-50 p-4">
          <div className="flex flex-col items-center gap-3">
            <Loader2 className="w-8 h-8 animate-spin text-amber-800" />
            <span className="text-xs font-semibold text-slate-500">Loading sign in...</span>
          </div>
        </div>
      }
    >
      <LoginClient initialTheme={initialTheme} />
    </Suspense>
  );
}
