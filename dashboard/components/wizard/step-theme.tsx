"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, CheckCircle2, Loader2, Palette } from "lucide-react";
import { apiClient } from "@/lib/api-client";

interface StepThemeProps {
  ulid: string;
  onNext: () => void;
}

export function StepTheme({ ulid, onNext }: StepThemeProps) {
  const [selectedKey, setSelectedKey] = useState("theme_a");
  const [isSaving, setIsSaving] = useState(false);

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
              colors: { primary: "#E91E63", secondary: "#222222", background: "#FFFFFF", surface: "#FAFAFA", text: "#111111" },
              radius: 12,
              font: "Poppins",
            },
          },
          {
            key: "theme_b",
            name: "Luxury Boutique",
            defaults: {
              colors: { primary: "#0F172A", secondary: "#C5A880", background: "#F8FAFC", surface: "#FFFFFF", text: "#0F172A" },
              radius: 8,
              font: "Outfit",
            },
          },
          {
            key: "theme_c",
            name: "Vibrant Lifestyle",
            defaults: {
              colors: { primary: "#4F46E5", secondary: "#F59E0B", background: "#FFFFFF", surface: "#F3F4F6", text: "#1F2937" },
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
        <h2 className="text-xl font-bold text-slate-900 tracking-tight">
          Choose a Mobile Theme Preset
        </h2>
        <p className="text-sm text-slate-500 mt-1">
          Select a layout preset tailored for your brand aesthetic. All colors can be refined in branding.
        </p>
      </div>

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

            return (
              <div
                key={theme.key}
                onClick={() => setSelectedKey(theme.key)}
                className={`p-5 rounded-2xl border-2 cursor-pointer transition flex flex-col justify-between ${
                  isSelected
                    ? "border-indigo-600 bg-indigo-50/20 shadow-md ring-1 ring-indigo-600"
                    : "border-slate-200 hover:border-slate-300 bg-white shadow-sm"
                }`}
              >
                <div>
                  <div className="flex items-center justify-between mb-4">
                    <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                      {theme.key}
                    </span>
                    {isSelected && <CheckCircle2 className="w-5 h-5 text-indigo-600" />}
                  </div>

                  <h3 className="font-bold text-slate-900 text-lg mb-1">{theme.name}</h3>
                  <p className="text-xs text-slate-500 mb-4">
                    Font: <strong className="text-slate-700">{theme.defaults?.font}</strong> • Radius: {theme.defaults?.radius}px
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
                  <span className="text-slate-500">Live Preview</span>
                  <span className={`font-semibold ${isSelected ? "text-indigo-600" : "text-slate-400"}`}>
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
          className="w-full sm:w-auto justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2 shadow-md shadow-indigo-600/20 disabled:opacity-70"
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
