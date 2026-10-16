"use client";

import { useEffect, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, CheckCircle2, Eye, Loader2, Palette, Smartphone, Sparkles } from "lucide-react";
import { apiClient } from "@/lib/api-client";
import { PhoneFrame } from "@/components/preview/phone-frame";

interface StepPreviewProps {
  ulid: string;
  onNext: () => void;
}

export function StepPreview({ ulid, onNext }: StepPreviewProps) {
  const [mobileTab, setMobileTab] = useState<"phone" | "controls">("phone");
  const [config, setConfig] = useState<any>({
    schema_version: 1,
    app: { name: "Glow Beauty", currency: "INR" },
    theme: {
      layout: "theme_a",
      colors: {
        primary: "#E91E63",
        secondary: "#222222",
        background: "#FFFFFF",
        surface: "#FAFAFA",
        text: "#111111",
      },
      radius: 12,
      font: "Poppins",
      nav: "bottom",
    },
    features: {
      wishlist: true,
      otp_login: true,
      google_login: true,
      notifications: true,
      reviews: false,
    },
    home: [
      {
        type: "banner",
        items: [{ image: "https://assets.webintoapp.com/banner.jpg", link: "category:all" }],
      },
      { type: "categories" },
      { type: "product_rail", title: "Trending Products", source: "recent" },
    ],
    links: { privacy: "https://webintoapp.com/privacy", terms: "https://webintoapp.com/terms" },
  });

  const [isPublishing, setIsPublishing] = useState(false);
  const [publishSuccess, setPublishSuccess] = useState(false);
  const debounceTimer = useRef<NodeJS.Timeout | null>(null);

  // Fetch real preview products from store connector
  const { data: previewData } = useQuery({
    queryKey: ["preview-data", ulid],
    queryFn: async () => {
      try {
        return await apiClient(`/api/apps/${ulid}/preview-data`);
      } catch {
        return { products: [], categories: [] };
      }
    },
  });

  // Fetch existing draft config on mount
  useEffect(() => {
    let isMounted = true;
    apiClient(`/api/apps/${ulid}/config/draft`)
      .then((res) => {
        if (isMounted && res.config) {
          setConfig((prev: any) => {
            const preservedHome = (Array.isArray(res.config.home) && res.config.home.length > 0)
              ? res.config.home
              : prev.home;

            const finalName = (res.config.app?.name && res.config.app.name !== "Glow Beauty")
              ? res.config.app.name
              : (previewData?.store_name || prev.app?.name || "Mobile Store");

            return {
              ...prev,
              ...res.config,
              home: preservedHome,
              app: {
                ...prev.app,
                ...(res.config.app || {}),
                name: finalName,
              },
            };
          });
        }
      })
      .catch(() => {});
    return () => {
      isMounted = false;
    };
  }, [ulid, previewData?.store_name]);

  useEffect(() => {
    if (previewData?.store_name) {
      setConfig((prev: any) => {
        if (!prev.app?.name || prev.app.name === "Glow Beauty") {
          return {
            ...prev,
            app: { ...prev?.app, name: previewData.store_name },
          };
        }
        return prev;
      });
    }
  }, [previewData?.store_name]);

  // Handle debounced updates to draft config (400ms per docs/01 §5)
  const updateConfigDebounced = (newConfig: any) => {
    setConfig(newConfig);

    if (debounceTimer.current) {
      clearTimeout(debounceTimer.current);
    }

    debounceTimer.current = setTimeout(async () => {
      try {
        await apiClient(`/api/apps/${ulid}/config/draft`, {
          method: "PUT",
          body: JSON.stringify(newConfig),
        });
      } catch (err) {
        console.error("Failed to autosave draft", err);
      }
    }, 400);
  };

  const handleColorChange = (color: string) => {
    const updated = {
      ...config,
      theme: {
        ...config.theme,
        colors: {
          ...config.theme?.colors,
          primary: color,
        },
      },
    };
    updateConfigDebounced(updated);
  };

  const handlePublish = async () => {
    setIsPublishing(true);
    try {
      await apiClient(`/api/apps/${ulid}/config/publish`, {
        method: "POST",
      });
      setPublishSuccess(true);
      setTimeout(() => {
        onNext();
      }, 700);
    } catch (err: any) {
      alert(err.message || "Failed to publish config.");
    } finally {
      setIsPublishing(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-slate-900 tracking-tight">
            Interactive Phone Preview
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            Real products rendered dynamically from your store. Changes autosave with 400ms debounce.
          </p>
        </div>

        <button
          onClick={handlePublish}
          disabled={isPublishing || publishSuccess}
          className="w-full sm:w-auto justify-center px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2 shadow-md shadow-emerald-600/20 disabled:opacity-70"
        >
          {isPublishing ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              <span>Publishing Snapshot...</span>
            </>
          ) : publishSuccess ? (
            <>
              <CheckCircle2 className="w-4 h-4" />
              <span>Published!</span>
            </>
          ) : (
            <>
              <Sparkles className="w-4 h-4" />
              <span>Publish Configuration</span>
            </>
          )}
        </button>
      </div>

      {/* Mobile Tab Switcher (Visible on Phones & Tablets) */}
      <div className="lg:hidden flex items-center p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold w-full">
        <button
          type="button"
          onClick={() => setMobileTab("phone")}
          className={`flex-1 py-2 rounded-lg transition flex items-center justify-center gap-1.5 ${
            mobileTab === "phone"
              ? "bg-white text-indigo-700 shadow-sm font-bold"
              : "text-slate-600 hover:text-slate-900"
          }`}
        >
          <Smartphone className="w-3.5 h-3.5" />
          <span>Interactive Phone</span>
        </button>
        <button
          type="button"
          onClick={() => setMobileTab("controls")}
          className={`flex-1 py-2 rounded-lg transition flex items-center justify-center gap-1.5 ${
            mobileTab === "controls"
              ? "bg-white text-indigo-700 shadow-sm font-bold"
              : "text-slate-600 hover:text-slate-900"
          }`}
        >
          <Palette className="w-3.5 h-3.5" />
          <span>Theme Controls</span>
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {/* Real-time Live Controls (Left on desktop, Tab on mobile) */}
        <div
          className={`lg:col-span-6 bg-white border border-slate-200 rounded-2xl p-4 sm:p-6 shadow-sm space-y-6 ${
            mobileTab === "controls" ? "block" : "hidden lg:block"
          }`}
        >
          <div className="flex items-center gap-2 text-sm font-bold text-slate-900 pb-3 border-b border-slate-100">
            <Eye className="w-4 h-4 text-indigo-600" />
            <span>Live Theme Quick Controls</span>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">
              Primary Brand Accent
            </label>
            <div className="flex items-center gap-3">
              <input
                type="color"
                value={config.theme?.colors?.primary || "#E91E63"}
                onChange={(e) => handleColorChange(e.target.value)}
                className="w-10 h-10 rounded-xl cursor-pointer border border-slate-200 p-0.5"
              />
              <span className="font-mono text-sm uppercase text-slate-700">
                {config.theme?.colors?.primary || "#E91E63"}
              </span>
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">
              App Display Name
            </label>
            <input
              type="text"
              value={config.app?.name || ""}
              onChange={(e) =>
                updateConfigDebounced({
                  ...config,
                  app: { ...config.app, name: e.target.value },
                })
              }
              className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-indigo-600 focus:outline-none"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">
              Corner Radius: {config.theme?.radius || 12}px
            </label>
            <input
              type="range"
              min="0"
              max="24"
              value={config.theme?.radius || 12}
              onChange={(e) =>
                updateConfigDebounced({
                  ...config,
                  theme: { ...config.theme, radius: parseInt(e.target.value, 10) },
                })
              }
              className="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-600"
            />
          </div>

          <div className="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
            <p className="font-semibold text-slate-800 mb-1">Live Store Data Connected:</p>
            <p>
              Displaying {previewData?.products?.length || 0} real catalog products synced directly from WooCommerce via REST v3 connector.
            </p>
          </div>
        </div>

        {/* Interactive Mobile Phone Frame (Right on desktop, Tab on mobile) */}
        <div
          className={`lg:col-span-6 flex flex-col items-center justify-center py-1 sm:py-2 w-full ${
            mobileTab === "phone" ? "flex" : "hidden lg:flex"
          }`}
        >
          {/* Subtle phone preview hint */}
          <div className="mb-2 text-center hidden sm:block">
            <span className="text-[11px] font-semibold text-slate-400">
              Interactive Live Phone Mirror (Scroll &amp; Click)
            </span>
          </div>

          <PhoneFrame
            config={config}
            products={previewData?.products}
            categories={previewData?.categories}
            logoUrl={config?.app?.logo_url || config?.theme?.logo_url || previewData?.logo_url || previewData?.website_logo}
            bannerUrl={previewData?.banner_image}
          />
        </div>
      </div>
    </div>
  );
}
