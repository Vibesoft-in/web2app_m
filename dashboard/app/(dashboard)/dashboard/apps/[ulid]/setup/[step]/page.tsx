"use client";

import { useEffect } from "react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import { StepPlatform } from "@/components/wizard/step-platform";
import { StepConnect } from "@/components/wizard/step-connect";
import { StepAnalysis } from "@/components/wizard/step-analysis";
import { StepFeatures } from "@/components/wizard/step-features";
import { StepTheme } from "@/components/wizard/step-theme";
import { StepBranding } from "@/components/wizard/step-branding";
import { StepPreview } from "@/components/wizard/step-preview";
import { StepIntegrations } from "@/components/wizard/step-integrations";
import { StepPayment } from "@/components/wizard/step-payment";
import { StepBuild } from "@/components/wizard/step-build";
import { ArrowLeft, Check, Loader2 } from "lucide-react";

const STEPS = [
  { key: "platform", label: "Platform" },
  { key: "connect", label: "Connect" },
  { key: "analysis", label: "Score" },
  { key: "features", label: "Features" },
  { key: "theme", label: "Theme" },
  { key: "branding", label: "Branding" },
  { key: "preview", label: "Preview" },
  { key: "integrations", label: "Integrations" },
  { key: "payment", label: "Plan" },
  { key: "build", label: "Build" },
];

export default function WizardStepPage() {
  const params = useParams();
  const router = useRouter();
  const ulid = params.ulid as string;
  const currentStepParam = params.step as string;

  const { data: state, isLoading, refetch } = useQuery({
    queryKey: ["setup-state", ulid],
    queryFn: () => apiClient(`/api/apps/${ulid}/setup-state`),
    refetchOnWindowFocus: false,
  });

  // URL STEP GUARD:
  // If the step in the URL is not permitted by allowed_steps, automatically redirect to current_step
  useEffect(() => {
    if (state && state.allowed_steps) {
      const isAllowed = state.allowed_steps.includes(currentStepParam);
      if (!isAllowed && state.current_step) {
        router.replace(`/dashboard/apps/${ulid}/setup/${state.current_step}`);
      }
    }
  }, [state, currentStepParam, ulid, router]);

  if (isLoading) {
    return (
      <div className="min-h-[400px] flex items-center justify-center">
        <div className="flex flex-col items-center gap-3">
          <Loader2 className="w-8 h-8 animate-spin text-indigo-600" />
          <span className="text-xs font-semibold text-slate-500">Loading wizard state...</span>
        </div>
      </div>
    );
  }

  const navigateNext = async () => {
    await refetch();
    const currentIndex = STEPS.findIndex((s) => s.key === currentStepParam);
    if (currentIndex < STEPS.length - 1) {
      const nextStepKey = STEPS[currentIndex + 1].key;
      router.push(`/dashboard/apps/${ulid}/setup/${nextStepKey}`);
    }
  };

  const handleStepClick = (stepKey: string) => {
    if (state?.allowed_steps?.includes(stepKey)) {
      router.push(`/dashboard/apps/${ulid}/setup/${stepKey}`);
    }
  };

  const currentStepIndex = Math.max(0, STEPS.findIndex((s) => s.key === currentStepParam));
  const currentStep = STEPS[currentStepIndex] || STEPS[0];

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {/* ── TOP HEADER WITH BACK TO DASHBOARD BUTTON ── */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <Link
          href="/dashboard"
          className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 hover:text-slate-900 hover:bg-slate-50 font-bold text-xs shadow-xs transition group w-fit"
        >
          <ArrowLeft className="w-4 h-4 text-slate-500 group-hover:-translate-x-1 transition-transform" />
          <span>Back to Dashboard</span>
        </Link>

        <div className="flex items-center gap-2 text-xs text-slate-500">
          <span>Editing Step {currentStepIndex + 1} of 10:</span>
          <span className="font-extrabold text-indigo-600 bg-indigo-50 border border-indigo-200/60 px-2.5 py-0.5 rounded-full">
            {currentStep.label}
          </span>
        </div>
      </div>

      {/* 10-Step Progress Stepper Header - Fully visible without scroll effect */}
      <div className="bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 shadow-sm">
        {/* Desktop & Laptops: Single continuous bar with all 10 steps visible */}
        <div className="hidden md:flex items-center justify-between w-full">
          {STEPS.map((s, index) => {
            const isCompleted = state?.completed_steps?.includes(s.key);
            const isCurrent = currentStepParam === s.key;
            const isAllowed = state?.allowed_steps?.includes(s.key);

            return (
              <div key={s.key} className="flex items-center flex-1 last:flex-none">
                <button
                  type="button"
                  onClick={() => handleStepClick(s.key)}
                  disabled={!isAllowed}
                  className={`flex items-center gap-1.5 select-none transition group text-left ${
                    isAllowed ? "cursor-pointer" : "cursor-not-allowed opacity-40"
                  }`}
                >
                  <div
                    className={`w-6 h-6 lg:w-7 lg:h-7 rounded-full flex items-center justify-center text-[11px] lg:text-xs font-bold flex-shrink-0 transition ${
                      isCurrent
                        ? "bg-indigo-600 text-white ring-4 ring-indigo-100 shadow-sm"
                        : isCompleted
                        ? "bg-emerald-500 text-white"
                        : "bg-slate-100 text-slate-500 group-hover:bg-slate-200"
                    }`}
                  >
                    {isCompleted ? <Check className="w-3.5 h-3.5 stroke-[2.5]" /> : index + 1}
                  </div>
                  <span
                    className={`text-[11px] lg:text-xs font-semibold whitespace-nowrap ${
                      isCurrent
                        ? "text-indigo-600 font-bold"
                        : isCompleted
                        ? "text-slate-800"
                        : "text-slate-400"
                    }`}
                  >
                    {s.label}
                  </span>
                </button>

                {index < STEPS.length - 1 && (
                  <div
                    className={`flex-1 h-0.5 mx-1.5 transition-colors ${
                      isCompleted ? "bg-emerald-300" : "bg-slate-200"
                    }`}
                  />
                )}
              </div>
            );
          })}
        </div>

        {/* Mobile & Tablets: 2 neat rows of 5 steps each - All 10 visible at once without scroll */}
        <div className="md:hidden">
          <div className="grid grid-cols-5 gap-1 sm:gap-1.5">
            {STEPS.map((s, index) => {
              const isCompleted = state?.completed_steps?.includes(s.key);
              const isCurrent = currentStepParam === s.key;
              const isAllowed = state?.allowed_steps?.includes(s.key);

              return (
                <button
                  key={s.key}
                  type="button"
                  onClick={() => handleStepClick(s.key)}
                  disabled={!isAllowed}
                  className={`min-w-0 p-1 sm:p-1.5 rounded-xl flex flex-col items-center justify-center text-center transition ${
                    isCurrent
                      ? "bg-indigo-50 border border-indigo-300 ring-1 ring-indigo-500 shadow-sm"
                      : isCompleted
                      ? "bg-emerald-50/70 border border-emerald-200"
                      : "bg-slate-50 border border-slate-100 opacity-60"
                  } ${!isAllowed ? "cursor-not-allowed" : "cursor-pointer"}`}
                >
                  <div
                    className={`w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold mb-0.5 sm:mb-1 transition flex-shrink-0 ${
                      isCurrent
                        ? "bg-indigo-600 text-white"
                        : isCompleted
                        ? "bg-emerald-500 text-white"
                        : "bg-slate-200 text-slate-600"
                    }`}
                  >
                    {isCompleted ? <Check className="w-3 h-3 stroke-[2.5]" /> : index + 1}
                  </div>
                  <span
                    className={`text-[9px] sm:text-[10px] leading-tight font-semibold truncate w-full block ${
                      isCurrent
                        ? "text-indigo-700 font-bold"
                        : isCompleted
                        ? "text-slate-800"
                        : "text-slate-400"
                    }`}
                  >
                    {s.label}
                  </span>
                </button>
              );
            })}
          </div>

          {/* Active Step Indicator Banner for Mobile */}
          <div className="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-xs px-1">
            <span className="text-slate-500 font-medium">
              Step {currentStepIndex + 1} of {STEPS.length}
            </span>
            <span className="font-bold text-indigo-600">
              {currentStep?.label}
            </span>
          </div>
        </div>
      </div>

      {/* Wizard Step Body */}
      <div className="bg-white border border-slate-200 rounded-2xl sm:rounded-3xl p-3.5 sm:p-10 shadow-sm">
        {currentStepParam === "platform" && (
          <StepPlatform ulid={ulid} app={state?.app} onNext={navigateNext} />
        )}
        {currentStepParam === "connect" && (
          <StepConnect ulid={ulid} onNext={navigateNext} />
        )}
        {currentStepParam === "analysis" && (
          <StepAnalysis ulid={ulid} app={state?.app} onNext={navigateNext} />
        )}
        {currentStepParam === "features" && (
          <StepFeatures ulid={ulid} onNext={navigateNext} />
        )}
        {currentStepParam === "theme" && (
          <StepTheme ulid={ulid} onNext={navigateNext} />
        )}
        {currentStepParam === "branding" && (
          <StepBranding ulid={ulid} onNext={navigateNext} />
        )}
        {currentStepParam === "preview" && (
          <StepPreview ulid={ulid} onNext={navigateNext} />
        )}
        {currentStepParam === "integrations" && (
          <StepIntegrations onNext={navigateNext} />
        )}
        {currentStepParam === "payment" && (
          <StepPayment ulid={ulid} setupState={state} onNext={navigateNext} />
        )}
        {currentStepParam === "build" && (
          <StepBuild ulid={ulid} setupState={state} />
        )}
      </div>
    </div>
  );
}
