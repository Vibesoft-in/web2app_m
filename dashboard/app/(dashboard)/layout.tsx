"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import { Layers, LogOut, Plus, ShieldCheck, Sparkles } from "lucide-react";

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const pathname = usePathname();

  const { data: userData } = useQuery({
    queryKey: ["auth", "me"],
    queryFn: () => apiClient("/api/auth/me"),
    retry: false,
  });

  const handleLogout = async () => {
    try {
      await apiClient("/api/auth/logout", { method: "POST" });
    } finally {
      router.push("/login");
      router.refresh();
    }
  };

  const user = userData?.user;

  const isBilling = pathname.startsWith("/dashboard/billing");
  const isSettings = pathname.startsWith("/dashboard/settings");
  const isAddons = pathname.startsWith("/dashboard/addons");
  const isApps = !isBilling && !isSettings && !isAddons;

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col w-full overflow-x-hidden">
      <header className="bg-white border-b border-slate-200 sticky top-0 z-40 w-full">
        <div className="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between">
          <div className="flex items-center gap-4 sm:gap-8">
            <Link href="/dashboard" className="flex items-center gap-2">
              <div className="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 flex items-center justify-center text-white shadow-md shadow-indigo-600/20 flex-shrink-0">
                <Sparkles className="w-4 h-4 sm:w-5 sm:h-5" />
              </div>
              <span className="font-bold text-base sm:text-lg text-slate-900 tracking-tight">
                WebIntoApp
              </span>
            </Link>

            <nav className="hidden md:flex items-center gap-6 text-sm font-medium">
              <Link
                href="/dashboard"
                className={`py-5 transition ${
                  isApps
                    ? "text-indigo-600 border-b-2 border-indigo-600 font-semibold"
                    : "text-slate-500 hover:text-slate-900"
                }`}
              >
                Dashboard
              </Link>
              <Link
                href="/dashboard/addons"
                className={`py-5 transition ${
                  isAddons
                    ? "text-indigo-600 border-b-2 border-indigo-600 font-semibold"
                    : "text-slate-500 hover:text-slate-900"
                }`}
              >
                Add-ons &amp; Store
              </Link>
              <Link
                href="/dashboard/billing"
                className={`py-5 transition ${
                  isBilling
                    ? "text-indigo-600 border-b-2 border-indigo-600 font-semibold"
                    : "text-slate-500 hover:text-slate-900"
                }`}
              >
                Billing &amp; Invoices
              </Link>
              <Link
                href="/dashboard/settings"
                className={`py-5 transition ${
                  isSettings
                    ? "text-indigo-600 border-b-2 border-indigo-600 font-semibold"
                    : "text-slate-500 hover:text-slate-900"
                }`}
              >
                Settings
              </Link>
            </nav>
          </div>

          <div className="flex items-center gap-2 sm:gap-4">
            <Link
              href="/dashboard/apps/new"
              className="inline-flex items-center gap-1 sm:gap-1.5 px-2.5 py-1.5 sm:px-3.5 sm:py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-semibold transition shadow-sm flex-shrink-0"
            >
              <Plus className="w-3.5 h-3.5 sm:w-4 sm:h-4" />
              <span className="hidden sm:inline">Create App</span>
            </Link>

            <div className="hidden sm:block h-6 w-px bg-slate-200" />

            <div className="flex items-center gap-1 sm:gap-3">
              <div className="hidden sm:block text-right">
                <p className="text-xs font-semibold text-slate-900 truncate max-w-[150px]">
                  {user?.name || "Client"}
                </p>
                <p className="text-xs text-slate-500 truncate max-w-[150px]">{user?.email || ""}</p>
              </div>

              <button
                onClick={handleLogout}
                title="Log out"
                className="p-1.5 sm:p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
              >
                <LogOut className="w-4 h-4 sm:w-5 sm:h-5" />
              </button>
            </div>
          </div>
        </div>
      </header>

      <main className="flex-1 max-w-7xl w-full mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8 pb-24 md:pb-8 overflow-x-hidden">
        {children}
      </main>

      {/* Modern Mobile Bottom Navigation Bar (Phone View) */}
      <nav className="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-lg border-t border-slate-200 px-2 py-2 flex items-center justify-around shadow-lg shadow-slate-900/10">
        <Link
          href="/dashboard"
          className={`flex flex-col items-center gap-0.5 text-[10px] font-semibold transition px-2 py-1 rounded-xl ${
            isApps ? "text-indigo-600 font-bold" : "text-slate-400 hover:text-slate-600"
          }`}
        >
          <Layers className="w-5 h-5" />
          <span>Apps</span>
        </Link>

        <Link
          href="/dashboard/addons"
          className={`flex flex-col items-center gap-0.5 text-[10px] font-semibold transition px-2 py-1 rounded-xl ${
            isAddons ? "text-indigo-600 font-bold" : "text-slate-400 hover:text-slate-600"
          }`}
        >
          <Sparkles className="w-5 h-5" />
          <span>Add-ons</span>
        </Link>

        <Link
          href="/dashboard/apps/new"
          className="flex flex-col items-center gap-0.5 text-[10px] font-semibold text-indigo-600 px-1 py-1 -mt-4"
        >
          <div className="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-600/30">
            <Plus className="w-5 h-5" />
          </div>
          <span>New</span>
        </Link>

        <Link
          href="/dashboard/billing"
          className={`flex flex-col items-center gap-0.5 text-[10px] font-semibold transition px-2 py-1 rounded-xl ${
            isBilling ? "text-indigo-600 font-bold" : "text-slate-400 hover:text-slate-600"
          }`}
        >
          <ShieldCheck className="w-5 h-5" />
          <span>Billing</span>
        </Link>

        <Link
          href="/dashboard/settings"
          className={`flex flex-col items-center gap-0.5 text-[10px] font-semibold transition px-2 py-1 rounded-xl ${
            isSettings ? "text-indigo-600 font-bold" : "text-slate-400 hover:text-slate-600"
          }`}
        >
          <LogOut className="w-5 h-5" />
          <span>Account</span>
        </Link>
      </nav>
    </div>
  );
}
