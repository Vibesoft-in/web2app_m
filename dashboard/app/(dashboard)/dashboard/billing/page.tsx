"use client";

import { useState } from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  Calendar,
  Check,
  CreditCard,
  FileText,
  HelpCircle,
  Loader2,
  Receipt,
  ShieldCheck,
  Sparkles,
  Zap,
} from "lucide-react";
import { apiClient } from "@/lib/api-client";

export default function BillingPage() {
  const [switchingPlanId, setSwitchingPlanId] = useState<number | null>(null);
  const [switchMessage, setSwitchMessage] = useState<string | null>(null);

  const { data: billingData, isLoading, refetch } = useQuery({
    queryKey: ["billing-data"],
    queryFn: () => apiClient("/api/billing"),
  });

  const activeSub = billingData?.active_sub;
  const currentPlan = activeSub?.plan || {
    id: 1,
    name: "Starter",
    price_monthly: 0,
  };
  const plans = billingData?.plans || [
    {
      id: 1,
      name: "Starter",
      price_monthly: 0,
      features: { wishlist: true, otp_login: true, notifications: true },
      limits: { builds_per_month: 3, max_apps: 1 },
    },
    {
      id: 2,
      name: "Pro Growth",
      price_monthly: 199900,
      features: { wishlist: true, otp_login: true, notifications: true, reviews: true },
      limits: { builds_per_month: 10, max_apps: 3 },
    },
    {
      id: 3,
      name: "Enterprise",
      price_monthly: 499900,
      features: { wishlist: true, otp_login: true, notifications: true, reviews: true, whatsapp: true },
      limits: { builds_per_month: 50, max_apps: 10 },
    },
  ];

  const handleSwitchPlan = async (planId: number, planName: string) => {
    if (activeSub?.app?.ulid) {
      setSwitchingPlanId(planId);
      setSwitchMessage(null);
      try {
        await apiClient(`/api/apps/${activeSub.app.ulid}/subscribe`, {
          method: "POST",
          body: JSON.stringify({ plan_id: planId }),
        });
        setSwitchMessage(`Successfully switched plan to ${planName}!`);
        await refetch();
      } catch (err: any) {
        setSwitchMessage(err.message || "Failed to switch plan.");
      } finally {
        setSwitchingPlanId(null);
      }
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-[450px] flex items-center justify-center">
        <div className="flex flex-col items-center gap-3">
          <Loader2 className="w-8 h-8 animate-spin text-indigo-600" />
          <span className="text-xs font-semibold text-slate-500">Loading billing details...</span>
        </div>
      </div>
    );
  }

  const formattedActivePrice =
    currentPlan.price_monthly === 0
      ? "₹0"
      : `₹${(currentPlan.price_monthly / 100).toLocaleString("en-IN")}`;

  return (
    <div className="space-y-8 max-w-6xl mx-auto pb-12">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Billing & Subscriptions</h1>
        <p className="text-sm text-slate-500 mt-1">
          Manage your subscription tier, billing period, and view receipt history.
        </p>
      </div>

      {switchMessage && (
        <div className="p-4 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm flex items-center gap-2">
          <Sparkles className="w-4 h-4 text-indigo-600 flex-shrink-0" />
          <span>{switchMessage}</span>
        </div>
      )}

      {/* Active Subscription Overview Card */}
      <div className="bg-gradient-to-br from-indigo-900 via-slate-900 to-slate-950 rounded-2xl sm:rounded-3xl p-4 sm:p-8 text-white shadow-xl relative overflow-hidden">
        <div className="absolute right-0 top-0 translate-x-12 -translate-y-12 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none" />

        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-white/10">
          <div>
            <div className="flex items-center gap-2.5">
              <span className="px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-bold uppercase tracking-wider">
                {activeSub?.status || "Active Plan"}
              </span>
              <span className="text-xs text-slate-400">
                Workspace: {billingData?.tenant?.name || "My Store"}
              </span>
            </div>
            <h2 className="text-3xl font-black mt-2 tracking-tight flex items-baseline gap-2">
              <span>{currentPlan.name} Tier</span>
              <span className="text-lg font-normal text-slate-400">
                {formattedActivePrice} / month
              </span>
            </h2>
            <p className="text-xs text-slate-300 mt-1">
              {activeSub?.app?.name
                ? `Assigned to Mobile App: ${activeSub.app.name}`
                : "Active white-label mobile app license"}
            </p>
          </div>

          <div className="text-left md:text-right">
            <div className="text-xs text-slate-400 flex items-center md:justify-end gap-1.5">
              <Calendar className="w-3.5 h-3.5" />
              <span>
                {activeSub?.current_period_end
                  ? `Renews on: ${new Date(activeSub.current_period_end).toLocaleDateString("en-IN", {
                      day: "numeric",
                      month: "short",
                      year: "numeric",
                    })}`
                  : "Continuous monthly access"}
              </span>
            </div>
            <div className="text-[11px] text-slate-400 font-mono mt-1">
              Ref ID: {activeSub?.razorpay_sub_id || "sub_active_store"}
            </div>
          </div>
        </div>

        {/* Quotas & Features */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6">
          <div className="bg-white/5 rounded-2xl p-4 border border-white/5">
            <span className="text-[11px] uppercase tracking-wider text-slate-400 font-semibold block">
              Monthly Builds
            </span>
            <span className="text-xl font-bold mt-1 block">
              {currentPlan.limits?.builds_per_month || 10} / month
            </span>
          </div>
          <div className="bg-white/5 rounded-2xl p-4 border border-white/5">
            <span className="text-[11px] uppercase tracking-wider text-slate-400 font-semibold block">
              Supported Platforms
            </span>
            <span className="text-xl font-bold mt-1 block">Android (AAB+APK)</span>
          </div>
          <div className="bg-white/5 rounded-2xl p-4 border border-white/5">
            <span className="text-[11px] uppercase tracking-wider text-slate-400 font-semibold block">
              Catalog Sync
            </span>
            <span className="text-xl font-bold mt-1 block">Automated REST</span>
          </div>
          <div className="bg-white/5 rounded-2xl p-4 border border-white/5">
            <span className="text-[11px] uppercase tracking-wider text-slate-400 font-semibold block">
              Push Notifications
            </span>
            <span className="text-xl font-bold mt-1 block">Firebase (FCM)</span>
          </div>
        </div>
      </div>

      {/* Available Plans Comparison */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="text-lg font-bold text-slate-900 tracking-tight">Available Subscription Plans</h3>
            <p className="text-xs text-slate-500">Switch tiers at any time with immediate effect.</p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {plans.map((p: any) => {
            const isCurrent = currentPlan.id === p.id;
            const priceFormatted =
              p.price_monthly === 0
                ? "₹0"
                : `₹${(p.price_monthly / 100).toLocaleString("en-IN")}`;

            return (
              <div
                key={p.id}
                className={`rounded-3xl p-6 border-2 transition flex flex-col justify-between relative bg-white ${
                  isCurrent
                    ? "border-indigo-600 shadow-md ring-1 ring-indigo-600"
                    : "border-slate-200 hover:border-slate-300 shadow-sm"
                }`}
              >
                {isCurrent && (
                  <div className="absolute -top-3 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold uppercase tracking-wider px-3 py-0.5 rounded-full shadow-sm">
                    Current Plan
                  </div>
                )}

                <div>
                  <div className="flex items-center justify-between gap-2">
                    <h4 className="font-bold text-slate-900 text-lg">{p.name}</h4>
                    {p.name === "Starter" && (
                      <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                        APK Only
                      </span>
                    )}
                    {p.name === "Pro Growth" && (
                      <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        APK • AAB • IPA
                      </span>
                    )}
                    {p.name === "Enterprise" && (
                      <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                        All + Admin Panel
                      </span>
                    )}
                  </div>

                  <div className="mt-3 mb-4">
                    <span className="text-3xl font-black text-slate-900">{priceFormatted}</span>
                    <span className="text-xs text-slate-500 font-medium"> / month</span>
                  </div>

                  <div className="space-y-2.5 border-t border-slate-100 pt-4 text-xs text-slate-600">
                    {p.name === "Starter" ? (
                      <>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 font-bold flex-shrink-0" />
                          <span className="font-semibold text-slate-900">Android APK Download Only</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>{p.limits?.builds_per_month || 3} Production Builds / mo</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>FCM Push Notifications</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>Community Support</span>
                        </div>
                      </>
                    ) : p.name === "Pro Growth" ? (
                      <>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 font-bold flex-shrink-0" />
                          <span className="font-semibold text-slate-900">Android APK, AAB & iOS IPA Downloads</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>{p.limits?.builds_per_month || 10} Production Builds / mo</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>Priority Build Queue & Cart Recovery</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>Play Store & App Store Ready</span>
                        </div>
                      </>
                    ) : (
                      <>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-purple-600 font-bold flex-shrink-0" />
                          <span className="font-semibold text-slate-900">APK, AAB, IPA + Dedicated Admin Panel</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-purple-600 flex-shrink-0" />
                          <span className="font-semibold text-slate-900">Dedicated Admin Panel Access</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>{p.limits?.builds_per_month || 50} Builds & WhatsApp Cloud API</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Check className="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" />
                          <span>Dedicated Account Manager & 99.9% SLA</span>
                        </div>
                      </>
                    )}
                  </div>
                </div>

                <button
                  type="button"
                  disabled={isCurrent || switchingPlanId !== null}
                  onClick={() => handleSwitchPlan(p.id, p.name)}
                  className={`mt-6 w-full py-2.5 rounded-xl font-semibold text-xs transition flex items-center justify-center gap-2 ${
                    isCurrent
                      ? "bg-slate-100 text-slate-400 cursor-default"
                      : "bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm shadow-indigo-600/20"
                  }`}
                >
                  {switchingPlanId === p.id ? (
                    <>
                      <Loader2 className="w-3.5 h-3.5 animate-spin" />
                      <span>Switching...</span>
                    </>
                  ) : isCurrent ? (
                    "Active Tier"
                  ) : (
                    <span>Switch to {p.name}</span>
                  )}
                </button>
              </div>
            );
          })}
        </div>
      </div>

      {/* Invoice & Receipt History */}
      <div className="bg-white border border-slate-200 rounded-2xl sm:rounded-3xl p-4 sm:p-8 shadow-sm space-y-4">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div>
            <h3 className="font-bold text-slate-900 text-base">Payment & Billing History</h3>
            <p className="text-xs text-slate-500">Download historical invoices and transaction receipts.</p>
          </div>
          <Receipt className="w-5 h-5 text-slate-400" />
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs min-w-[500px]">
            <thead>
              <tr className="text-slate-400 uppercase tracking-wider border-b border-slate-100">
                <th className="py-3 font-semibold">Invoice ID</th>
                <th className="py-3 font-semibold">Date</th>
                <th className="py-3 font-semibold">Plan</th>
                <th className="py-3 font-semibold">Amount</th>
                <th className="py-3 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-slate-700">
              <tr>
                <td className="py-3 font-mono font-medium text-slate-900">
                  INV-2026-{(activeSub?.id || 1).toString().padStart(4, "0")}
                </td>
                <td className="py-3">
                  {activeSub?.created_at
                    ? new Date(activeSub.created_at).toLocaleDateString("en-IN", {
                        day: "numeric",
                        month: "short",
                        year: "numeric",
                      })
                    : new Date().toLocaleDateString("en-IN", {
                        day: "numeric",
                        month: "short",
                        year: "numeric",
                      })}
                </td>
                <td className="py-3 font-semibold text-slate-900">{currentPlan.name} Monthly</td>
                <td className="py-3 font-bold text-slate-900">{formattedActivePrice}</td>
                <td className="py-3">
                  <span className="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-semibold border border-emerald-200">
                    Paid
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      {/* Secure Payment Gateway Notice */}
      <div className="p-4 rounded-2xl bg-slate-100 border border-slate-200 text-xs text-slate-600 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <ShieldCheck className="w-4 h-4 text-emerald-600 flex-shrink-0" />
          <span>Payments are processed with 256-bit bank-grade encryption via Razorpay.</span>
        </div>
        <span className="font-semibold text-slate-500">PCI-DSS Compliant</span>
      </div>
    </div>
  );
}
