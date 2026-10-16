"use client";

import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, Check, Loader2, Sparkles, Smartphone, ShieldCheck, LayoutDashboard } from "lucide-react";
import { apiClient } from "@/lib/api-client";

interface StepPaymentProps {
  ulid?: string;
  setupState?: any;
  onNext: () => void;
}

export function StepPayment({ ulid, setupState, onNext }: StepPaymentProps) {
  const initialPlanId = setupState?.subscription?.plan_id || 2;
  const [selectedPlanId, setSelectedPlanId] = useState<number | string>(initialPlanId);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (setupState?.subscription?.plan_id) {
      setSelectedPlanId(setupState.subscription.plan_id);
    }
  }, [setupState?.subscription?.plan_id]);

  const { data: plansData, isLoading } = useQuery({
    queryKey: ["plans"],
    queryFn: async () => {
      try {
        const res = await apiClient("/api/plans");
        return res.plans || [];
      } catch {
        return [
          {
            id: 1,
            key: "starter",
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
            key: "pro",
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
            key: "enterprise",
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
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold text-slate-900 tracking-tight">
          Select Subscription Plan
        </h2>
        <p className="text-sm text-slate-500 mt-1">
          Simple monthly pricing. All plans include automated catalog synchronization.
        </p>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="w-8 h-8 text-indigo-600 animate-spin" />
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {plans.map((plan: any, idx: number) => {
            const isSelected = Number(selectedPlanId) === Number(plan.id);
            const featureList = Array.isArray(plan.features)
              ? plan.features
              : typeof plan.features === "object" && plan.features !== null
              ? Object.entries(plan.features)
                  .filter(([_, enabled]) => Boolean(enabled))
                  .map(([name]) => name.replace(/_/g, " ").toUpperCase())
              : ["White-label Android App", "Unlimited Catalog Sync", "Push Notifications"];

            return (
              <div
                key={plan.id || idx}
                onClick={() => setSelectedPlanId(plan.id)}
                className={`p-6 rounded-2xl border-2 transition cursor-pointer flex flex-col justify-between relative ${
                  isSelected
                    ? "border-indigo-600 bg-white shadow-lg shadow-indigo-600/10 ring-1 ring-indigo-600"
                    : "border-slate-200 bg-white hover:border-slate-300 shadow-sm"
                }`}
              >
                {plan.popular && (
                  <div className="absolute -top-3 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold uppercase tracking-wider px-3 py-0.5 rounded-full shadow-sm">
                    Most Popular
                  </div>
                )}

                <div>
                  <div className="flex items-center justify-between gap-2 mb-1">
                    <h3 className="font-bold text-slate-900 text-lg">{plan.name}</h3>
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
                  <p className="text-xs text-slate-500 mt-1">{plan.desc}</p>

                  <div className="mt-4 mb-6">
                    <span className="text-3xl font-black text-slate-900">{plan.price_formatted || plan.price}</span>
                    <span className="text-xs text-slate-500 font-semibold">{plan.period || "/ month"}</span>
                  </div>

                  <div className="space-y-2.5 border-t border-slate-100 pt-4 text-xs text-slate-600">
                    {featureList.map((feat: string, fidx: number) => {
                      const isFormatHighlight =
                        feat.includes("APK") || feat.includes("AAB") || feat.includes("IPA") || feat.includes("Admin Panel");

                      return (
                        <div key={fidx} className="flex items-start gap-2">
                          <Check
                            className={`w-3.5 h-3.5 flex-shrink-0 mt-0.5 ${
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

                <button
                  type="button"
                  className={`mt-6 w-full py-2.5 rounded-xl font-semibold text-xs transition ${
                    isSelected
                      ? "bg-indigo-600 text-white shadow-md shadow-indigo-600/25"
                      : "bg-slate-100 text-slate-700 hover:bg-slate-200"
                  }`}
                >
                  {isSelected ? "Selected Plan" : "Choose Plan"}
                </button>
              </div>
            );
          })}
        </div>
      )}

      <div className="flex justify-end pt-4">
        <button
          onClick={async () => {
            if (ulid) {
              setIsSubmitting(true);
              try {
                const planToActivate = Number(selectedPlanId) || Number(plans[0]?.id) || 1;
                await apiClient(`/api/apps/${ulid}/subscribe`, {
                  method: "POST",
                  body: JSON.stringify({ plan_id: planToActivate }),
                });
              } catch (e) {
                // proceed if already active
              } finally {
                setIsSubmitting(false);
              }
            }
            onNext();
          }}
          disabled={isSubmitting}
          className="w-full sm:w-auto justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2 shadow-md shadow-indigo-600/20"
        >
          {isSubmitting ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              <span>Activating Subscription...</span>
            </>
          ) : (
            <>
              <span>Continue to Build Engine</span>
              <ArrowRight className="w-4 h-4" />
            </>
          )}
        </button>
      </div>
    </div>
  );
}
