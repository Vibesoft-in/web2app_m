"use client";

import { useState } from "react";
import {
  AlertCircle,
  AlertTriangle,
  ArrowRight,
  BarChart3,
  Check,
  CheckCircle2,
  Clock,
  ExternalLink,
  Gauge,
  Globe,
  HelpCircle,
  Info,
  Layers,
  MessageSquare,
  PhoneCall,
  Rocket,
  ShieldAlert,
  ShieldCheck,
  ShoppingBag,
  Sparkles,
  TrendingDown,
  TrendingUp,
  X,
  Zap,
} from "lucide-react";

interface StepAnalysisProps {
  ulid?: string;
  app?: any;
  onNext: () => void;
}

interface Pillar {
  id: string;
  name: string;
  shortLabel: string;
  colorName: string;
  weight: number; // max points
  scoreHigh: number;
  scoreLow: number;
  color: string;
  colorBadgeBg: string;
  colorBadgeText: string;
  colorBadgeBorder: string;
  icon: any;
  description: string;
  checklistHigh: string[];
  checklistLow: string[];
}

const PILLARS: Pillar[] = [
  {
    id: "catalog",
    name: "Catalog & Media Quality",
    shortLabel: "Catalog & Images",
    colorName: "Emerald Green",
    weight: 25,
    scoreHigh: 22,
    scoreLow: 12,
    color: "#10b981", // emerald
    colorBadgeBg: "bg-emerald-50",
    colorBadgeText: "text-emerald-700",
    colorBadgeBorder: "border-emerald-200",
    icon: ShoppingBag,
    description: "Evaluates high-resolution images, variant descriptions, pricing completeness, and inventory status.",
    checklistHigh: [
      "95%+ products have high-resolution images (> 800x800px)",
      "Standard pricing and currency formats detected",
      "Product variant options cleanly configured",
    ],
    checklistLow: [
      "Low image resolution on 40%+ products (may blur on mobile)",
      "Missing product descriptions or short teasers",
      "Inventory tracking disabled on key items",
    ],
  },
  {
    id: "navigation",
    name: "Category & Navigation Structure",
    shortLabel: "Category & Menus",
    colorName: "Indigo Blue",
    weight: 20,
    scoreHigh: 18,
    scoreLow: 8,
    color: "#6366f1", // indigo
    colorBadgeBg: "bg-indigo-50",
    colorBadgeText: "text-indigo-700",
    colorBadgeBorder: "border-indigo-200",
    icon: Layers,
    description: "Assesses multi-tier category hierarchy, catalog depth, and readiness for mobile tab bars.",
    checklistHigh: [
      "Clear top-level and sub-category taxonomies",
      "Zero empty or orphan categories detected",
      "Ready for Flutter bottom-tab and drawer navigation",
    ],
    checklistLow: [
      "Only 1-2 categories found with all products lumped together",
      "Navigation menu lacks structured sub-categories",
      "Search filters incomplete for mobile browsing",
    ],
  },
  {
    id: "traffic",
    name: "Store Traffic & Buyer Activity",
    shortLabel: "Traffic & Buyers",
    colorName: "Purple Violet",
    weight: 25,
    scoreHigh: 18,
    scoreLow: 6,
    color: "#8b5cf6", // violet
    colorBadgeBg: "bg-purple-50",
    colorBadgeText: "text-purple-700",
    colorBadgeBorder: "border-purple-200",
    icon: TrendingUp,
    description: "Analyzes customer visit volume, repeat buyer ratio, and active shopping cart velocity.",
    checklistHigh: [
      "Healthy daily visitor volume and product page views",
      "Demonstrated add-to-cart activity over the last 30 days",
      "Repeat customer base exists to drive app re-engagement",
    ],
    checklistLow: [
      "Daily unique visitors is below sustainable threshold (< 50 visits/day)",
      "Very low cart velocity — limited proof of consistent buyer intent",
      "High risk of low app downloads due to lack of existing web audience",
    ],
  },
  {
    id: "speed",
    name: "Mobile Speed & API Response",
    shortLabel: "Mobile Speed",
    colorName: "Sky Blue",
    weight: 15,
    scoreHigh: 12,
    scoreLow: 5,
    color: "#0284c7", // sky
    colorBadgeBg: "bg-sky-50",
    colorBadgeText: "text-sky-700",
    colorBadgeBorder: "border-sky-200",
    icon: Zap,
    description: "Measures server time-to-first-byte (TTFB), REST API latency, and image delivery performance.",
    checklistHigh: [
      "Server API latency under 450ms for live catalog endpoints",
      "Mobile-friendly caching and gzip/brotli compression active",
      "Smooth data streaming ready for native mobile rails",
    ],
    checklistLow: [
      "High server response latency (> 1800ms) on product list queries",
      "Unoptimized images causing heavy bandwidth drain",
      "Occasional connection timeouts during high-concurrency requests",
    ],
  },
  {
    id: "security",
    name: "Checkout Security & Trust",
    shortLabel: "Security & Trust",
    colorName: "Amber Orange",
    weight: 15,
    scoreHigh: 12,
    scoreLow: 5,
    color: "#f59e0b", // amber
    colorBadgeBg: "bg-amber-50",
    colorBadgeText: "text-amber-700",
    colorBadgeBorder: "border-amber-200",
    icon: ShieldCheck,
    description: "Validates active SSL/TLS transport, trusted payment gateway integrations, and mobile checkout flow.",
    checklistHigh: [
      "Modern SSL/TLS certificate verified by UrlGuard",
      "Standard payment gateways active (Razorpay / UPI / Card)",
      "Secure HTTPS webview checkout compliant with Google Play policy",
    ],
    checklistLow: [
      "Payment gateway in test/sandbox mode or untrusted redirect",
      "Mixed content warnings found on product checkout URLs",
      "Incomplete privacy policy or terms pages required for app stores",
    ],
  },
];

export function StepAnalysis({ ulid, app, onNext }: StepAnalysisProps) {
  // Score mode toggle: Healthy (82%) vs Reality Check (< 50%)
  const [simulationMode, setSimulationMode] = useState<"healthy" | "low">("healthy");
  const [hoveredPillarId, setHoveredPillarId] = useState<string | null>(null);
  const [isConsultModalOpen, setIsConsultModalOpen] = useState(false);
  const [consultSubmitted, setConsultSubmitted] = useState(false);
  const [consultForm, setConsultForm] = useState({
    name: "",
    phone: "",
    websiteUrl: app?.name ? `https://${app.name.toLowerCase().replace(/\s+/g, "")}.com` : "",
    goal: "traffic",
  });

  const isHealthy = simulationMode === "healthy";

  // Calculate scores
  const totalScore = PILLARS.reduce(
    (sum, p) => sum + (isHealthy ? p.scoreHigh : p.scoreLow),
    0
  );

  // SVG Donut calculation with pure pre-calculated offsets
  const radius = 50;
  const circumference = 2 * Math.PI * radius; // ~314.159

  let runningOffset = 0;
  const segments = PILLARS.map((p) => {
    const scoreVal = isHealthy ? p.scoreHigh : p.scoreLow;
    const segmentStroke = (scoreVal / 100) * circumference;
    const offset = runningOffset;
    runningOffset += segmentStroke;

    return {
      ...p,
      scoreVal,
      segmentStroke,
      offset,
    };
  });

  const activePillar = PILLARS.find((p) => p.id === hoveredPillarId);

  const handleConsultSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setConsultSubmitted(true);
  };

  return (
    <div className="space-y-8">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-slate-900 tracking-tight">
            Store Readiness Score & Feasibility Audit
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            Automated multi-pillar evaluation of catalog structure, traffic volume, mobile speed, and checkout security.
          </p>
        </div>

        {/* Audit Mode Switcher */}
        <div className="grid grid-cols-2 gap-1 p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold w-full sm:w-auto sm:flex sm:gap-1.5 self-stretch sm:self-auto">
          <button
            type="button"
            onClick={() => setSimulationMode("healthy")}
            className={`px-2.5 py-1.5 rounded-lg transition text-center truncate ${
              isHealthy
                ? "bg-white text-emerald-700 shadow-sm font-bold"
                : "text-slate-600 hover:text-slate-900"
            }`}
          >
            Healthy (82%)
          </button>
          <button
            type="button"
            onClick={() => setSimulationMode("low")}
            className={`px-2.5 py-1.5 rounded-lg transition text-center truncate ${
              !isHealthy
                ? "bg-white text-rose-700 shadow-sm font-bold"
                : "text-slate-600 hover:text-slate-900"
            }`}
          >
            Low Score (&lt; 50%)
          </button>
        </div>
      </div>

      {/* Main Scorecard Hero Banner with Enhanced Donut Chart & Color Legend */}
      <div
        className={`rounded-2xl sm:rounded-3xl p-4 sm:p-8 border-2 transition relative overflow-hidden ${
          isHealthy
            ? "bg-gradient-to-br from-white via-emerald-50/25 to-indigo-50/20 border-emerald-500/30 shadow-md"
            : "bg-gradient-to-br from-white via-amber-50/30 to-rose-50/30 border-amber-500/40 shadow-md"
        }`}
      >
        <div className="flex flex-col lg:flex-row items-center lg:items-start gap-6 sm:gap-8">
          {/* Donut Chart with Interactive Center & Color Segments */}
          <div className="flex flex-col items-center flex-shrink-0">
            <div className="relative w-40 h-40 sm:w-48 sm:h-48 flex items-center justify-center">
              <svg className="w-full h-full transform -rotate-90" viewBox="0 0 130 130">
                {/* Background Unfilled Circle (Gray: Remaining Room for Optimization) */}
                <circle
                  cx="65"
                  cy="65"
                  r={radius}
                  className="text-slate-100"
                  strokeWidth="12"
                  stroke="currentColor"
                  fill="none"
                />

                {/* Individual Colored Segments */}
                {segments.map((seg) => {
                  const isHovered = hoveredPillarId === seg.id;
                  const strokeWidth = isHovered ? 15 : 12;

                  return (
                    <circle
                      key={seg.id}
                      cx="65"
                      cy="65"
                      r={radius}
                      stroke={isHealthy ? seg.color : "#f43f5e"}
                      strokeWidth={strokeWidth}
                      strokeDasharray={`${seg.segmentStroke} ${circumference - seg.segmentStroke}`}
                      strokeDashoffset={-seg.offset}
                      fill="none"
                      className="transition-all duration-300 cursor-pointer"
                      onMouseEnter={() => setHoveredPillarId(seg.id)}
                      onMouseLeave={() => setHoveredPillarId(null)}
                    />
                  );
                })}
              </svg>

              {/* Inner Circle Dynamic Content */}
              <div className="absolute flex flex-col items-center justify-center text-center p-2 pointer-events-none">
                {activePillar ? (
                  <>
                    <span
                      className="text-xs font-bold uppercase tracking-wider transition-colors"
                      style={{ color: activePillar.color }}
                    >
                      {activePillar.shortLabel}
                    </span>
                    <span className="text-2xl font-black text-slate-900 mt-0.5">
                      +{isHealthy ? activePillar.scoreHigh : activePillar.scoreLow} pts
                    </span>
                    <span className="text-[10px] text-slate-500 font-semibold">
                      {activePillar.colorName}
                    </span>
                  </>
                ) : (
                  <>
                    <span
                      className={`text-4xl font-black tracking-tight ${
                        isHealthy ? "text-slate-900" : "text-rose-600"
                      }`}
                    >
                      {totalScore}%
                    </span>
                    <span className="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">
                      Readiness Score
                    </span>
                    <span className="text-[10px] text-slate-500 font-medium">
                      ({totalScore} / 100)
                    </span>
                  </>
                )}
              </div>
            </div>

            {/* Quick Chart Hint */}
            <span className="text-[11px] text-slate-400 mt-2 font-medium">
              Hover slices to inspect components
            </span>
          </div>

          {/* Right Column: Assessment Details & Complete Color Legend */}
          <div className="flex-1 w-full space-y-4">
            {/* Top Badges */}
            <div className="flex flex-wrap items-center justify-center lg:justify-start gap-2">
              {isHealthy ? (
                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">
                  <Sparkles className="w-3.5 h-3.5" />
                  <span>82% Score • Highly Recommended for Mobile App</span>
                </span>
              ) : (
                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-100 text-rose-800 text-xs font-bold animate-pulse">
                  <AlertTriangle className="w-3.5 h-3.5" />
                  <span>36% Score • Needs Website Traffic & Optimization First</span>
                </span>
              )}
              <span className="text-xs text-slate-500 font-medium">5-Pillar Live Store Audit</span>
            </div>

            {/* Title & Description */}
            <div className="text-center lg:text-left">
              <h3 className="font-extrabold text-slate-900 text-xl sm:text-2xl tracking-tight">
                {isHealthy
                  ? "Your store has strong fundamentals to scale on Mobile!"
                  : "Reality Check: Your store is NOT ready for a mobile app yet."}
              </h3>

              <p className="text-xs sm:text-sm text-slate-600 leading-relaxed mt-1 max-w-2xl">
                {isHealthy
                  ? "Aapka 82% score 5 core pillars se milkar bana hai. Aapke products, clean categories aur active buyer demand mobile app ke liye bilkul primed hain."
                  : "Agar website par regular daily buyers aur traffic 50% se kam hai, to mobile app par spend karne se ROI nahi milega. Pehle website enhance karke traffic badhwayein!"}
              </p>
            </div>

            {/* Visual Color Legend Bar: Showing exact names, colors, and point shares */}
            <div className="bg-white/90 border border-slate-200/80 rounded-2xl p-3.5 sm:p-4 shadow-sm space-y-2.5">
              <div className="flex items-center justify-between text-xs">
                <span className="font-bold text-slate-800 uppercase tracking-wider text-[11px]">
                  Score Breakdown By Color &amp; Component:
                </span>
                <span className="text-slate-500 text-[11px] font-medium">
                  Sum Total: <strong className="text-slate-900">{totalScore}%</strong>
                </span>
              </div>

              {/* Responsive Pill Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-xs">
                {segments.map((seg) => {
                  const isHovered = hoveredPillarId === seg.id;
                  const pts = isHealthy ? seg.scoreHigh : seg.scoreLow;

                  return (
                    <div
                      key={seg.id}
                      onMouseEnter={() => setHoveredPillarId(seg.id)}
                      onMouseLeave={() => setHoveredPillarId(null)}
                      className={`p-2 rounded-xl border transition cursor-pointer flex items-center justify-between gap-2 ${
                        isHovered
                          ? "bg-slate-100 border-slate-400 shadow-sm ring-1 ring-slate-400"
                          : "bg-slate-50/80 border-slate-200 hover:bg-slate-100/80"
                      }`}
                    >
                      <div className="flex items-center gap-2 min-w-0">
                        {/* Color Swatch Dot */}
                        <span
                          className="w-3.5 h-3.5 rounded-full flex-shrink-0 shadow-sm"
                          style={{ backgroundColor: seg.color }}
                        />
                        <div className="min-w-0">
                          <p className="font-bold text-slate-900 text-[11px] truncate">
                            {seg.shortLabel}
                          </p>
                          <span className="text-[10px] text-slate-500 block truncate">
                            {seg.colorName}
                          </span>
                        </div>
                      </div>

                      <div className="text-right flex-shrink-0">
                        <span className="font-bold font-mono text-xs text-slate-900">
                          +{pts}%
                        </span>
                        <span className="text-[10px] text-slate-400 block font-medium">
                          /{seg.weight}
                        </span>
                      </div>
                    </div>
                  );
                })}

                {/* Gray Segment (Remaining Gap) */}
                <div className="p-2 rounded-xl border border-slate-200 bg-slate-50/50 flex items-center justify-between gap-2 text-slate-400">
                  <div className="flex items-center gap-2">
                    <span className="w-3.5 h-3.5 rounded-full bg-slate-200 flex-shrink-0" />
                    <div>
                      <p className="font-bold text-slate-500 text-[11px]">Optimization Gap</p>
                      <span className="text-[10px] text-slate-400 block">Light Gray</span>
                    </div>
                  </div>
                  <span className="font-mono text-xs font-semibold text-slate-400">
                    {100 - totalScore}% Left
                  </span>
                </div>
              </div>

              {/* Exact Formula String */}
              <div className="pt-2 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 text-[10px] sm:text-[11px] text-slate-500">
                <span className="font-sans font-semibold text-slate-600">Calculation Formula:</span>
                <span className="bg-slate-100 p-1.5 sm:px-2 sm:py-0.5 rounded text-slate-700 font-mono font-bold break-words leading-relaxed">
                  {segments[0].scoreVal}% (Green) + {segments[1].scoreVal}% (Indigo) + {segments[2].scoreVal}% (Purple) + {segments[3].scoreVal}% (Sky) + {segments[4].scoreVal}% (Amber) = {totalScore}% Total
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* 5 Concrete Pillars Breakdown Grid */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="font-bold text-slate-900 text-base">In-Depth Pillar Diagnostics</h3>
            <p className="text-xs text-slate-500">
              Review each colored component and its contribution to the final 82% score.
            </p>
          </div>
          <span className="text-xs font-bold text-slate-600 bg-slate-100 px-3 py-1 rounded-lg">
            Total Points: {totalScore} / 100
          </span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {PILLARS.map((pillar) => {
            const Icon = pillar.icon;
            const currentPoints = isHealthy ? pillar.scoreHigh : pillar.scoreLow;
            const percentage = Math.round((currentPoints / pillar.weight) * 100);
            const isPillarHealthy = percentage >= 70;
            const isHovered = hoveredPillarId === pillar.id;

            return (
              <div
                key={pillar.id}
                onMouseEnter={() => setHoveredPillarId(pillar.id)}
                onMouseLeave={() => setHoveredPillarId(null)}
                className={`bg-white border-2 rounded-2xl p-5 transition flex flex-col justify-between ${
                  isHovered
                    ? "border-indigo-500 shadow-md ring-1 ring-indigo-500"
                    : "border-slate-200 shadow-sm hover:border-slate-300"
                }`}
              >
                <div>
                  {/* Pillar Header with Color Badge */}
                  <div className="flex items-center justify-between gap-2 mb-2">
                    <div className="flex items-center gap-2.5">
                      <div
                        className="w-9 h-9 rounded-xl flex items-center justify-center text-white shadow-sm flex-shrink-0"
                        style={{ backgroundColor: pillar.color }}
                      >
                        <Icon className="w-4 h-4" />
                      </div>
                      <div>
                        <div className="flex items-center gap-1.5">
                          <h4 className="font-bold text-slate-900 text-sm">{pillar.name}</h4>
                          <span
                            className="w-2.5 h-2.5 rounded-full inline-block"
                            style={{ backgroundColor: pillar.color }}
                            title={`Represented by ${pillar.colorName}`}
                          />
                        </div>
                        <span className="text-[11px] text-slate-400 font-medium">
                          {pillar.colorName} • Weight: {pillar.weight} Pts
                        </span>
                      </div>
                    </div>

                    <div className="text-right">
                      <span className="font-mono font-bold text-base text-slate-900">
                        {currentPoints}
                        <span className="text-xs text-slate-400">/{pillar.weight}</span>
                      </span>
                      <span
                        className={`block text-[10px] font-bold uppercase tracking-wider ${
                          isPillarHealthy ? "text-emerald-600" : "text-rose-600"
                        }`}
                      >
                        {percentage}% Score
                      </span>
                    </div>
                  </div>

                  {/* Progress Bar */}
                  <div className="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden my-3">
                    <div
                      className="h-full rounded-full transition-all duration-700"
                      style={{
                        width: `${percentage}%`,
                        backgroundColor: isPillarHealthy ? pillar.color : "#f43f5e",
                      }}
                    />
                  </div>

                  <p className="text-xs text-slate-500 mb-3">{pillar.description}</p>

                  {/* Diagnostic Checklist */}
                  <div className="space-y-1.5 pt-2 border-t border-slate-100 text-xs">
                    {(isHealthy ? pillar.checklistHigh : pillar.checklistLow).map((check, cidx) => (
                      <div key={cidx} className="flex items-start gap-2">
                        {isPillarHealthy ? (
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 flex-shrink-0 mt-0.5" />
                        ) : (
                          <AlertCircle className="w-3.5 h-3.5 text-rose-500 flex-shrink-0 mt-0.5" />
                        )}
                        <span className={isPillarHealthy ? "text-slate-600" : "text-slate-700 font-medium"}>
                          {check}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Reality Check / Growth Upsell Box when score is low OR visible for enhancement */}
      <div
        className={`rounded-3xl p-6 sm:p-8 border-2 transition ${
          !isHealthy
            ? "bg-slate-900 text-white border-slate-800 shadow-xl"
            : "bg-indigo-50/50 border-indigo-200 text-slate-900"
        }`}
      >
        <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
          <div className="space-y-2 max-w-2xl">
            <div className="flex items-center gap-2">
              <span
                className={`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${
                  !isHealthy
                    ? "bg-amber-400 text-slate-950"
                    : "bg-indigo-600 text-white"
                }`}
              >
                {!isHealthy ? "Recommended Next Step" : "Optional Enhancement Service"}
              </span>
              <span className={!isHealthy ? "text-slate-400 text-xs" : "text-slate-500 text-xs"}>
                Grow Before You Launch
              </span>
            </div>

            <h3 className={`text-xl sm:text-2xl font-black tracking-tight ${!isHealthy ? "text-white" : "text-slate-900"}`}>
              {!isHealthy
                ? "Pehle apni website enhance karein aur traffic badhwayein!"
                : "Want to scale your website traffic even faster alongside your app?"}
            </h3>

            <p className={`text-xs sm:text-sm leading-relaxed ${!isHealthy ? "text-slate-300" : "text-slate-600"}`}>
              {!isHealthy
                ? "Agar aapka readiness score 50% se kam hai, to humari dedicated eCommerce Growth Team aapki website ka SEO, Mobile Speed, Google/Meta Ads aur Catalog structure optimize karegi taaki aapka score 85%+ pahuche aur app aane par record sales milein."
                : "Our growth experts can audit your store for SEO, conversion rate optimization (CRO), and targeted traffic campaigns while you build your mobile app."}
            </p>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 text-xs">
              <div className="flex items-center gap-1.5 font-semibold">
                <Rocket className="w-4 h-4 text-emerald-400 flex-shrink-0" />
                <span>SEO & Traffic Boost</span>
              </div>
              <div className="flex items-center gap-1.5 font-semibold">
                <Zap className="w-4 h-4 text-sky-400 flex-shrink-0" />
                <span>Speed Optimization</span>
              </div>
              <div className="flex items-center gap-1.5 font-semibold">
                <ShoppingBag className="w-4 h-4 text-amber-400 flex-shrink-0" />
                <span>Catalog Cleanup</span>
              </div>
              <div className="flex items-center gap-1.5 font-semibold">
                <BarChart3 className="w-4 h-4 text-purple-400 flex-shrink-0" />
                <span>Sales Conversion CRO</span>
              </div>
            </div>
          </div>

          <button
            type="button"
            onClick={() => setIsConsultModalOpen(true)}
            className={`w-full md:w-auto px-6 py-3.5 rounded-2xl font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2 flex-shrink-0 shadow-lg ${
              !isHealthy
                ? "bg-amber-400 hover:bg-amber-300 text-slate-950 shadow-amber-400/20 active:scale-95"
                : "bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20"
            }`}
          >
            <PhoneCall className="w-4 h-4" />
            <span>Request Free Website & Traffic Consultation</span>
          </button>
        </div>
      </div>

      {/* Navigation Footer */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">
        <div>
          {!isHealthy ? (
            <p className="text-xs text-amber-700 font-semibold">
              ⚠️ Recommendation: Complete store enhancement before proceeding to payment.
            </p>
          ) : (
            <p className="text-xs text-emerald-700 font-semibold flex items-center gap-1.5">
              <CheckCircle2 className="w-4 h-4" />
              <span>All 5 readiness pillars validated. Ready for mobile feature selection.</span>
            </p>
          )}
        </div>

        <div className="flex flex-col sm:flex-row items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
          {!isHealthy && (
            <button
              type="button"
              onClick={() => setIsConsultModalOpen(true)}
              className="w-full sm:w-auto px-5 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2"
            >
              <span>Enhance Store First</span>
            </button>
          )}

          <button
            onClick={onNext}
            className={`w-full sm:w-auto justify-center px-6 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 shadow-md ${
              isHealthy
                ? "bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20"
                : "bg-slate-200 hover:bg-slate-300 text-slate-700"
            }`}
          >
            <span>{isHealthy ? "Continue to Feature Toggles" : "Proceed Anyway (Advanced)"}</span>
            <ArrowRight className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Website & Traffic Growth Consultation Modal */}
      {isConsultModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative border border-slate-200">
            <button
              onClick={() => {
                setIsConsultModalOpen(false);
                setConsultSubmitted(false);
              }}
              className="absolute top-5 right-5 p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
            >
              <X className="w-5 h-5" />
            </button>

            {consultSubmitted ? (
              <div className="py-8 text-center space-y-4">
                <div className="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
                  <Check className="w-8 h-8 stroke-[3]" />
                </div>
                <h3 className="text-xl font-bold text-slate-900">Growth Request Received!</h3>
                <p className="text-xs sm:text-sm text-slate-500 max-w-md mx-auto leading-relaxed">
                  Thank you! Our eCommerce Growth Specialist will review your website metrics and reach out to you via WhatsApp / Phone at <strong className="text-slate-800">{consultForm.phone}</strong> within 2 hours.
                </p>
                <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-left text-xs text-slate-600 space-y-1 max-w-sm mx-auto">
                  <div><strong>Store:</strong> {consultForm.websiteUrl || "Connected Store"}</div>
                  <div><strong>Focus:</strong> {consultForm.goal.toUpperCase()}</div>
                  <div><strong>Next Step:</strong> Free 1-on-1 Strategy Call</div>
                </div>
                <button
                  type="button"
                  onClick={() => {
                    setIsConsultModalOpen(false);
                    setConsultSubmitted(false);
                  }}
                  className="px-6 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition"
                >
                  Done & Close
                </button>
              </div>
            ) : (
              <form onSubmit={handleConsultSubmit} className="space-y-5">
                <div>
                  <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold mb-2">
                    <Rocket className="w-3.5 h-3.5" />
                    <span>Free Store Enhancement Service</span>
                  </div>
                  <h3 className="text-xl font-bold text-slate-900">
                    Grow Your Website Traffic & Sales
                  </h3>
                  <p className="text-xs text-slate-500 mt-1">
                    Enter your contact details. Humari team aapki website ka SEO, Speed aur Traffic badhane ke liye complete roadmap taiyar karegi.
                  </p>
                </div>

                <div className="space-y-4 text-xs">
                  <div>
                    <label className="font-bold text-slate-700 block mb-1">Your Full Name</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Rahul Sharma"
                      value={consultForm.name}
                      onChange={(e) => setConsultForm({ ...consultForm, name: e.target.value })}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                    />
                  </div>

                  <div>
                    <label className="font-bold text-slate-700 block mb-1">WhatsApp / Phone Number</label>
                    <input
                      type="tel"
                      required
                      placeholder="+91 98765 43210"
                      value={consultForm.phone}
                      onChange={(e) => setConsultForm({ ...consultForm, phone: e.target.value })}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                    />
                  </div>

                  <div>
                    <label className="font-bold text-slate-700 block mb-1">Store / Website URL</label>
                    <input
                      type="url"
                      placeholder="https://yourstore.com"
                      value={consultForm.websiteUrl}
                      onChange={(e) => setConsultForm({ ...consultForm, websiteUrl: e.target.value })}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"
                    />
                  </div>

                  <div>
                    <label className="font-bold text-slate-700 block mb-1">Primary Growth Requirement</label>
                    <select
                      value={consultForm.goal}
                      onChange={(e) => setConsultForm({ ...consultForm, goal: e.target.value })}
                      className="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 bg-white"
                    >
                      <option value="traffic">Increase Daily Organic & Ad Traffic (SEO + Meta Ads)</option>
                      <option value="speed">Speed Up Store & Mobile Page Load (TTFB / Caching)</option>
                      <option value="catalog">Product Catalog & High-Res Photography Optimization</option>
                      <option value="full">Complete Store Revamp & Conversion Rate Boost (CRO)</option>
                    </select>
                  </div>
                </div>

                <div className="pt-2">
                  <button
                    type="submit"
                    className="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 active:scale-95"
                  >
                    <MessageSquare className="w-4 h-4" />
                    <span>Submit & Request Growth Call</span>
                  </button>
                  <p className="text-[11px] text-slate-400 text-center mt-2">
                    100% Free consultation • No spam • Direct WhatsApp reach out
                  </p>
                </div>
              </form>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
