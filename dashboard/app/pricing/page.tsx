"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Check, Loader2, ArrowLeft, Sparkles, Smartphone, ShieldCheck, LayoutDashboard } from "lucide-react";
import { apiClient } from "@/lib/api-client";

export default function PricingPage() {
  const { data: plansData, isLoading } = useQuery({
    queryKey: ["public-plans"],
    queryFn: async () => {
      try {
        const res = await apiClient("/api/plans");
        return res.plans || [];
      } catch {
        return [
          {
            id: 1,
            name: "Starter",
            price_formatted: "₹0",
            period: "/ month",
            desc: "Ideal for emerging brands launching their first native Android app.",
            formats: ["APK"],
            features: [
              "Android APK Download Only",
              "1 White-label Android App",
              "Unlimited Catalog Sync",
              "FCM Push Notifications",
              "Community Support",
            ],
          },
          {
            id: 2,
            name: "Pro Growth",
            price_formatted: "₹1,999",
            period: "/ month",
            desc: "Best for growing stores looking for maximum conversion & speed.",
            formats: ["APK", "AAB", "IPA"],
            features: [
              "Android APK, AAB & iOS IPA Downloads",
              "Priority Build Queue",
              "Abandoned Cart Recovery",
              "OTP Fast Login",
              "White-Label Play Store & App Store Listing",
            ],
            popular: true,
          },
          {
            id: 3,
            name: "Enterprise",
            price_formatted: "₹4,999",
            period: "/ month",
            desc: "High volume stores needing custom integrations & dedicated bandwidth.",
            formats: ["APK", "AAB", "IPA", "Admin Panel"],
            admin_panel: true,
            features: [
              "APK, AAB & iOS IPA + Dedicated Admin Panel",
              "Dedicated Admin Panel Access",
              "WhatsApp Cloud API Integration",
              "Dedicated Account Manager & VIP Support",
              "99.9% Uptime SLA",
            ],
          },
        ];
      }
    },
  });

  const plans = plansData || [];

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 px-4 py-12 md:py-20">
      <div className="max-w-6xl mx-auto">
        <div className="mb-8">
          <Link
            href="/"
            className="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-indigo-600 transition"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Back to Home</span>
          </Link>
        </div>

        <div className="text-center max-w-3xl mx-auto mb-16">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 text-indigo-600 font-semibold text-xs tracking-wider uppercase mb-4">
            <Sparkles className="w-3.5 h-3.5" />
            Transparent Pricing
          </span>
          <h1 className="text-4xl md:text-5xl font-black text-slate-900 tracking-tight">
            Plans built for stores of every size
          </h1>
          <p className="text-base text-slate-600 mt-4 leading-relaxed">
            Choose the right tier for your mobile commerce journey. Download APKs, Play Store App Bundles, iOS IPAs, and dedicated Admin Panels.
          </p>
        </div>

        {isLoading ? (
          <div className="flex justify-center items-center py-20">
            <Loader2 className="w-8 h-8 text-indigo-600 animate-spin" />
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {plans.map((plan: any, idx: number) => {
              const isPopular = plan.popular;
              const featureList = Array.isArray(plan.features)
                ? plan.features
                : typeof plan.features === "object" && plan.features !== null
                ? Object.entries(plan.features)
                    .filter(([_, enabled]) => Boolean(enabled))
                    .map(([name]) => name.replace(/_/g, " ").toUpperCase())
                : ["White-label Android App", "Automated Catalog Sync", "Push Notifications"];

              return (
                <div
                  key={plan.id || idx}
                  className={`p-5 sm:p-8 rounded-2xl sm:rounded-3xl border-2 transition relative flex flex-col justify-between bg-white ${
                    isPopular
                      ? "border-indigo-600 shadow-xl shadow-indigo-600/10 ring-1 ring-indigo-600"
                      : "border-slate-200 shadow-sm hover:shadow-md"
                  }`}
                >
                  {isPopular && (
                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[11px] font-bold uppercase tracking-wider px-4 py-1 rounded-full shadow-md">
                      Most Popular
                    </div>
                  )}

                  <div>
                    <div className="flex items-center justify-between gap-2 mb-2">
                      <h3 className="font-bold text-xl text-slate-900">{plan.name}</h3>
                      {plan.name === "Starter" && (
                        <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                          APK Only
                        </span>
                      )}
                      {plan.name === "Pro Growth" && (
                        <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                          APK • AAB • IPA
                        </span>
                      )}
                      {plan.name === "Enterprise" && (
                        <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                          All + Admin Panel
                        </span>
                      )}
                    </div>
                    <p className="text-xs text-slate-500 mb-6">
                      {plan.desc || "Turn your store into an engaging native app with full sync."}
                    </p>

                    <div className="mb-8">
                      <span className="text-4xl font-extrabold text-slate-900">
                        {plan.price_formatted || (plan.price_monthly === 0 ? "₹0" : `₹${plan.price_monthly / 100}`)}
                      </span>
                      <span className="text-sm text-slate-500 font-medium ml-1.5">
                        {plan.period || "/ month"}
                      </span>
                    </div>

                    <div className="space-y-3 border-t border-slate-100 pt-6 text-sm text-slate-600 mb-8">
                      {featureList.map((feat: string, fidx: number) => {
                        const isFormatHighlight =
                          feat.includes("APK") || feat.includes("AAB") || feat.includes("IPA") || feat.includes("Admin Panel");

                        return (
                          <div key={fidx} className="flex items-start gap-2.5">
                            <Check
                              className={`w-4 h-4 flex-shrink-0 mt-0.5 ${
                                isFormatHighlight ? "text-indigo-600 font-bold" : "text-emerald-600"
                              }`}
                            />
                            <span className={isFormatHighlight ? "font-semibold text-slate-900" : ""}>
                              {feat}
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  <Link
                    href="/signup"
                    className={`w-full py-3 rounded-xl font-bold text-sm text-center transition block ${
                      isPopular
                        ? "bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-600/20"
                        : "bg-slate-100 hover:bg-slate-200 text-slate-900"
                    }`}
                  >
                    Get Started with {plan.name}
                  </Link>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
