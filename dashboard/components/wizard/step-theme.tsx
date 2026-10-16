"use client";

import { useState, useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, CheckCircle2, Loader2, Palette, Sparkles } from "lucide-react";
import { apiClient } from "@/lib/api-client";

interface StepThemeProps {
  ulid: string;
  onNext: () => void;
}

interface SavedThemeInfo {
  name: string;
  slug: string;
  price?: string;
  badge?: string;
}

const THEME_META: Record<
  string,
  {
    marketName: string;
    marketSlug: string;
    badge: string;
    price: string;
    description: string;
    emoji: string;
  }
> = {
  theme_a: {
    marketName: "Minimal",
    marketSlug: "minimal",
    badge: "Minimal Style",
    price: "Included in Plan",
    description: "Clean, whitespace-focused minimalist store layout",
    emoji: "🛍️",
  },
  theme_b: {
    marketName: "Modern",
    marketSlug: "modern",
    badge: "Modern Style",
    price: "₹1,999",
    description: "Editorial typography & storytelling showcase layout",
    emoji: "✨",
  },
  theme_c: {
    marketName: "Trendy",
    marketSlug: "trendy",
    badge: "Trendy Style",
    price: "₹1,499",
    description: "High-contrast dynamic energetic boutique design",
    emoji: "⚡",
  },
};

export function StepTheme({ ulid, onNext }: StepThemeProps) {
  const [selectedKey, setSelectedKey] = useState("theme_a");
  const [savedTheme, setSavedTheme] = useState<SavedThemeInfo | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  // Pre-select theme if user selected one on marketplace or prior steps
  useEffect(() => {
    try {
      const saved = localStorage.getItem("appifyhub_selected_theme");
      if (saved) {
        const parsed: SavedThemeInfo = JSON.parse(saved);
        setSavedTheme(parsed);
        if (parsed.slug === "minimal") setSelectedKey("theme_a");
        else if (parsed.slug === "modern") setSelectedKey("theme_b");
        else if (parsed.slug === "trendy") setSelectedKey("theme_c");
      }
    } catch {}
  }, []);

  const { data: themesData, isLoading } = useQuery({
    queryKey: ["themes", ulid],
    queryFn: async () => {
      try {
        const res = await apiClient(`/api/apps/${ulid}/themes`);
        return res.themes || [];
      } catch {
        // Fallback default themes
        return [
          {
            key: "theme_a",
            name: "Modern Retail",
            defaults: {
              colors: {
                primary: "#E91E63",
                secondary: "#222222",
                background: "#FFFFFF",
                surface: "#FAFAFA",
                text: "#111111",
              },
              radius: 12,
              font: "Poppins",
            },
          },
          {
            key: "theme_b",
            name: "Luxury Boutique",
            defaults: {
              colors: {
                primary: "#0F172A",
                secondary: "#C5A880",
                background: "#F8FAFC",
                surface: "#FFFFFF",
                text: "#0F172A",
              },
              radius: 8,
              font: "Outfit",
            },
          },
          {
            key: "theme_c",
            name: "Vibrant Lifestyle",
            defaults: {
              colors: {
                primary: "#4F46E5",
                secondary: "#F59E0B",
                background: "#FFFFFF",
                surface: "#F3F4F6",
                text: "#1F2937",
              },
              radius: 16,
              font: "Inter",
            },
          },
        ];
      }
    },
  });

  const themes = themesData || [];

  const handleSelect = async () => {
    setIsSaving(true);
    const chosen = themes.find((t: any) => t.key === selectedKey);

    try {
      if (chosen) {
        await apiClient(`/api/apps/${ulid}/config/draft`, {
          method: "PUT",
          body: JSON.stringify({
            theme: chosen.defaults,
          }),
        });
      }
      onNext();
    } catch {
      onNext();
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <div className="flex items-center gap-2">
          <Palette className="w-5 h-5 text-indigo-600" />
          <h2 className="text-xl font-bold text-slate-900 tracking-tight">
            Choose a Mobile Theme Preset
          </h2>
        </div>
        <p className="text-sm text-slate-500 mt-1">
          Select a layout preset tailored for your brand aesthetic. All colors, logos, and fonts can be refined in the next Branding step.
        </p>
      </div>

      {/* Auto-selected theme notification banner if user selected a theme from marketplace */}
      {savedTheme && (
        <div className="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center text-lg shadow-inner">
              {savedTheme.slug === "trendy"
                ? "⚡"
                : savedTheme.slug === "modern"
                ? "✨"
                : "🛍️"}
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="text-xs font-bold uppercase tracking-wider text-amber-950">
                  Theme Pre-Selected
                </span>
                <span className="text-[11px] font-bold text-amber-900 bg-amber-100 px-2.5 py-0.5 rounded-full border border-amber-300">
                  {savedTheme.name} ({savedTheme.price || "Active"})
                </span>
              </div>
              <p className="text-xs text-slate-600 mt-0.5">
                Automatically selected and configured from your marketplace choice. You can keep this or select another theme below anytime.
              </p>
            </div>
          </div>
          <span className="inline-flex items-center gap-1.5 text-xs font-bold text-amber-950 bg-white border border-amber-300 px-3 py-1.5 rounded-xl shadow-xs self-start sm:self-auto">
            <CheckCircle2 className="w-4 h-4 text-emerald-600" />
            <span>Auto-Selected</span>
          </span>
        </div>
      )}

      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-64 bg-slate-100 rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {themes.map((theme: any) => {
            const isSelected = selectedKey === theme.key;
            const colors = theme.defaults?.colors || {};
            const meta = THEME_META[theme.key] || {
              marketName: theme.name,
              marketSlug: theme.key,
              badge: "Theme Preset",
              price: "Included",
              description: "Optimized mobile layout",
              emoji: "📱",
            };
            const isUserMarketChoice =
              savedTheme?.slug === meta.marketSlug;

            return (
              <div
                key={theme.key}
                onClick={() => setSelectedKey(theme.key)}
                className={`p-5 rounded-2xl border-2 cursor-pointer transition flex flex-col justify-between ${
                  isSelected
                    ? "border-amber-800 bg-amber-50/20 shadow-md ring-2 ring-amber-700/20"
                    : "border-slate-200 hover:border-slate-300 bg-white shadow-sm"
                }`}
              >
                <div>
                  <div className="flex items-start justify-between mb-3">
                    <div className="flex items-center gap-2">
                      <span className="text-xl">{meta.emoji}</span>
                      <div>
                        <span className="text-[11px] font-bold uppercase tracking-wider text-amber-900 block">
                          {meta.badge}
                        </span>
                        <span className="text-[11px] font-medium text-slate-400">
                          {meta.price}
                        </span>
                      </div>
                    </div>
                    {isSelected ? (
                      <span className="w-6 h-6 rounded-full bg-amber-800 text-white flex items-center justify-center text-xs font-bold shadow-xs">
                        ✓
                      </span>
                    ) : (
                      <span className="w-6 h-6 rounded-full border border-slate-300 text-transparent flex items-center justify-center text-xs">
                        ○
                      </span>
                    )}
                  </div>

                  <h3 className="font-bold text-slate-900 text-lg mb-0.5">
                    {meta.marketName}
                  </h3>
                  <span className="text-xs font-semibold text-slate-400 block mb-2">
                    Style: {theme.name}
                  </span>
                  <p className="text-xs text-slate-600 mb-3 line-clamp-2">
                    {meta.description}
                  </p>

                  <p className="text-xs text-slate-500 mb-4">
                    Font:{" "}
                    <strong className="text-slate-700">
                      {theme.defaults?.font}
                    </strong>{" "}
                    • Radius: {theme.defaults?.radius}px
                  </p>

                  {/* Color Swatch Previews */}
                  <div className="flex items-center gap-2 mb-4">
                    <div
                      className="w-7 h-7 rounded-full shadow-inner border border-black/10"
                      style={{ backgroundColor: colors.primary }}
                      title="Primary"
                    />
                    <div
                      className="w-7 h-7 rounded-full shadow-inner border border-black/10"
                      style={{ backgroundColor: colors.secondary }}
                      title="Secondary"
                    />
                    <div
                      className="w-7 h-7 rounded-full shadow-inner border border-black/10"
                      style={{ backgroundColor: colors.surface }}
                      title="Surface"
                    />
                  </div>
                </div>

                <div className="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                  {isUserMarketChoice ? (
                    <span className="inline-flex items-center gap-1 text-[11px] font-bold text-amber-900 bg-amber-100/80 px-2 py-0.5 rounded-full border border-amber-300">
                      <Sparkles className="w-3 h-3 text-amber-700" />
                      <span>Marketplace Choice</span>
                    </span>
                  ) : (
                    <span className="text-slate-400">Live Preset</span>
                  )}
                  <span
                    className={`font-semibold ${
                      isSelected ? "text-amber-900" : "text-slate-400"
                    }`}
                  >
                    {isSelected ? "Active Preset" : "Click to Select"}
                  </span>
                </div>
              </div>
            );
          })}
        </div>
      )}

      <div className="flex justify-end pt-4">
        <button
          onClick={handleSelect}
          disabled={isSaving}
          className="w-full sm:w-auto justify-center px-6 py-2.5 bg-amber-900 hover:bg-amber-950 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2 shadow-md shadow-amber-900/20 disabled:opacity-70"
        >
          {isSaving ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              <span>Applying Theme...</span>
            </>
          ) : (
            <>
              <span>Continue to Branding</span>
              <ArrowRight className="w-4 h-4" />
            </>
          )}
        </button>
      </div>
    </div>
  );
}
