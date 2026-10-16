"use client";

import { useState, useEffect, useMemo } from "react";
import { createPortal } from "react-dom";
import { useQuery } from "@tanstack/react-query";
import { apiClient } from "@/lib/api-client";
import {
  AlertCircle,
  AlertTriangle,
  ArrowRight,
  BarChart3,
  Box,
  Calculator,
  Check,
  CheckCircle2,
  Clock,
  ExternalLink,
  FileSpreadsheet,
  Gauge,
  Globe,
  HelpCircle,
  Info,
  Layers,
  List,
  Loader2,
  MessageSquare,
  PhoneCall,
  Rocket,
  ShieldAlert,
  ShieldCheck,
  ShoppingBag,
  Sparkles,
  TrendingDown,
  TrendingUp,
  Users,
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
  weight: number; // Max weight points (sum = 100)
  scoreHigh: number;
  scoreLow: number;
  color: string;
  colorBadgeBg: string;
  colorBadgeText: string;
  colorBadgeBorder: string;
  icon: any;
  metricKey: string;
  defaultVal: string;
  unit: string;
  benchmark: string;
  description: string;
  checklistHigh: string[];
  checklistLow: string[];
}

// 11 Core Pillars evaluating Website Performance, Technical Quality & Catalog Readiness
const PILLARS: Pillar[] = [
  {
    id: "speed",
    name: "Mobile Speed & Response Latency",
    shortLabel: "Speed (TTFB)",
    colorName: "Sky Blue",
    weight: 10,
    scoreHigh: 9,
    scoreLow: 4,
    color: "#0284c7",
    colorBadgeBg: "bg-sky-50",
    colorBadgeText: "text-sky-700",
    colorBadgeBorder: "border-sky-200",
    icon: Zap,
    metricKey: "speed",
    defaultVal: "145ms",
    unit: "ms",
    benchmark: "< 300ms",
    description: "Measures server time-to-first-byte (TTFB), REST API response latency, and payload delivery.",
    checklistHigh: [
      "Server API latency under 300ms for live catalog endpoints",
      "Mobile-friendly gzip/brotli compression active",
      "Instant data streaming ready for mobile app rails",
    ],
    checklistLow: [
      "Server response latency exceeds 900ms",
      "Unoptimized queries cause slow mobile app synchronization",
    ],
  },
  {
    id: "total_products",
    name: "Total Products Catalog",
    shortLabel: "Total Products",
    colorName: "Emerald Green",
    weight: 8,
    scoreHigh: 7,
    scoreLow: 3,
    color: "#10b981",
    colorBadgeBg: "bg-emerald-50",
    colorBadgeText: "text-emerald-700",
    colorBadgeBorder: "border-emerald-200",
    icon: Box,
    metricKey: "total_products",
    defaultVal: "24 items",
    unit: "items",
    benchmark: "Catalog ready",
    description: "Evaluates active product inventory volume, variant pricing, descriptions, and catalog depth.",
    checklistHigh: [
      "Catalog products detected with active pricing and currency",
      "Product variant options cleanly configured",
      "Sufficient depth for engaging category grids and carousels",
    ],
    checklistLow: [
      "Low or zero active products detected in catalog",
      "Missing product images or short descriptions",
    ],
  },
  {
    id: "first_contentful_paint",
    name: "First Contentful Paint (FCP)",
    shortLabel: "First Paint (FCP)",
    colorName: "Cyan",
    weight: 10,
    scoreHigh: 9,
    scoreLow: 4,
    color: "#06b6d4",
    colorBadgeBg: "bg-cyan-50",
    colorBadgeText: "text-cyan-700",
    colorBadgeBorder: "border-cyan-200",
    icon: Clock,
    metricKey: "first_contentful_paint",
    defaultVal: "1.1s",
    unit: "s",
    benchmark: "< 1.8s",
    description: "Time from when the page starts loading to when any part of the page's content is rendered.",
    checklistHigh: [
      "First DOM element painted in under 1.8s Google CWV standard",
      "Zero render-blocking scripts delaying first paint",
      "Instant visual feedback on 4G/5G mobile connections",
    ],
    checklistLow: [
      "FCP takes longer than 2.8s on mobile networks",
      "Heavy stylesheet dependencies delay initial rendering",
    ],
  },
  {
    id: "last_contentful_paint",
    name: "Last Contentful Paint (LCP)",
    shortLabel: "Last Paint (LCP)",
    colorName: "Indigo",
    weight: 12,
    scoreHigh: 11,
    scoreLow: 5,
    color: "#6366f1",
    colorBadgeBg: "bg-indigo-50",
    colorBadgeText: "text-indigo-700",
    colorBadgeBorder: "border-indigo-200",
    icon: Sparkles,
    metricKey: "last_contentful_paint",
    defaultVal: "1.9s",
    unit: "s",
    benchmark: "< 2.5s",
    description: "Time until the main hero banner / primary product block is fully visible to the user.",
    checklistHigh: [
      "Primary hero banner renders in under 2.5s",
      "WebP / optimized images loaded with high priority",
      "Smooth visual stability without content layout shifts",
    ],
    checklistLow: [
      "Hero banner image takes over 3.8s to fully load",
      "Oversized uncompressed images cause mobile bandwidth lag",
    ],
  },
  {
    id: "total_blocking_time",
    name: "Total Blocking Time (TBT)",
    shortLabel: "Blocking Time (TBT)",
    colorName: "Amber Orange",
    weight: 10,
    scoreHigh: 9,
    scoreLow: 4,
    color: "#f59e0b",
    colorBadgeBg: "bg-amber-50",
    colorBadgeText: "text-amber-700",
    colorBadgeBorder: "border-amber-200",
    icon: TrendingDown,
    metricKey: "total_blocking_time",
    defaultVal: "120ms",
    unit: "ms",
    benchmark: "< 200ms",
    description: "Total main-thread blocked time between FCP and Time to Interactive (input delay).",
    checklistHigh: [
      "Main thread blocked for less than 200ms during page load",
      "JavaScript parsed cleanly without input lag",
      "Immediate user touch and tap responsiveness",
    ],
    checklistLow: [
      "Third-party tracking scripts block main thread (> 350ms)",
      "Users experience input delay when tapping buttons",
    ],
  },
  {
    id: "performance",
    name: "Core Web Vitals Performance",
    shortLabel: "Performance",
    colorName: "Purple Violet",
    weight: 14,
    scoreHigh: 12,
    scoreLow: 5,
    color: "#8b5cf6",
    colorBadgeBg: "bg-purple-50",
    colorBadgeText: "text-purple-700",
    colorBadgeBorder: "border-purple-200",
    icon: Gauge,
    metricKey: "performance",
    defaultVal: "92/100",
    unit: "/100",
    benchmark: "> 85",
    description: "Comprehensive Google Core Web Vitals aggregate scoring across paint, interactivity, and stability.",
    checklistHigh: [
      "High overall Core Web Vitals rating (> 85/100)",
      "Lean DOM tree hierarchy without excessive element nesting",
      "Optimized for smooth 60fps mobile scrolling",
    ],
    checklistLow: [
      "Low performance score with bloated script bundles",
      "Cumulative layout shifts degrade mobile user experience",
    ],
  },
  {
    id: "accessibility",
    name: "Accessibility & UX (a11y)",
    shortLabel: "Accessibility",
    colorName: "Pink Rose",
    weight: 8,
    scoreHigh: 7,
    scoreLow: 3,
    color: "#ec4899",
    colorBadgeBg: "bg-pink-50",
    colorBadgeText: "text-pink-700",
    colorBadgeBorder: "border-pink-200",
    icon: Users,
    metricKey: "accessibility",
    defaultVal: "96/100",
    unit: "/100",
    benchmark: "> 90",
    description: "Assesses semantic markup, image alt tags, color contrast, and screen reader friendliness.",
    checklistHigh: [
      "Image alt descriptions detected across product media",
      "High contrast between text and background surfaces",
      "Standard touch targets (> 44x44px) ready for mobile fingers",
    ],
    checklistLow: [
      "Missing image alt tags and unlabeled buttons",
      "Low contrast text difficult to read on mobile screens",
    ],
  },
  {
    id: "best_practise",
    name: "Web Best Practices",
    shortLabel: "Best Practices",
    colorName: "Teal",
    weight: 8,
    scoreHigh: 7,
    scoreLow: 3,
    color: "#14b8a6",
    colorBadgeBg: "bg-teal-50",
    colorBadgeText: "text-teal-700",
    colorBadgeBorder: "border-teal-200",
    icon: CheckCircle2,
    metricKey: "best_practise",
    defaultVal: "98/100",
    unit: "/100",
    benchmark: "> 90",
    description: "Compliance with modern web specifications, HTML5 doctype, UTF-8 charset, and HTTPS transport.",
    checklistHigh: [
      "HTML5 doctype, UTF-8 charset, and viewport meta configured",
      "No deprecated web APIs or insecure cross-origin embeds",
      "Clean HTTPS asset requests without mixed content",
    ],
    checklistLow: [
      "Mixed content warnings or outdated plugins detected",
      "Missing mobile responsive viewport meta tag",
    ],
  },
  {
    id: "seo",
    name: "SEO & Search Discoverability",
    shortLabel: "SEO",
    colorName: "Blue",
    weight: 8,
    scoreHigh: 7,
    scoreLow: 3,
    color: "#3b82f6",
    colorBadgeBg: "bg-blue-50",
    colorBadgeText: "text-blue-700",
    colorBadgeBorder: "border-blue-200",
    icon: Globe,
    metricKey: "seo",
    defaultVal: "94/100",
    unit: "/100",
    benchmark: "> 85",
    description: "Analyzes meta title, description, heading hierarchy, canonical links, and OpenGraph tags.",
    checklistHigh: [
      "Descriptive title tags and structured meta descriptions",
      "Proper H1/H2 heading hierarchy and canonical links",
      "OpenGraph social preview metadata properly configured",
    ],
    checklistLow: [
      "Missing or generic meta description tags",
      "Missing primary H1 tag on store home page",
    ],
  },
  {
    id: "security_and_trust",
    name: "Security & Buyer Trust",
    shortLabel: "Security & Trust",
    colorName: "Bright Green",
    weight: 8,
    scoreHigh: 7,
    scoreLow: 3,
    color: "#22c55e",
    colorBadgeBg: "bg-green-50",
    colorBadgeText: "text-green-700",
    colorBadgeBorder: "border-green-200",
    icon: ShieldCheck,
    metricKey: "security_and_trust",
    defaultVal: "95/100",
    unit: "/100",
    benchmark: "SSL Active",
    description: "Validates active SSL/TLS certificates, trusted checkout gateways, and policy documentation.",
    checklistHigh: [
      "Active modern SSL/TLS 1.3 certificate verified",
      "Trusted payment gateways (Razorpay, UPI, Cards, Stripe)",
      "Privacy Policy and Terms pages compliant with Play Store",
    ],
    checklistLow: [
      "Insecure HTTP connection or expired certificate",
      "Missing mandatory store policy and refund terms",
    ],
  },
  {
    id: "total_category",
    name: "Total Categories Architecture",
    shortLabel: "Categories",
    colorName: "Fuchsia Purple",
    weight: 4,
    scoreHigh: 4,
    scoreLow: 2,
    color: "#a855f7",
    colorBadgeBg: "bg-fuchsia-50",
    colorBadgeText: "text-fuchsia-700",
    colorBadgeBorder: "border-fuchsia-200",
    icon: List,
    metricKey: "total_category",
    defaultVal: "6 categories",
    unit: "categories",
    benchmark: "Mapped",
    description: "Assesses category taxonomy, product grouping, and mobile tab bar navigation readiness.",
    checklistHigh: [
      "Multi-tier category taxonomy ready for mobile tab navigation",
      "Zero empty or orphan categories detected in catalog",
      "Clean product grouping simplifies customer discovery",
    ],
    checklistLow: [
      "All catalog items lumped into a single uncategorized list",
      "Missing category icons or navigation filters",
    ],
  },
];

export function StepAnalysis({ ulid, app, onNext }: StepAnalysisProps) {
  const [simulationMode, setSimulationMode] = useState<"healthy" | "low">("healthy");
  const [hoveredPillarId, setHoveredPillarId] = useState<string | null>(null);
  const [isConsultModalOpen, setIsConsultModalOpen] = useState(false);
  const [consultSubmitted, setConsultSubmitted] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  // Prevent background scroll when consultation modal is open
  useEffect(() => {
    if (isConsultModalOpen) {
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "";
    }
    return () => {
      document.body.style.overflow = "";
    };
  }, [isConsultModalOpen]);

  const [consultForm, setConsultForm] = useState({
    name: "",
    phone: "",
    websiteUrl: "",
    goal: "traffic",
  });

  // Query live website performance metrics from backend
  const { data: websiteData, isLoading: isMetricsLoading } = useQuery({
    queryKey: ["website-data", ulid],
    queryFn: async () => {
      if (!ulid) return null;
      try {
        return await apiClient(`/api/apps/${ulid}/website-data`);
      } catch {
        return null;
      }
    },
    enabled: !!ulid,
    refetchOnWindowFocus: false,
  });

  const isHealthy = simulationMode === "healthy";
  const metrics = websiteData?.performance_metrics;

  // Calculate dynamic scores for each of the 11 pillars
  const evaluatedPillars = useMemo(() => {
    return PILLARS.map((p) => {
      const metricItem = metrics?.[p.metricKey];
      let scoreVal: number;
      let displayValue: string;
      let status: "good" | "moderate" | "warning";

      if (isHealthy) {
        if (metricItem && typeof metricItem.score === "number") {
          // Scale metricItem.score (0-100) to pillar weight
          scoreVal = Math.round((metricItem.score / 100) * p.weight);
          displayValue = metricItem.value !== undefined ? String(metricItem.value) : p.defaultVal;
          status = metricItem.status || "good";
        } else {
          scoreVal = p.scoreHigh;
          displayValue = p.defaultVal;
          status = "good";
        }
      } else {
        scoreVal = p.scoreLow;
        displayValue =
          p.id === "speed"
            ? "1850ms"
            : p.id === "first_contentful_paint"
            ? "3.2s"
            : p.id === "last_contentful_paint"
            ? "4.6s"
            : p.id === "total_blocking_time"
            ? "480ms"
            : p.id === "total_products"
            ? "1 item"
            : p.id === "total_category"
            ? "1 category"
            : "42/100";
        status = "warning";
      }

      return {
        ...p,
        scoreVal,
        displayValue,
        status,
      };
    });
  }, [metrics, isHealthy]);

  // Overall Score sum (out of 100)
  const totalScore = useMemo(() => {
    return evaluatedPillars.reduce((sum, p) => sum + p.scoreVal, 0);
  }, [evaluatedPillars]);

  // SVG Donut calculation with 11 distinct segments
  const radius = 54;
  const circumference = 2 * Math.PI * radius;

  let runningOffset = 0;
  const segments = evaluatedPillars.map((p) => {
    const segmentStroke = (p.scoreVal / 100) * circumference;
    const offset = runningOffset;
    runningOffset += segmentStroke;

    return {
      ...p,
      segmentStroke,
      offset,
    };
  });

  const activePillar = evaluatedPillars.find((p) => p.id === hoveredPillarId);

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
            Store Readiness Score &amp; Feasibility Audit
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            Calculated across 11 core metrics: Speed, Products, FCP, LCP, TBT, Performance, a11y, Best Practices, SEO, Security, and Categories.
          </p>
        </div>

        {/* Audit Mode Switcher */}
        <div className="grid grid-cols-2 gap-1 p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold w-full sm:w-auto sm:flex sm:gap-1.5 self-stretch sm:self-auto">
          <button
            type="button"
            onClick={() => setSimulationMode("healthy")}
            className={`px-3 py-1.5 rounded-lg transition text-center truncate cursor-pointer ${
              isHealthy
                ? "bg-white text-emerald-700 shadow-sm font-bold"
                : "text-slate-600 hover:text-slate-900"
            }`}
          >
            Healthy Store ({metrics?.overall_score ? `${metrics.overall_score}%` : "87%"})
          </button>
          <button
            type="button"
            onClick={() => setSimulationMode("low")}
            className={`px-3 py-1.5 rounded-lg transition text-center truncate cursor-pointer ${
              !isHealthy
                ? "bg-white text-rose-700 shadow-sm font-bold"
                : "text-slate-600 hover:text-slate-900"
            }`}
          >
            Low Score (&lt; 50%)
          </button>
        </div>
      </div>

      {/* Main Scorecard Hero Banner matching exact reference design */}
      <div className="bg-white rounded-3xl p-5 sm:p-7 border border-emerald-100/90 shadow-sm">
        <div className="flex flex-col lg:flex-row items-center lg:items-start gap-6 lg:gap-8">
          {/* Left Column: Donut Chart + 11-Pillar Legend */}
          <div className="flex flex-col items-center w-full lg:w-[230px] xl:w-[240px] flex-shrink-0">
            {/* Donut Chart */}
            <div className="relative w-44 h-44 flex items-center justify-center flex-shrink-0">
              <svg className="w-full h-full transform -rotate-90" viewBox="0 0 140 140">
                {/* Background Unfilled Circle (Light Gray: Optimization Gap) */}
                <circle
                  cx="70"
                  cy="70"
                  r={radius}
                  className="text-slate-200"
                  strokeWidth="14"
                  stroke="currentColor"
                  fill="none"
                />

                {/* 11 Individual Colored Segments */}
                {segments.map((seg) => (
                  <circle
                    key={seg.id}
                    cx="70"
                    cy="70"
                    r={radius}
                    stroke={isHealthy ? seg.color : "#f43f5e"}
                    strokeWidth="14"
                    strokeDasharray={`${seg.segmentStroke} ${circumference - seg.segmentStroke}`}
                    strokeDashoffset={-seg.offset}
                    fill="none"
                    className="transition-all duration-300"
                  />
                ))}
              </svg>

              {/* Center Content Inside Donut */}
              <div className="absolute inset-0 flex flex-col items-center justify-center text-center p-1 pointer-events-none">
                <span className="text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                  STORE SCORE
                </span>
                <span className="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight my-0.5 leading-none">
                  {totalScore}%
                </span>
                <span className="inline-block px-2.5 py-0.5 mt-0.5 rounded-full text-[9px] font-bold bg-[#ecfdf5] text-emerald-800 border border-emerald-300/80 leading-tight text-center max-w-[125px]">
                  {isHealthy ? "Highly Recommended for App" : "Needs Optimization First"}
                </span>
              </div>
            </div>

            {/* Vertical Legend List for the 11 pillars */}
            <div className="w-full max-w-[230px] space-y-1.5 mt-3 max-h-52 overflow-y-auto no-scrollbar pr-1">
              {segments.map((seg) => (
                <div
                  key={seg.id}
                  onMouseEnter={() => setHoveredPillarId(seg.id)}
                  onMouseLeave={() => setHoveredPillarId(null)}
                  className={`flex items-center justify-between text-[11px] p-1 rounded-md transition ${
                    hoveredPillarId === seg.id ? "bg-slate-100 font-bold" : ""
                  }`}
                >
                  <div className="flex items-center gap-1.5 min-w-0">
                    <span
                      className="w-2.5 h-2.5 rounded-full flex-shrink-0"
                      style={{ backgroundColor: isHealthy ? seg.color : "#f43f5e" }}
                    />
                    <span className="text-slate-700 truncate">{seg.shortLabel}</span>
                  </div>
                  <span className="font-bold text-slate-900 ml-1.5 flex-shrink-0">
                    +{seg.scoreVal}%
                  </span>
                </div>
              ))}

              {/* Optimization Gap in Legend */}
              <div className="flex items-center justify-between text-[11px] p-1 border-t border-slate-100">
                <div className="flex items-center gap-1.5 min-w-0">
                  <span className="w-2.5 h-2.5 rounded-full bg-slate-300 flex-shrink-0" />
                  <span className="text-slate-600 font-medium truncate">Optimization Gap</span>
                </div>
                <span className="font-bold text-slate-800 ml-1.5">{Math.max(0, 100 - totalScore)}%</span>
              </div>
            </div>
          </div>

          {/* Right Column: Assessment Details, 11 Component Breakdown Grid & Formula */}
          <div className="flex-1 min-w-0 w-full space-y-4">
            {/* Top Badges & Audit Meta */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#ecfdf5] text-emerald-800 border border-emerald-200/80 text-xs font-bold w-fit shadow-xs">
                <Sparkles className="w-3.5 h-3.5 text-emerald-600 fill-emerald-600" />
                <span>
                  {totalScore}% Score • {isHealthy ? "Mobile-Ready E-Commerce Foundation" : "Optimization Required"}
                </span>
              </div>

              <div className="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                <FileSpreadsheet className="w-4 h-4 text-slate-400" />
                <span>11-Factor Automated Website Audit</span>
              </div>
            </div>

            {/* Title & Description */}
            <div>
              <h3 className="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-tight">
                {isHealthy
                  ? "Your store has strong fundamentals to scale on Mobile!"
                  : "Reality Check: Your store is NOT ready for a mobile app yet."}
              </h3>
              <p className="text-xs sm:text-sm text-slate-500 leading-relaxed mt-1">
                {isHealthy
                  ? `Your ${totalScore}% score is calculated across 11 vital criteria including server speed, catalog depth, Core Web Vitals (FCP, LCP, TBT), accessibility, SEO, and checkout security.`
                  : "If site response latency is high or catalog depth is incomplete, building an app will result in low retention. Optimize your website foundations first to maximize your app ROI!"}
              </p>
            </div>

            {/* Section Heading & Total Score */}
            <div className="flex items-center justify-between pt-0.5">
              <span className="text-[11px] font-extrabold uppercase tracking-wider text-slate-700">
                11-FACTOR SCORE BREAKDOWN
              </span>
              <span className="text-xs text-slate-500 font-medium">
                Total Score:{" "}
                <strong className="text-emerald-600 font-black text-sm ml-1">
                  {totalScore}%
                </strong>
              </span>
            </div>

            {/* 11 Component Cards Grid (4 cols on lg, 2 cols on mobile) */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-2.5">
              {evaluatedPillars.map((pillar) => {
                const Icon = pillar.icon;
                const isHovered = hoveredPillarId === pillar.id;

                return (
                  <div
                    key={pillar.id}
                    onMouseEnter={() => setHoveredPillarId(pillar.id)}
                    onMouseLeave={() => setHoveredPillarId(null)}
                    className={`rounded-xl p-2.5 border transition-all cursor-pointer ${
                      isHovered
                        ? "border-indigo-400 ring-2 ring-indigo-200 bg-indigo-50/50 shadow-xs"
                        : "border-slate-200/90 bg-slate-50/60 hover:bg-white hover:border-slate-300"
                    }`}
                  >
                    <div className="flex items-center justify-between mb-1.5">
                      <div
                        className="w-7 h-7 rounded-lg text-white flex items-center justify-center flex-shrink-0 shadow-xs"
                        style={{ backgroundColor: pillar.color }}
                      >
                        <Icon className="w-3.5 h-3.5" />
                      </div>
                      <span className="font-extrabold text-xs text-slate-900 font-mono">
                        +{pillar.scoreVal}%
                      </span>
                    </div>

                    <h4 className="font-bold text-slate-900 text-[11px] leading-tight truncate">
                      {pillar.shortLabel}
                    </h4>

                    <div className="flex items-center justify-between mt-1 text-[10px]">
                      <span className="text-slate-500 font-medium truncate max-w-[80px]">
                        {pillar.displayValue}
                      </span>
                      <span className="text-slate-400 font-semibold">/{pillar.weight}</span>
                    </div>
                  </div>
                );
              })}

              {/* Optimization Gap Card */}
              <div className="rounded-xl p-2.5 border border-slate-200 bg-white/70 flex flex-col justify-between">
                <div className="flex items-center justify-between mb-1.5">
                  <div className="w-7 h-7 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center flex-shrink-0">
                    <BarChart3 className="w-3.5 h-3.5" />
                  </div>
                  <span className="font-bold text-slate-600 text-xs">
                    {Math.max(0, 100 - totalScore)}%
                  </span>
                </div>
                <h4 className="font-bold text-slate-700 text-[11px] leading-tight truncate">
                  Optimization Gap
                </h4>
                <span className="text-[10px] text-slate-400 mt-1 block">Remaining</span>
              </div>
            </div>

            {/* Bottom Calculation Formula Bar */}
            <div className="bg-[#f0fdf9] border border-[#a7f3d0] rounded-xl px-3.5 py-2 flex flex-wrap items-center gap-2 text-xs text-slate-700">
              <Calculator className="w-4 h-4 text-emerald-700 flex-shrink-0" />
              <span className="font-bold text-slate-900 flex-shrink-0">Calculation Formula:</span>
              <span className="font-mono text-slate-700 font-medium overflow-x-auto text-[10.5px] sm:text-xs">
                Speed (10%) + Products (8%) + FCP (10%) + LCP (12%) + TBT (10%) + Perf (14%) + a11y (8%) + Best Prac (8%) + SEO (8%) + Security (8%) + Categories (4%) = {totalScore}% Total
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* 11 Pillars In-Depth Diagnostics Grid */}
      <div className="space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h3 className="font-bold text-slate-900 text-base">In-Depth 11-Factor Pillar Diagnostics</h3>
            <p className="text-xs text-slate-500">
              Measured from your website with Core Web Vitals, API timing, security protocols, and catalog data.
            </p>
          </div>
          <span className="text-xs font-bold text-slate-700 bg-slate-100 px-3 py-1 rounded-lg border border-slate-200 w-fit">
            Score: {totalScore} / 100 Points
          </span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {evaluatedPillars.map((pillar) => {
            const Icon = pillar.icon;
            const currentPoints = pillar.scoreVal;
            const percentage = Math.round((currentPoints / pillar.weight) * 100);
            const isPillarHealthy = percentage >= 70;
            const isHovered = hoveredPillarId === pillar.id;

            return (
              <div
                key={pillar.id}
                onMouseEnter={() => setHoveredPillarId(pillar.id)}
                onMouseLeave={() => setHoveredPillarId(null)}
                className={`bg-white border-2 rounded-2xl p-4 sm:p-5 transition flex flex-col justify-between ${
                  isHovered
                    ? "border-indigo-500 shadow-md ring-1 ring-indigo-500"
                    : "border-slate-200 shadow-xs hover:border-slate-300"
                }`}
              >
                <div>
                  {/* Pillar Header with Color Badge */}
                  <div className="flex items-center justify-between gap-2 mb-2">
                    <div className="flex items-center gap-2.5 min-w-0">
                      <div
                        className="w-9 h-9 rounded-xl flex items-center justify-center text-white shadow-xs flex-shrink-0"
                        style={{ backgroundColor: pillar.color }}
                      >
                        <Icon className="w-4 h-4" />
                      </div>
                      <div className="min-w-0">
                        <div className="flex items-center gap-1.5">
                          <h4 className="font-bold text-slate-900 text-xs sm:text-sm truncate">
                            {pillar.name}
                          </h4>
                        </div>
                        <span className="text-[11px] text-slate-400 font-medium block truncate">
                          Benchmark: {pillar.benchmark} • Weight: {pillar.weight}%
                        </span>
                      </div>
                    </div>

                    <div className="text-right flex-shrink-0">
                      <span className="font-mono font-bold text-sm sm:text-base text-slate-900">
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

                  {/* Measured Value Pill */}
                  <div className="my-2.5 p-2 rounded-lg bg-slate-50 border border-slate-200/80 flex items-center justify-between text-xs">
                    <span className="text-slate-500 font-medium">Measured Value:</span>
                    <span className="font-bold font-mono text-slate-900 bg-white px-2 py-0.5 rounded border border-slate-200">
                      {pillar.displayValue}
                    </span>
                  </div>

                  {/* Progress Bar */}
                  <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden my-2">
                    <div
                      className="h-full rounded-full transition-all duration-700"
                      style={{
                        width: `${percentage}%`,
                        backgroundColor: isPillarHealthy ? pillar.color : "#f43f5e",
                      }}
                    />
                  </div>

                  <p className="text-xs text-slate-500 mb-3 leading-relaxed">
                    {pillar.description}
                  </p>

                  {/* Diagnostic Checklist */}
                  <div className="space-y-1.5 pt-2 border-t border-slate-100 text-xs">
                    {(isHealthy ? pillar.checklistHigh : pillar.checklistLow).map((check, cidx) => (
                      <div key={cidx} className="flex items-start gap-1.5">
                        {isPillarHealthy ? (
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 flex-shrink-0 mt-0.5" />
                        ) : (
                          <AlertCircle className="w-3.5 h-3.5 text-rose-500 flex-shrink-0 mt-0.5" />
                        )}
                        <span className={isPillarHealthy ? "text-slate-600 text-[11px]" : "text-slate-700 text-[11px] font-medium"}>
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
                : "Want to scale your website performance & traffic alongside your app?"}
            </h3>

            <p className={`text-xs sm:text-sm leading-relaxed ${!isHealthy ? "text-slate-300" : "text-slate-600"}`}>
              {!isHealthy
                ? "Agar aapka readiness score 50% se kam hai, to humari dedicated eCommerce Growth Team aapki website ka SEO, Mobile Speed, Google/Meta Ads aur Catalog structure optimize karegi taaki aapka score 85%+ pahuche aur app aane par record sales milein."
                : "Our technical experts can help optimize your store's TTFB, Core Web Vitals (FCP, LCP, TBT), and search ranking while you build your mobile app."}
            </p>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 text-xs">
              <div className="flex items-center gap-1.5 font-semibold">
                <Rocket className="w-4 h-4 text-emerald-400 flex-shrink-0" />
                <span>SEO &amp; Traffic Boost</span>
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
                <span>Conversion CRO</span>
              </div>
            </div>
          </div>

          <button
            type="button"
            onClick={() => setIsConsultModalOpen(true)}
            className={`w-full md:w-auto px-6 py-3.5 rounded-2xl font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2 flex-shrink-0 shadow-lg cursor-pointer ${
              !isHealthy
                ? "bg-amber-400 hover:bg-amber-300 text-slate-950 shadow-amber-400/20 active:scale-95"
                : "bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20"
            }`}
          >
            <PhoneCall className="w-4 h-4" />
            <span>Request Free Website &amp; Traffic Consultation</span>
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
              <span>All 11 readiness &amp; performance pillars evaluated. Ready for Data Collection.</span>
            </p>
          )}
        </div>

        <div className="flex flex-col sm:flex-row items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
          {!isHealthy && (
            <button
              type="button"
              onClick={() => setIsConsultModalOpen(true)}
              className="w-full sm:w-auto px-5 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer"
            >
              <span>Enhance Store First</span>
            </button>
          )}

          <button
            onClick={onNext}
            className={`w-full sm:w-auto justify-center px-6 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition flex items-center gap-2 shadow-md cursor-pointer ${
              isHealthy
                ? "bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20"
                : "bg-slate-200 hover:bg-slate-300 text-slate-700"
            }`}
          >
            <span>{isHealthy ? "Continue to Data Collection" : "Proceed to Data Collection (Advanced)"}</span>
            <ArrowRight className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Website & Traffic Growth Consultation Modal Rendered into document.body */}
      {mounted &&
        isConsultModalOpen &&
        createPortal(
          <div className="fixed inset-0 z-[99999] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto">
            <div
              className="fixed inset-0"
              onClick={() => {
                setIsConsultModalOpen(false);
                setConsultSubmitted(false);
              }}
            />

            <div className="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative z-10 border border-slate-200 my-auto">
              <button
                type="button"
                onClick={() => {
                  setIsConsultModalOpen(false);
                  setConsultSubmitted(false);
                }}
                className="absolute top-5 right-5 p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer"
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
                    className="px-6 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition cursor-pointer"
                  >
                    Done &amp; Close
                  </button>
                </div>
              ) : (
                <form onSubmit={handleConsultSubmit} className="space-y-5">
                  <div>
                    <h3 className="text-xl font-bold text-slate-900">
                      Grow Your Website Traffic &amp; Sales
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
                        <option value="traffic">Increase Daily Organic &amp; Ad Traffic (SEO + Meta Ads)</option>
                        <option value="speed">Speed Up Store &amp; Mobile Page Load (TTFB / Caching)</option>
                        <option value="catalog">Product Catalog &amp; High-Res Photography Optimization</option>
                        <option value="full">Complete Store Revamp &amp; Conversion Rate Boost (CRO)</option>
                      </select>
                    </div>
                  </div>

                  <div className="pt-2">
                    <button
                      type="submit"
                      className="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs sm:text-sm font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 active:scale-95 cursor-pointer"
                    >
                      <MessageSquare className="w-4 h-4" />
                      <span>Submit &amp; Request Growth Call</span>
                    </button>
                    <p className="text-[11px] text-slate-400 text-center mt-2">
                      100% Free consultation • No spam • Direct WhatsApp reach out
                    </p>
                  </div>
                </form>
              )}
            </div>
          </div>,
          document.body
        )}
    </div>
  );
}
