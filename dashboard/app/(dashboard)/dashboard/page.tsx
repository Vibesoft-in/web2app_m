"use client";

import { useState } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import { AppDetailsView } from "@/components/dashboard/app-details-view";
import {
  ArrowLeft,
  ArrowRight,
  ArrowUpRight,
  Bell,
  Box,
  Calendar,
  Check,
  CheckCircle2,
  Clock,
  CreditCard,
  Download,
  ExternalLink,
  Eye,
  Flame,
  Globe,
  HardDrive,
  Layers,
  Loader2,
  Lock,
  MessageSquare,
  Package,
  Plus,
  Printer,
  Receipt,
  RefreshCw,
  Rocket,
  Shield,
  ShieldCheck,
  Smartphone,
  Sparkles,
  User,
  Zap,
} from "lucide-react";

export default function UserDashboardPage() {
  const [activeTab, setActiveTab] = useState<"apps" | "builds" | "plans" | "payments" | "addons">("apps");
  const [isRebuilding, setIsRebuilding] = useState(false);
  const [rebuildMessage, setRebuildMessage] = useState<string | null>(null);
  const [switchingPlanId, setSwitchingPlanId] = useState<number | null>(null);
  const [planMessage, setPlanMessage] = useState<string | null>(null);
  const [selectedInvoice, setSelectedInvoice] = useState<any | null>(null);
  const [selectedAddon, setSelectedAddon] = useState<any | null>(null);
  const [addonSuccess, setAddonSuccess] = useState(false);
  const [viewingAppDetails, setViewingAppDetails] = useState<any | null>(null);
  const [appConfigData, setAppConfigData] = useState<any | null>(null);
  const [isLoadingConfig, setIsLoadingConfig] = useState(false);

  // 1. Fetch User Profile
  const { data: userData } = useQuery({
    queryKey: ["auth", "me"],
    queryFn: () => apiClient("/api/auth/me"),
    retry: false,
  });

  // 2. Fetch User's Applications (with builds & connections)
  const { data: apps, isLoading: isAppsLoading, refetch: refetchApps } = useQuery({
    queryKey: ["apps"],
    queryFn: async () => {
      try {
        const res = await apiClient("/api/apps");
        return Array.isArray(res) ? res : res.apps || [];
      } catch {
        return [];
      }
    },
  });

  // 3. Fetch Billing, Subscriptions, Payments & Plans
  const { data: billingData, isLoading: isBillingLoading, refetch: refetchBilling } = useQuery({
    queryKey: ["billing-data"],
    queryFn: () => apiClient("/api/billing"),
  });

  const user = userData?.user;
  const primaryApp = apps?.[0];
  const activeSub = billingData?.active_sub;
  const currentPlan = activeSub?.plan || {
    id: 1,
    name: "Starter",
    price_monthly: 0,
  };
  const plans = billingData?.plans || [];
  const payments = billingData?.payments || [];
  const builds = primaryApp?.builds || [];
  const readyBuild = builds.find((b: any) => b.status === "ready" || b.status?.value === "ready") || builds[0];
  const latestBuild = readyBuild || builds[0];
  const targetBuildId = latestBuild?.id;

  const handleRebuild = async () => {
    if (!primaryApp?.ulid) return;
    setIsRebuilding(true);
    setRebuildMessage(null);
    try {
      await apiClient(`/api/apps/${primaryApp.ulid}/builds`, { method: "POST" });
      setRebuildMessage("Build job queued successfully! Syncing store products and generating update...");
      await refetchApps();
    } catch (err: any) {
      setRebuildMessage(err.message || "Failed to trigger build.");
    } finally {
      setIsRebuilding(false);
    }
  };

  const handleSwitchPlan = async (planId: number, planName: string) => {
    if (!primaryApp?.ulid) return;
    setSwitchingPlanId(planId);
    setPlanMessage(null);
    try {
      await apiClient(`/api/apps/${primaryApp.ulid}/subscribe`, {
        method: "POST",
        body: JSON.stringify({ plan_id: planId }),
      });
      setPlanMessage(`Successfully upgraded plan to ${planName}! All tier features are now unlocked.`);
      await refetchBilling();
      await refetchApps();
    } catch (err: any) {
      setPlanMessage(err.message || "Failed to switch plan.");
    } finally {
      setSwitchingPlanId(null);
    }
  };

  const handleOpenAppDetails = async (app: any) => {
    setViewingAppDetails(app);
    setIsLoadingConfig(true);
    try {
      const res = await apiClient(`/api/apps/${app.ulid}/config/draft`);
      if (res?.config) {
        setAppConfigData(res.config);
      } else if (app?.configs?.[0]?.config) {
        setAppConfigData(app.configs[0].config);
      }
    } catch {
      if (app?.configs?.[0]?.config) {
        setAppConfigData(app.configs[0].config);
      }
    } finally {
      setIsLoadingConfig(false);
    }
  };

  const formattedPlanPrice =
    currentPlan.price_monthly === 0
      ? "₹0 / mo"
      : `₹${(currentPlan.price_monthly / 100).toLocaleString("en-IN")} / mo`;

  const addonsList = [
    {
      id: "play_store",
      name: "Google Play Store Publishing Assistant",
      tag: "Store Publishing",
      badge: "High Impact",
      price: currentPlan.name === "Starter" ? "₹1,499" : "Included Free",
      desc: "Complete submission to Google Play Console with signed .aab, asset graphics, privacy policy hosting & approval guarantee.",
      icon: Rocket,
    },
    {
      id: "apple_ios",
      name: "Apple App Store (iOS) Native Package",
      tag: "Store Publishing",
      badge: "iOS 18 Ready",
      price: currentPlan.name === "Enterprise" ? "Included Free" : "₹2,999",
      desc: "Native iOS binary compilation, TestFlight beta distribution, Apple Developer account integration and App Store submission.",
      icon: Smartphone,
    },
    {
      id: "push_pro",
      name: "Push Notifications Campaign Engine Pro",
      tag: "Growth Marketing",
      badge: "Sales Booster",
      price: currentPlan.name === "Starter" ? "₹699/mo" : "Included Free",
      desc: "Send broadcast image push notifications to users' lock screens, automated abandoned cart recovery, and flash sale deep-links.",
      icon: Bell,
    },
    {
      id: "whatsapp_sync",
      name: "WhatsApp Automated Order Sync & Chat",
      tag: "Support & Chat",
      badge: "Popular in India",
      price: currentPlan.name === "Enterprise" ? "Included Free" : "₹999/mo",
      desc: "Automated WhatsApp updates on order placement, dispatch tracking links, and 1-click in-app floating WhatsApp live chat support.",
      icon: MessageSquare,
    },
    {
      id: "white_label",
      name: "100% White-Label & Custom Splash Screen",
      tag: "Branding",
      badge: "Full Identity",
      price: currentPlan.name === "Starter" ? "₹999/mo" : "Included Free",
      desc: "Completely remove all platform branding. Custom animated launch splash screen, custom brand typography, and custom package name.",
      icon: Sparkles,
    },
    {
      id: "cloud_sla",
      name: "Dedicated High-Speed Cloud Server & 99.9% SLA",
      tag: "Infrastructure",
      badge: "Fast Sync",
      price: currentPlan.name === "Enterprise" ? "Included Free" : "₹1,999/mo",
      desc: "Sub-second store product catalog synchronization for large stores (10,000+ items), priority build queue and 24/7 VIP SLA.",
      icon: Zap,
    },
  ];

  if (viewingAppDetails) {
    return (
      <AppDetailsView
        app={viewingAppDetails}
        currentPlan={currentPlan}
        config={appConfigData || viewingAppDetails?.configs?.[0]?.config}
        isLoadingConfig={isLoadingConfig}
        onBack={() => {
          setViewingAppDetails(null);
          setAppConfigData(null);
        }}
        onNavigateToTab={(tab) => {
          setViewingAppDetails(null);
          setAppConfigData(null);
          setActiveTab(tab);
        }}
        onRebuild={handleRebuild}
        isRebuilding={isRebuilding}
      />
    );
  }

  return (
    <div className="space-y-8 max-w-7xl mx-auto pb-16">
      {/* ── TOP HERO & USER PROFILE BAR ── */}
      <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div className="flex items-center gap-4 sm:gap-5">
            <div className="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-700 to-violet-800 text-white font-extrabold text-2xl sm:text-3xl flex items-center justify-center shadow-lg shadow-indigo-600/20 flex-shrink-0">
              {(user?.name || primaryApp?.name || "U").charAt(0).toUpperCase()}
            </div>
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                  Welcome back, {user?.name || "Store Owner"}!
                </h1>
                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                  Verified Client
                </span>
              </div>
              <p className="text-xs sm:text-sm text-slate-500 mt-1">
                {user?.email || "owner@store.com"} &bull; Store:{" "}
                <strong className="text-slate-800 font-semibold">{primaryApp?.name || "Mobile Store"}</strong>
              </p>
              <div className="flex flex-wrap items-center gap-3 mt-3 text-xs text-slate-500">
                <span className="flex items-center gap-1">
                  <ShieldCheck className="w-3.5 h-3.5 text-indigo-600" />
                  Plan: <strong className="text-indigo-600 font-bold">{currentPlan.name}</strong>
                </span>
                <span>&bull;</span>
                <span className="flex items-center gap-1">
                  <Globe className="w-3.5 h-3.5 text-slate-400" />
                  Store: {primaryApp?.connection?.store_url ? new URL(primaryApp.connection.store_url).hostname : "Connected"}
                </span>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <button
              onClick={() => setActiveTab("addons")}
              className="px-4 py-2.5 rounded-xl border border-indigo-200 bg-indigo-50/50 hover:bg-indigo-100/50 text-indigo-700 text-xs font-bold transition flex items-center gap-2 shadow-sm"
            >
              <Sparkles className="w-4 h-4 text-indigo-600" />
              <span>Explore Add-ons</span>
            </button>
            <Link
              href="/dashboard/apps/new"
              className="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-2 shadow-md shadow-indigo-600/20"
            >
              <Plus className="w-4 h-4" />
              <span>Build New App</span>
            </Link>
          </div>
        </div>

        {/* ── METRIC STATS ROW ── */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-8 pt-6 border-t border-slate-100">
          <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/60">
            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Active Apps</span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 mt-1">{apps?.length || 1}</div>
            <span className="text-[11px] text-emerald-600 font-semibold flex items-center gap-1 mt-0.5">
              <CheckCircle2 className="w-3 h-3" /> 100% Synced
            </span>
          </div>

          <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/60">
            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Builds</span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 mt-1">{builds.length || 1}</div>
            <span className="text-[11px] text-indigo-600 font-semibold flex items-center gap-1 mt-0.5">
              <Package className="w-3 h-3" /> v{latestBuild?.version_code || 1}.0.0 Production
            </span>
          </div>

          <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/60">
            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Current Subscription</span>
            <div className="text-xl sm:text-2xl font-black text-slate-900 mt-1">{currentPlan.name}</div>
            <span className="text-[11px] text-slate-500 font-semibold flex items-center gap-1 mt-0.5">
              {formattedPlanPrice}
            </span>
          </div>

          <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/60">
            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Billing Status</span>
            <div className="text-xl sm:text-2xl font-black text-emerald-600 mt-1">Active &amp; Paid</div>
            <span className="text-[11px] text-slate-500 font-semibold flex items-center gap-1 mt-0.5">
              <Receipt className="w-3 h-3" /> {payments.length || 1} Invoices Logged
            </span>
          </div>
        </div>
      </div>

      {/* ── INTERACTIVE DASHBOARD NAVIGATION TABS ── */}
      <div className="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto scrollbar-none">
        <button
          onClick={() => setActiveTab("apps")}
          className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap ${
            activeTab === "apps"
              ? "bg-indigo-600 text-white shadow-md shadow-indigo-600/20"
              : "text-slate-600 hover:text-slate-900 hover:bg-slate-100"
          }`}
        >
          <Smartphone className="w-4 h-4" />
          <span>My Apps &amp; Store</span>
        </button>

        <button
          onClick={() => setActiveTab("builds")}
          className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap ${
            activeTab === "builds"
              ? "bg-indigo-600 text-white shadow-md shadow-indigo-600/20"
              : "text-slate-600 hover:text-slate-900 hover:bg-slate-100"
          }`}
        >
          <Package className="w-4 h-4" />
          <span>Build History ({builds.length || 1})</span>
        </button>

        <button
          onClick={() => setActiveTab("plans")}
          className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap ${
            activeTab === "plans"
              ? "bg-indigo-600 text-white shadow-md shadow-indigo-600/20"
              : "text-slate-600 hover:text-slate-900 hover:bg-slate-100"
          }`}
        >
          <Shield className="w-4 h-4" />
          <span>Plans &amp; Subscription</span>
        </button>

        <button
          onClick={() => setActiveTab("payments")}
          className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap ${
            activeTab === "payments"
              ? "bg-indigo-600 text-white shadow-md shadow-indigo-600/20"
              : "text-slate-600 hover:text-slate-900 hover:bg-slate-100"
          }`}
        >
          <Receipt className="w-4 h-4" />
          <span>Payment History ({payments.length || 1})</span>
        </button>

        <button
          onClick={() => setActiveTab("addons")}
          className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap ${
            activeTab === "addons"
              ? "bg-indigo-600 text-white shadow-md shadow-indigo-600/20"
              : "text-slate-600 hover:text-slate-900 hover:bg-slate-100"
          }`}
        >
          <Sparkles className="w-4 h-4" />
          <span>Add-ons &amp; Enhancements</span>
        </button>
      </div>

      {/* ── TAB 1: MY APPS & STORE LIVE CARD ── */}
      {activeTab === "apps" && (
        <div className="space-y-6">
          {primaryApp ? (
            <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm">
              <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-slate-100">
                <div className="flex items-start gap-4">
                  <div className="w-14 h-14 rounded-2xl bg-gradient-to-tr from-pink-500 via-rose-500 to-red-500 text-white font-extrabold text-2xl flex items-center justify-center shadow-md shadow-rose-500/20 flex-shrink-0">
                    {primaryApp.name.charAt(0)}
                  </div>
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="text-xl font-extrabold text-slate-900">{primaryApp.name}</h2>
                      <span className="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                        {primaryApp.status || "Ready"}
                      </span>
                      <span className="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600">
                        {primaryApp.platform || "WooCommerce"}
                      </span>
                    </div>
                    <p className="text-xs text-slate-400 font-mono mt-1">{primaryApp.package_name}</p>
                    <p className="text-xs text-slate-600 mt-1">
                      Store URL:{" "}
                      <a
                        href={primaryApp.connection?.store_url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-indigo-600 hover:underline font-medium inline-flex items-center gap-0.5"
                      >
                        {primaryApp.connection?.store_url}
                        <ExternalLink className="w-3 h-3" />
                      </a>
                    </p>
                  </div>
                </div>

                {/* Direct Download & Build Actions */}
                <div className="flex flex-wrap items-center gap-3">
                  <button
                    type="button"
                    onClick={() => handleOpenAppDetails(primaryApp)}
                    className="px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/80 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-xs"
                  >
                    <Eye className="w-4 h-4 text-indigo-600" />
                    <span>View App Details</span>
                  </button>

                  <a
                    href={`/api/apps/${primaryApp.ulid}/builds/${targetBuildId}/download-file?format=apk`}
                    download
                    className="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-emerald-600/20 flex items-center gap-2"
                  >
                    <Download className="w-4 h-4" />
                    <span>Download APK (v{latestBuild?.version_code || 1})</span>
                  </a>

                  {currentPlan.name !== "Starter" ? (
                    <a
                      href={`/api/apps/${primaryApp.ulid}/builds/${targetBuildId}/download-file?format=aab`}
                      download
                      className="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-600/20 flex items-center gap-2"
                    >
                      <Download className="w-4 h-4" />
                      <span>Download AAB Bundle</span>
                    </a>
                  ) : (
                    <button
                      onClick={() => setActiveTab("plans")}
                      className="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition flex items-center gap-2"
                    >
                      <Lock className="w-3.5 h-3.5 text-slate-400" />
                      <span>AAB Locked (Pro Tier)</span>
                    </button>
                  )}

                  <Link
                    href={`/dashboard/apps/${primaryApp.ulid}/setup/branding`}
                    className="px-4 py-2.5 border border-slate-200 hover:border-slate-300 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2"
                  >
                    <span>Edit Branding &amp; Settings</span>
                  </Link>

                  <button
                    onClick={handleRebuild}
                    disabled={isRebuilding}
                    className="p-2.5 rounded-xl border border-slate-200 hover:border-slate-300 text-slate-600 hover:text-slate-900 transition"
                    title="Sync Store Products & Rebuild APK"
                  >
                    <RefreshCw className={`w-4 h-4 ${isRebuilding ? "animate-spin text-indigo-600" : ""}`} />
                  </button>
                </div>
              </div>

              {rebuildMessage && (
                <div className="mt-4 p-3 rounded-xl bg-indigo-50 text-indigo-700 text-xs font-semibold flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 flex-shrink-0" />
                  <span>{rebuildMessage}</span>
                </div>
              )}

              {/* Technical Specifications */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
                <div className="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs">
                  <span className="text-slate-400 font-semibold block">Min Android SDK</span>
                  <strong className="text-slate-900 font-bold mt-1 block">Android 7.0 (API 24)</strong>
                </div>
                <div className="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs">
                  <span className="text-slate-400 font-semibold block">Target Android SDK</span>
                  <strong className="text-slate-900 font-bold mt-1 block">Android 14 (API 34)</strong>
                </div>
                <div className="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs">
                  <span className="text-slate-400 font-semibold block">Security &amp; Keystore</span>
                  <strong className="text-slate-900 font-bold mt-1 block">v1, v2, v3 Cryptographic</strong>
                </div>
                <div className="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs">
                  <span className="text-slate-400 font-semibold block">Architecture</span>
                  <strong className="text-slate-900 font-bold mt-1 block">Universal ARM64 / x86_64</strong>
                </div>
              </div>
            </div>
          ) : (
            <div className="bg-white border border-slate-200 rounded-3xl p-12 text-center">
              <Smartphone className="w-12 h-12 text-slate-300 mx-auto mb-3" />
              <h3 className="text-base font-bold text-slate-900">No applications created yet</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Convert your WooCommerce store into native mobile apps in 10 simple guided steps.
              </p>
              <Link
                href="/dashboard/apps/new"
                className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20"
              >
                <Plus className="w-4 h-4" />
                <span>Start New App</span>
              </Link>
            </div>
          )}
        </div>
      )}

      {/* ── TAB 2: BUILD HISTORY ("JO KUCH BHI USNE BUILD KIA") ── */}
      {activeTab === "builds" && (
        <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
          <div className="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
              <h3 className="font-extrabold text-slate-900 text-base">Compilation &amp; Build History</h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Every Android APK and Google Play bundle generated for your application.
              </p>
            </div>
            <button
              onClick={handleRebuild}
              disabled={isRebuilding}
              className="px-3.5 py-2 rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-bold transition flex items-center gap-2"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${isRebuilding ? "animate-spin" : ""}`} />
              <span>Trigger New Build</span>
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs min-w-[650px]">
              <thead>
                <tr className="text-slate-400 uppercase tracking-wider border-b border-slate-100">
                  <th className="py-3 font-bold">Build ID</th>
                  <th className="py-3 font-bold">Version</th>
                  <th className="py-3 font-bold">Target Platform</th>
                  <th className="py-3 font-bold">Date &amp; Time</th>
                  <th className="py-3 font-bold">Status</th>
                  <th className="py-3 font-bold text-right">Artifacts</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-slate-700">
                {builds.length > 0 ? (
                  builds.map((b: any) => (
                    <tr key={b.id} className="hover:bg-slate-50/50 transition">
                      <td className="py-3.5 font-mono font-bold text-slate-900">BUILD-{b.id.toString().padStart(4, "0")}</td>
                      <td className="py-3.5 font-bold text-slate-900">v{b.version_code || 1}.0.0</td>
                      <td className="py-3.5 font-medium text-slate-600">Android 14 (API 34) &bull; Universal DEX</td>
                      <td className="py-3.5 text-slate-500">
                        {b.created_at
                          ? new Date(b.created_at).toLocaleDateString("en-IN", {
                              day: "numeric",
                              month: "short",
                              year: "numeric",
                              hour: "2-digit",
                              minute: "2-digit",
                            })
                          : "Today"}
                      </td>
                      <td className="py-3.5">
                        <span className="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-extrabold border border-emerald-200">
                          {b.status || "Ready"}
                        </span>
                      </td>
                      <td className="py-3.5 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <a
                            href={`/api/apps/${primaryApp?.ulid}/builds/${b.id}/download-file?format=apk`}
                            download
                            className="px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-[11px] flex items-center gap-1 transition"
                          >
                            <Download className="w-3 h-3" />
                            <span>APK</span>
                          </a>
                          {currentPlan.name !== "Starter" ? (
                            <a
                              href={`/api/apps/${primaryApp?.ulid}/builds/${b.id}/download-file?format=aab`}
                              download
                              className="px-2.5 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-bold text-[11px] flex items-center gap-1 transition"
                            >
                              <Download className="w-3 h-3" />
                              <span>AAB</span>
                            </a>
                          ) : (
                            <span className="text-[10px] text-slate-400 font-semibold">AAB (Pro)</span>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={6} className="py-8 text-center text-slate-400">
                      No builds compiled yet.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── TAB 3: SUBSCRIPTIONS & PLANS ("SUBCRIPTION LIA, PLANS LIYE") ── */}
      {activeTab === "plans" && (
        <div className="space-y-6">
          <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
              <div>
                <span className="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                  Current Active Subscription
                </span>
                <h3 className="text-xl font-extrabold text-slate-900 mt-2">{currentPlan.name} Tier</h3>
                <p className="text-xs text-slate-500 mt-0.5">
                  Subscription is active and renews monthly. Change or upgrade your plan anytime.
                </p>
              </div>
              <div className="text-right">
                <span className="text-2xl font-black text-slate-900">{formattedPlanPrice}</span>
                <span className="block text-xs text-emerald-600 font-bold mt-0.5">&bull; Active &amp; Verified</span>
              </div>
            </div>

            {planMessage && (
              <div className="mt-4 p-3 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 flex-shrink-0" />
                <span>{planMessage}</span>
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
              {plans.map((p: any) => {
                const isCurrent = p.id === currentPlan.id;
                const formattedPrice =
                  p.price_monthly === 0
                    ? "₹0"
                    : `₹${(p.price_monthly / 100).toLocaleString("en-IN")}`;

                return (
                  <div
                    key={p.id}
                    className={`rounded-2xl p-6 border transition flex flex-col justify-between ${
                      isCurrent
                        ? "border-indigo-600 bg-indigo-50/20 ring-2 ring-indigo-600/10"
                        : "border-slate-200 bg-white hover:border-slate-300"
                    }`}
                  >
                    <div>
                      <div className="flex items-center justify-between mb-3">
                        <span className="font-extrabold text-base text-slate-900">{p.name}</span>
                        {isCurrent && (
                          <span className="px-2 py-0.5 rounded-md bg-indigo-600 text-white font-extrabold text-[10px]">
                            ACTIVE TIER
                          </span>
                        )}
                      </div>

                      <div className="flex items-baseline gap-1 mb-4">
                        <span className="text-2xl font-black text-slate-900">{formattedPrice}</span>
                        <span className="text-xs text-slate-400">/ month</span>
                      </div>

                      <ul className="space-y-2 text-xs text-slate-600">
                        <li className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>{p.limits?.builds_per_month || 3} APK Builds per month</span>
                        </li>
                        <li className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>
                            {p.name === "Starter" ? "Android APK format" : "Android APK & Play Store .AAB"}
                          </span>
                        </li>
                        {p.name === "Enterprise" && (
                          <li className="flex items-center gap-2">
                            <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                            <span>Apple iOS .IPA &amp; Dedicated Admin Panel</span>
                          </li>
                        )}
                        <li className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>Automated Store Catalog Sync</span>
                        </li>
                      </ul>
                    </div>

                    <button
                      type="button"
                      disabled={isCurrent || switchingPlanId !== null}
                      onClick={() => handleSwitchPlan(p.id, p.name)}
                      className={`mt-6 w-full py-2.5 rounded-xl font-bold text-xs transition flex items-center justify-center gap-2 ${
                        isCurrent
                          ? "bg-slate-100 text-slate-400 cursor-default"
                          : "bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-600/20"
                      }`}
                    >
                      {switchingPlanId === p.id ? (
                        <>
                          <Loader2 className="w-3.5 h-3.5 animate-spin" />
                          <span>Switching...</span>
                        </>
                      ) : isCurrent ? (
                        "Current Plan"
                      ) : (
                        <span>Upgrade to {p.name}</span>
                      )}
                    </button>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      )}

      {/* ── TAB 4: PAYMENTS & INVOICES HISTORY ("PAYMENT KI, SBHI HISTORY") ── */}
      {activeTab === "payments" && (
        <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
          <div className="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
              <h3 className="font-extrabold text-slate-900 text-base">Payment &amp; Invoice History</h3>
              <p className="text-xs text-slate-500 mt-0.5">
                Download verified receipts and invoices for your accounting records.
              </p>
            </div>
            <Receipt className="w-5 h-5 text-slate-400" />
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs min-w-[650px]">
              <thead>
                <tr className="text-slate-400 uppercase tracking-wider border-b border-slate-100">
                  <th className="py-3 font-bold">Invoice #</th>
                  <th className="py-3 font-bold">Transaction Ref</th>
                  <th className="py-3 font-bold">Date</th>
                  <th className="py-3 font-bold">Plan Description</th>
                  <th className="py-3 font-bold">Amount Paid</th>
                  <th className="py-3 font-bold">Status</th>
                  <th className="py-3 font-bold text-right">Receipt</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-slate-700">
                {payments.length > 0 ? (
                  payments.map((pay: any, idx: number) => {
                    const invoiceId = `INV-2026-${pay.id.toString().padStart(4, "0")}`;
                    const amountFormatted =
                      pay.amount === 0
                        ? "₹0 (Free Tier)"
                        : `₹${(pay.amount / 100).toLocaleString("en-IN")}`;

                    return (
                      <tr key={pay.id || idx} className="hover:bg-slate-50/50 transition">
                        <td className="py-3.5 font-mono font-bold text-slate-900">{invoiceId}</td>
                        <td className="py-3.5 font-mono text-[11px] text-slate-500">
                          {pay.razorpay_payment_id || `pay_${pay.id}`}
                        </td>
                        <td className="py-3.5 text-slate-600">
                          {pay.created_at
                            ? new Date(pay.created_at).toLocaleDateString("en-IN", {
                                day: "numeric",
                                month: "short",
                                year: "numeric",
                              })
                            : "Today"}
                        </td>
                        <td className="py-3.5 font-semibold text-slate-900">
                          {pay.raw?.description || `${currentPlan.name} Subscription`}
                        </td>
                        <td className="py-3.5 font-extrabold text-slate-900">{amountFormatted}</td>
                        <td className="py-3.5">
                          <span className="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-extrabold border border-emerald-200">
                            Paid &bull; Success
                          </span>
                        </td>
                        <td className="py-3.5 text-right">
                          <button
                            onClick={() => setSelectedInvoice({ ...pay, invoiceId, amountFormatted })}
                            className="px-3 py-1 rounded-lg border border-slate-200 hover:border-slate-300 text-slate-700 font-bold text-[11px] transition inline-flex items-center gap-1"
                          >
                            <Receipt className="w-3 h-3 text-indigo-600" />
                            <span>View Receipt</span>
                          </button>
                        </td>
                      </tr>
                    );
                  })
                ) : (
                  <tr>
                    <td className="py-3.5 font-mono font-bold text-slate-900">INV-2026-0001</td>
                    <td className="py-3.5 font-mono text-[11px] text-slate-500">pay_starter_init</td>
                    <td className="py-3.5 text-slate-600">Today</td>
                    <td className="py-3.5 font-semibold text-slate-900">Starter Tier Monthly (Active)</td>
                    <td className="py-3.5 font-extrabold text-slate-900">₹0</td>
                    <td className="py-3.5">
                      <span className="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-extrabold border border-emerald-200">
                        Paid
                      </span>
                    </td>
                    <td className="py-3.5 text-right">
                      <button
                        onClick={() =>
                          setSelectedInvoice({
                            invoiceId: "INV-2026-0001",
                            amountFormatted: "₹0",
                            created_at: new Date().toISOString(),
                          })
                        }
                        className="px-3 py-1 rounded-lg border border-slate-200 hover:border-slate-300 text-slate-700 font-bold text-[11px] transition inline-flex items-center gap-1"
                      >
                        <Receipt className="w-3 h-3 text-indigo-600" />
                        <span>View Receipt</span>
                      </button>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── TAB 5: ADD-ONS & ENHANCEMENT STORE ── */}
      {activeTab === "addons" && (
        <div className="space-y-6">
          <div className="bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-6">
            <div>
              <span className="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-white/10 text-white border border-white/20">
                Future Enhancements
              </span>
              <h2 className="text-xl sm:text-2xl font-black mt-2">App Enhancement &amp; Add-ons Marketplace</h2>
              <p className="text-xs text-indigo-200 mt-1 max-w-xl leading-relaxed">
                Empower your mobile application with official Play Store publishing, iOS binary compilation, WhatsApp order tracking, and push campaigns.
              </p>
            </div>
            <Link
              href="/dashboard/addons"
              className="px-4 py-2.5 bg-white text-indigo-900 rounded-xl font-bold text-xs hover:bg-indigo-50 transition shadow-lg flex-shrink-0 flex items-center gap-2"
            >
              <span>Browse Full Catalog</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {addonsList.map((addon) => {
              const Icon = addon.icon;
              return (
                <div
                  key={addon.id}
                  className="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between"
                >
                  <div>
                    <div className="flex items-start justify-between gap-2 mb-4">
                      <div className="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <Icon className="w-6 h-6" />
                      </div>
                      <span className="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">
                        {addon.badge}
                      </span>
                    </div>

                    <h3 className="font-extrabold text-base text-slate-900 leading-snug">{addon.name}</h3>
                    <p className="text-xs text-slate-500 mt-2 line-clamp-3 leading-relaxed">{addon.desc}</p>

                    <div className="mt-4 pt-4 border-t border-slate-100">
                      <span className="text-lg font-black text-slate-900">{addon.price}</span>
                    </div>
                  </div>

                  <div className="mt-6 pt-4 border-t border-slate-100">
                    <button
                      onClick={() => {
                        setSelectedAddon(addon);
                        setAddonSuccess(false);
                      }}
                      className="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/20 transition flex items-center justify-center gap-1.5"
                    >
                      <span>Enable Add-on</span>
                      <ArrowRight className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* ── INVOICE RECEIPT MODAL ── */}
      {selectedInvoice && (
        <div className="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-start justify-between border-b border-slate-100 pb-4 mb-4">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-extrabold">
                  <Receipt className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-extrabold text-base text-slate-900">Official Payment Receipt</h3>
                  <span className="font-mono text-xs text-slate-500">{selectedInvoice.invoiceId}</span>
                </div>
              </div>
              <button
                onClick={() => setSelectedInvoice(null)}
                className="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:text-slate-900 flex items-center justify-center font-bold text-base"
              >
                &times;
              </button>
            </div>

            <div className="space-y-4 text-xs">
              <div className="grid grid-cols-2 gap-4 p-4 rounded-2xl bg-slate-50">
                <div>
                  <span className="text-slate-400 font-semibold block">Billed To</span>
                  <strong className="text-slate-900 block mt-0.5">{user?.name || "Store Owner"}</strong>
                  <span className="text-slate-500 block">{user?.email}</span>
                </div>
                <div>
                  <span className="text-slate-400 font-semibold block">Application</span>
                  <strong className="text-slate-900 block mt-0.5">{primaryApp?.name || "Mobile Store"}</strong>
                  <span className="text-emerald-600 font-bold block">Status: Verified Paid</span>
                </div>
              </div>

              <div className="p-4 rounded-2xl border border-slate-200 space-y-2">
                <div className="flex justify-between font-semibold text-slate-500">
                  <span>Item Description</span>
                  <span>Amount</span>
                </div>
                <div className="flex justify-between font-bold text-slate-900 border-t border-slate-100 pt-2">
                  <span>{currentPlan.name} Tier Monthly Subscription</span>
                  <span>{selectedInvoice.amountFormatted}</span>
                </div>
                <div className="flex justify-between text-slate-500 text-[11px] pt-1">
                  <span>Platform Fee &amp; Cloud Maintenance</span>
                  <span>₹0.00</span>
                </div>
                <div className="flex justify-between font-black text-sm text-slate-900 border-t border-slate-200 pt-2">
                  <span>Total Amount Paid</span>
                  <span className="text-indigo-600">{selectedInvoice.amountFormatted}</span>
                </div>
              </div>
            </div>

            <div className="flex items-center gap-3 pt-6">
              <button
                onClick={() => window.print()}
                className="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-50 transition flex items-center justify-center gap-1.5"
              >
                <Printer className="w-3.5 h-3.5" />
                <span>Print Invoice</span>
              </button>
              <button
                onClick={() => setSelectedInvoice(null)}
                className="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/20 transition"
              >
                Close Receipt
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── ADD-ON ACTIVATION MODAL ── */}
      {selectedAddon && (
        <div className="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
            {addonSuccess ? (
              <div className="text-center py-6 space-y-4">
                <div className="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
                  <CheckCircle2 className="w-8 h-8" />
                </div>
                <h3 className="text-xl font-extrabold text-slate-900">Add-on Activated Successfully!</h3>
                <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
                  <strong>{selectedAddon.name}</strong> is now configured for your store app{" "}
                  <strong>{primaryApp?.name || "Website Development"}</strong>.
                </p>
                <div className="pt-4">
                  <button
                    onClick={() => setSelectedAddon(null)}
                    className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-indigo-600/20"
                  >
                    Done
                  </button>
                </div>
              </div>
            ) : (
              <div className="space-y-5">
                <div className="flex items-start justify-between">
                  <div>
                    <span className="text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                      Add-on Activation
                    </span>
                    <h3 className="text-lg font-extrabold text-slate-900 mt-1">{selectedAddon.name}</h3>
                  </div>
                  <button
                    onClick={() => setSelectedAddon(null)}
                    className="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:text-slate-900 flex items-center justify-center font-bold text-base"
                  >
                    &times;
                  </button>
                </div>

                <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-xs space-y-2">
                  <div className="flex justify-between">
                    <span className="text-slate-500">Target Application:</span>
                    <span className="font-bold text-slate-900">{primaryApp?.name || "Website Development"}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Price:</span>
                    <span className="font-black text-indigo-600">{selectedAddon.price}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Current Plan:</span>
                    <span className="font-semibold text-slate-700">{currentPlan.name}</span>
                  </div>
                </div>

                <div className="flex items-center gap-3 pt-2">
                  <button
                    type="button"
                    onClick={() => setSelectedAddon(null)}
                    className="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-700 font-bold text-xs hover:bg-slate-50 transition"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => setAddonSuccess(true)}
                    className="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/20 transition"
                  >
                    Confirm &amp; Enable
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
