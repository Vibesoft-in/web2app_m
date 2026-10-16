"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  AlertCircle,
  CheckCircle2,
  Download,
  ExternalLink,
  Layers,
  LayoutDashboard,
  Loader2,
  Lock,
  Play,
  RotateCw,
  ShieldAlert,
  Smartphone,
  Sparkles,
  Terminal,
} from "lucide-react";

interface StepBuildProps {
  ulid: string;
  setupState: any;
}

export function StepBuild({ ulid, setupState }: StepBuildProps) {
  const [isBuilding, setIsBuilding] = useState(false);
  const [buildStep, setBuildStep] = useState<number>(0); // 0: idle, 1: queued, 2: compiling, 3: signing, 4: ready
  const [downloadBuildId, setDownloadBuildId] = useState<number | null>(null);
  const [buildError, setBuildError] = useState<string | null>(null);
  const [latestBuildInfo, setLatestBuildInfo] = useState<{
    id: number;
    version_code: number;
    version_name: string;
    finished_at?: string;
  } | null>(null);

  const buildAllowed = setupState?.build_allowed ?? false;
  const blockers: string[] = setupState?.build_blockers || [];

  // Subscription plan capabilities
  const subInfo = setupState?.subscription;
  const planName = subInfo?.plan_name || "Starter";
  const canDownloadApk = true;
  const canDownloadAab = subInfo?.can_download_aab ?? (planName === "Pro Growth" || planName === "Enterprise");
  const canDownloadIpa = subInfo?.can_download_ipa ?? (planName === "Pro Growth" || planName === "Enterprise");
  const hasAdminPanel = subInfo?.admin_panel_access ?? (planName === "Enterprise");

  // Check for any existing ready builds on mount
  useEffect(() => {
    let isMounted = true;
    async function fetchExistingBuilds() {
      try {
        const res = await fetch(`/api/apps/${ulid}/builds`);
        if (res.ok) {
          const json = await res.json();
          const builds = json.data || [];
          const readyBuild = builds.find((b: any) => b.is_ready || b.status === "ready");
          if (readyBuild && isMounted) {
            setBuildStep(4);
            setDownloadBuildId(readyBuild.id);
            setLatestBuildInfo({
              id: readyBuild.id,
              version_code: readyBuild.version_code,
              version_name: readyBuild.version_name,
              finished_at: readyBuild.finished_at,
            });
          }
        }
      } catch (e) {
        // Silent fail on check
      }
    }
    fetchExistingBuilds();
    return () => {
      isMounted = false;
    };
  }, [ulid]);

  const handleStartBuild = async () => {
    setBuildError(null);
    setIsBuilding(true);
    setBuildStep(1);

    try {
      // Step 1: Queued
      const res = await fetch(`/api/apps/${ulid}/builds`, {
        method: "POST",
      });

      // Visual progress transitions
      setTimeout(() => setBuildStep(2), 800);
      setTimeout(() => setBuildStep(3), 1800);

      const json = await res.json();

      if (!res.ok) {
        throw new Error(
          json.error?.message || json.message || "Failed to trigger build pipeline."
        );
      }

      const buildData = json.build || json;
      const buildId = buildData.id;

      // Finish build step
      setTimeout(() => {
        setBuildStep(4);
        setIsBuilding(false);
        setDownloadBuildId(buildId);
        setLatestBuildInfo({
          id: buildId,
          version_code: buildData.version_code || 1,
          version_name: buildData.version_name || "1.0.0",
          finished_at: new Date().toISOString(),
        });
      }, 2600);
    } catch (err: any) {
      setIsBuilding(false);
      setBuildStep(0);
      setBuildError(err.message || "Build generation failed.");
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
          <h2 className="text-xl font-bold text-slate-900 tracking-tight">
            Application Build Pipeline
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            Compile your white-label Flutter application package signed for deployment.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <span className="text-xs text-slate-500">Active Tier:</span>
          <span
            className={`px-2.5 py-1 rounded-full text-xs font-bold border ${
              planName === "Enterprise"
                ? "bg-purple-50 text-purple-700 border-purple-200"
                : planName === "Pro Growth"
                ? "bg-indigo-50 text-indigo-700 border-indigo-200"
                : "bg-slate-100 text-slate-700 border-slate-200"
            }`}
          >
            {planName}
          </span>
        </div>
      </div>

      {buildError && (
        <div className="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 flex items-start gap-3">
          <AlertCircle className="w-5 h-5 text-rose-600 flex-shrink-0 mt-0.5" />
          <div>
            <p className="font-bold text-sm">Build Initiation Failed</p>
            <p className="text-xs text-rose-700 mt-0.5">{buildError}</p>
          </div>
        </div>
      )}

      {!buildAllowed && (
        <div className="p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 space-y-2">
          <div className="flex items-center gap-2 font-bold text-amber-950">
            <ShieldAlert className="w-5 h-5 text-amber-600 flex-shrink-0" />
            <span>Build Prerequisites Incomplete</span>
          </div>
          <p className="text-xs text-amber-800 leading-relaxed">
            The build pipeline cannot be initiated until all requirements are met:
          </p>
          <ul className="text-xs space-y-1 list-disc pl-5 font-semibold text-amber-900">
            {blockers.map((b) => (
              <li key={b}>
                {b === "CONNECTION_NOT_VERIFIED" && "Store connection must be verified via REST keys"}
                {b === "CONFIG_NOT_PUBLISHED" && "Configuration must be published"}
                {b === "SUBSCRIPTION_NOT_ACTIVE" && "Subscription must be active"}
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 shadow-sm">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
          <div>
            <h3 className="font-bold text-slate-900 text-base">Release Artifact (.AAB / APK / IPA)</h3>
            <p className="text-xs text-slate-500 mt-0.5">
              Target SDK 34 • 64-bit Architecture • ProGuard Enabled
              {latestBuildInfo && ` • Version ${latestBuildInfo.version_name} (Code ${latestBuildInfo.version_code})`}
            </p>
          </div>

          <div className="flex items-center gap-2">
            {buildStep === 4 && (
              <button
                onClick={handleStartBuild}
                disabled={!buildAllowed || isBuilding}
                title="Trigger a new build rebuild"
                className="w-full sm:w-auto px-3 py-2.5 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 border border-slate-200 hover:bg-slate-50 text-slate-700 transition"
              >
                <RotateCw className={`w-3.5 h-3.5 ${isBuilding ? "animate-spin" : ""}`} />
                <span>Rebuild</span>
              </button>
            )}

            <button
              onClick={handleStartBuild}
              disabled={!buildAllowed || isBuilding || buildStep === 4}
              className={`w-full sm:w-auto justify-center px-5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2 transition ${
                buildAllowed && !isBuilding && buildStep !== 4
                  ? "bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-600/20"
                  : buildStep === 4
                  ? "bg-emerald-50 text-emerald-700 border border-emerald-200 cursor-default"
                  : "bg-slate-100 text-slate-400 cursor-not-allowed border border-slate-200"
              }`}
            >
              {isBuilding ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin text-indigo-600" />
                  <span className="text-slate-700">Building Package...</span>
                </>
              ) : buildStep === 4 ? (
                <>
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                  <span>Build Ready</span>
                </>
              ) : (
                <>
                  <Play className="w-4 h-4" />
                  <span>Generate Production App</span>
                </>
              )}
            </button>
          </div>
        </div>

        {/* Live Build Progression Timeline */}
        <div className="pt-6 space-y-4">
          <span className="text-xs font-bold text-slate-700 uppercase tracking-wider block">
            Build Progress Log
          </span>

          <div className="space-y-3 text-xs">
            {[
              { step: 1, label: "Job Queued & Snapshot Version Ingested" },
              { step: 2, label: "Injecting Package Name, Brand Tokens, and Public APP_KEY" },
              { step: 3, label: "Signing with Tenant Dedicated Keystore (SHA-256)" },
              { step: 4, label: "Packages (APK / AAB / IPA) Generated & Encrypted" },
            ].map((s) => {
              const isDone = buildStep > s.step || buildStep === 4;
              const isCurrent = buildStep === s.step && isBuilding;

              return (
                <div
                  key={s.step}
                  className={`p-3 rounded-xl border flex items-center justify-between transition ${
                    isDone
                      ? "bg-emerald-50/50 border-emerald-200 text-emerald-900"
                      : isCurrent
                      ? "bg-indigo-50/50 border-indigo-200 text-indigo-900 animate-pulse"
                      : "bg-slate-50 border-slate-200 text-slate-400"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <span className="font-mono font-bold text-[11px] w-5">0{s.step}</span>
                    <span className="font-medium">{s.label}</span>
                  </div>

                  {isDone ? (
                    <CheckCircle2 className="w-4 h-4 text-emerald-600 flex-shrink-0" />
                  ) : isCurrent ? (
                    <Loader2 className="w-4 h-4 text-indigo-600 animate-spin flex-shrink-0" />
                  ) : (
                    <div className="w-2 h-2 rounded-full bg-slate-300" />
                  )}
                </div>
              );
            })}
          </div>
        </div>

        {/* Tier-Specific Download & Access Grid */}
        {downloadBuildId && (
          <div className="mt-8 pt-6 border-t border-slate-100 space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <h4 className="font-bold text-slate-900 text-base">Download Artifacts & Plan Deliverables</h4>
                <p className="text-xs text-slate-500">
                  Select your package format based on your active <span className="font-semibold text-slate-800">{planName}</span> subscription.
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* 1. Android APK (Available for all plans) */}
              <div className="p-4 sm:p-5 rounded-2xl bg-white border-2 border-emerald-500/30 shadow-sm flex flex-col justify-between">
                <div>
                  <div className="flex items-center justify-between gap-2 mb-1.5">
                    <div className="flex items-center gap-2">
                      <Smartphone className="w-4 h-4 text-emerald-600" />
                      <span className="font-bold text-sm text-slate-900">Android Package (.APK)</span>
                    </div>
                    <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                      All Plans
                    </span>
                  </div>
                  <p className="text-xs text-slate-500 leading-relaxed mb-4">
                    Direct installation file for Android phones and tablets. Share with testers or clients without Google Play review.
                  </p>
                </div>

                <a
                  href={`/api/apps/${ulid}/builds/${downloadBuildId}/download-file?format=apk`}
                  download
                  className="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-bold text-xs rounded-xl transition flex items-center justify-center gap-2 shadow-md shadow-emerald-600/20"
                >
                  <Download className="w-4 h-4" />
                  <span>Download .APK</span>
                </a>
              </div>

              {/* 2. Google Play Bundle (.AAB) (Pro Growth & Enterprise) */}
              <div
                className={`p-4 sm:p-5 rounded-2xl border-2 flex flex-col justify-between ${
                  canDownloadAab
                    ? "bg-white border-indigo-500/30 shadow-sm"
                    : "bg-slate-50/70 border-dashed border-slate-200"
                }`}
              >
                <div>
                  <div className="flex items-center justify-between gap-2 mb-1.5">
                    <div className="flex items-center gap-2">
                      <Layers className={`w-4 h-4 ${canDownloadAab ? "text-indigo-600" : "text-slate-400"}`} />
                      <span className={`font-bold text-sm ${canDownloadAab ? "text-slate-900" : "text-slate-500"}`}>
                        Google Play Bundle (.AAB)
                      </span>
                    </div>
                    <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                      Pro & Enterprise
                    </span>
                  </div>
                  <p className="text-xs text-slate-500 leading-relaxed mb-4">
                    Signed production bundle optimized for Google Play Console submission with Dynamic Feature Delivery.
                  </p>
                </div>

                {canDownloadAab ? (
                  <a
                    href={`/api/apps/${ulid}/builds/${downloadBuildId}/download-file?format=aab`}
                    download
                    className="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white font-bold text-xs rounded-xl transition flex items-center justify-center gap-2 shadow-md shadow-indigo-600/20"
                  >
                    <Download className="w-4 h-4" />
                    <span>Download .AAB</span>
                  </a>
                ) : (
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2 border-t border-slate-200/60">
                    <div className="flex items-center gap-1.5 text-xs text-slate-500">
                      <Lock className="w-3.5 h-3.5 text-amber-600 flex-shrink-0" />
                      <span>Starter includes APK only</span>
                    </div>
                    <Link
                      href={`/dashboard/apps/${ulid}/setup/payment`}
                      className="text-xs font-bold text-indigo-600 hover:text-indigo-700 hover:underline flex items-center gap-1 self-start sm:self-auto"
                    >
                      <span>Upgrade Plan</span>
                      <ExternalLink className="w-3 h-3" />
                    </Link>
                  </div>
                )}
              </div>

              {/* 3. Apple iOS Package (.IPA) (Pro Growth & Enterprise) */}
              <div
                className={`p-4 sm:p-5 rounded-2xl border-2 flex flex-col justify-between ${
                  canDownloadIpa
                    ? "bg-white border-sky-500/30 shadow-sm"
                    : "bg-slate-50/70 border-dashed border-slate-200"
                }`}
              >
                <div>
                  <div className="flex items-center justify-between gap-2 mb-1.5">
                    <div className="flex items-center gap-2">
                      <Smartphone className={`w-4 h-4 ${canDownloadIpa ? "text-sky-600" : "text-slate-400"}`} />
                      <span className={`font-bold text-sm ${canDownloadIpa ? "text-slate-900" : "text-slate-500"}`}>
                        Apple iOS Package (.IPA)
                      </span>
                    </div>
                    <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-200">
                      Pro & Enterprise
                    </span>
                  </div>
                  <p className="text-xs text-slate-500 leading-relaxed mb-4">
                    Production binary ready for TestFlight distribution and Apple App Store Connect publishing.
                  </p>
                </div>

                {canDownloadIpa ? (
                  <a
                    href={`/api/apps/${ulid}/builds/${downloadBuildId}/download-file?format=ipa`}
                    download
                    className="w-full py-2.5 px-4 bg-sky-600 hover:bg-sky-500 active:scale-95 text-white font-bold text-xs rounded-xl transition flex items-center justify-center gap-2 shadow-md shadow-sky-600/20"
                  >
                    <Download className="w-4 h-4" />
                    <span>Download .IPA</span>
                  </a>
                ) : (
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2 border-t border-slate-200/60">
                    <div className="flex items-center gap-1.5 text-xs text-slate-500">
                      <Lock className="w-3.5 h-3.5 text-amber-600 flex-shrink-0" />
                      <span>Requires Pro or Enterprise</span>
                    </div>
                    <Link
                      href={`/dashboard/apps/${ulid}/setup/payment`}
                      className="text-xs font-bold text-sky-600 hover:text-sky-700 hover:underline flex items-center gap-1 self-start sm:self-auto"
                    >
                      <span>Upgrade Plan</span>
                      <ExternalLink className="w-3 h-3" />
                    </Link>
                  </div>
                )}
              </div>

              {/* 4. Dedicated Admin Panel (Enterprise Exclusive) */}
              <div
                className={`p-4 sm:p-5 rounded-2xl border-2 flex flex-col justify-between ${
                  hasAdminPanel
                    ? "bg-gradient-to-br from-purple-900 to-slate-900 text-white border-purple-500/40 shadow-md"
                    : "bg-slate-50/70 border-dashed border-slate-200"
                }`}
              >
                <div>
                  <div className="flex items-center justify-between gap-2 mb-1.5">
                    <div className="flex items-center gap-2">
                      <LayoutDashboard className={`w-4 h-4 ${hasAdminPanel ? "text-purple-300" : "text-slate-400"}`} />
                      <span className={`font-bold text-sm ${hasAdminPanel ? "text-white" : "text-slate-500"}`}>
                        Dedicated Admin Panel
                      </span>
                    </div>
                    <span
                      className={`px-2 py-0.5 rounded-md text-[10px] font-bold ${
                        hasAdminPanel
                          ? "bg-purple-500/20 text-purple-200 border border-purple-400/30"
                          : "bg-purple-50 text-purple-700 border border-purple-200"
                      }`}
                    >
                      Enterprise Exclusive
                    </span>
                  </div>
                  <p className={`text-xs leading-relaxed mb-4 ${hasAdminPanel ? "text-slate-300" : "text-slate-500"}`}>
                    Direct super-admin console with full access to tenant management, live push campaigns, analytics, and custom database settings.
                  </p>
                </div>

                {hasAdminPanel ? (
                  <div className="flex flex-col sm:flex-row gap-2">
                    <a
                      href="/admin"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex-1 py-2.5 px-4 bg-purple-500 hover:bg-purple-400 active:scale-95 text-white font-bold text-xs rounded-xl transition flex items-center justify-center gap-2 shadow-md shadow-purple-500/25"
                    >
                      <ExternalLink className="w-4 h-4" />
                      <span>Launch Admin Panel</span>
                    </a>
                    <a
                      href={`/api/apps/${ulid}/builds/${downloadBuildId}/download-file?format=admin`}
                      download
                      className="py-2.5 px-3 bg-white/10 hover:bg-white/20 active:scale-95 text-white font-medium text-xs rounded-xl transition flex items-center justify-center gap-1.5 border border-white/15"
                      title="Download Admin Package"
                    >
                      <Download className="w-3.5 h-3.5" />
                      <span>Export Panel</span>
                    </a>
                  </div>
                ) : (
                  <div className="flex items-center justify-between gap-2 pt-2 border-t border-slate-200/60">
                    <div className="flex items-center gap-1.5 text-xs text-slate-500">
                      <Lock className="w-3.5 h-3.5 text-purple-600" />
                      <span>Enterprise Tier Only</span>
                    </div>
                    <Link
                      href={`/dashboard/apps/${ulid}/setup/payment`}
                      className="text-xs font-bold text-purple-600 hover:text-purple-700 hover:underline flex items-center gap-1"
                    >
                      <span>Upgrade to Enterprise</span>
                      <ExternalLink className="w-3 h-3" />
                    </Link>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
