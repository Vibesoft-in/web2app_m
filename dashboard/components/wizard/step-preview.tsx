"use client";

import { useEffect, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Eye,
  Image as ImageIcon,
  Loader2,
  Palette,
  RotateCcw,
  Smartphone,
  Sparkles,
  Type,
  Upload,
  Link as LinkIcon,
  Check,
  AlertCircle,
} from "lucide-react";
import { apiClient } from "@/lib/api-client";
import { PhoneFrame } from "@/components/preview/phone-frame";

interface StepPreviewProps {
  ulid: string;
  onNext: () => void;
}

const DEFAULT_THEME_COLORS: Record<string, string> = {
  primary: "#046BD2",
  secondary: "#045CB4",
  accent: "#1E293B",
  background: "#FFFFFF",
  surface: "#F0F5FA",
  text: "#1F2937",
};

const COLOR_FIELDS = [
  { label: "Primary Brand Accent", key: "primary", default: "#046BD2", desc: "Buttons, tabs, active highlights & prices" },
  { label: "Secondary Brand Color", key: "secondary", default: "#045CB4", desc: "Hero banners, gradient overlays & badges" },
  { label: "Accent / Headings", key: "accent", default: "#1E293B", desc: "Product titles, bold headers & section names" },
  { label: "Card & Surface Background", key: "surface", default: "#F0F5FA", desc: "Product cards, headers & drawer backgrounds" },
  { label: "App Canvas Background", key: "background", default: "#FFFFFF", desc: "Main screen background color" },
  { label: "Body Text Color", key: "text", default: "#1F2937", desc: "Standard body text and descriptions" },
];

const BANNER_PRESETS = [
  {
    id: "fashion",
    name: "Summer Fashion",
    badge: "New Arrivals",
    title: "Summer Collection 2026",
    button_text: "Shop Collection →",
    image: "https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: "luxury",
    name: "Luxury Watches",
    badge: "Limited Edition",
    title: "Timeless Luxury Essentials",
    button_text: "Discover Now →",
    image: "https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: "streetwear",
    name: "Streetwear Drops",
    badge: "Trending Drop",
    title: "Urban Sneakers & Apparel",
    button_text: "Get First Access →",
    image: "https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: "sale",
    name: "Mega Sale 50%",
    badge: "Flash Deal 50% Off",
    title: "Biggest Clearance Sale",
    button_text: "Claim Discounts →",
    image: "https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: "tech",
    name: "Smart Audio & Gadgets",
    badge: "Featured Tech",
    title: "Next-Gen Smart Devices",
    button_text: "Shop Electronics →",
    image: "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&q=80",
  },
  {
    id: "home",
    name: "Nordic Living",
    badge: "Cozy Space",
    title: "Modern Home Interiors",
    button_text: "Explore Home →",
    image: "https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=800&q=80",
  },
];

export function StepPreview({ ulid, onNext }: StepPreviewProps) {
  const [mobileTab, setMobileTab] = useState<"phone" | "controls">("phone");
  const [config, setConfig] = useState<any>({
    schema_version: 1,
    app: { name: "shop", currency: "INR" },
    theme: {
      layout: "theme_a",
      colors: {
        primary: "#046BD2",
        secondary: "#045CB4",
        accent: "#1E293B",
        background: "#FFFFFF",
        surface: "#F0F5FA",
        text: "#1F2937",
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
        items: [
          {
            image: "https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=800&q=80",
            link: "category:all",
            title: "Exclusive Online Collection",
            badge: "Featured Store Deal",
            button_text: "Shop Now →",
          },
        ],
      },
      { type: "categories" },
      { type: "product_rail", title: "Trending Products", source: "recent" },
    ],
    links: { privacy: "https://webintoapp.com/privacy", terms: "https://webintoapp.com/terms" },
  });

  const [isPublishing, setIsPublishing] = useState(false);
  const [publishSuccess, setPublishSuccess] = useState(false);
  const debounceTimer = useRef<NodeJS.Timeout | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const hasLoadedDraft = useRef(false);

  // Hero Banner editing state
  const bannerSectionRef = useRef<HTMLDivElement | null>(null);
  const bannerFileInputRef = useRef<HTMLInputElement | null>(null);
  const [isUploadingBanner, setIsUploadingBanner] = useState(false);
  const [bannerUploadError, setBannerUploadError] = useState<string | null>(null);
  const [bannerHighlight, setBannerHighlight] = useState(false);
  const [bannerUrlInput, setBannerUrlInput] = useState("");
  const [showAdvancedBannerText, setShowAdvancedBannerText] = useState(false);
  const [originalWebsiteColors, setOriginalWebsiteColors] = useState<Record<string, string> | null>(null);

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

  // Fetch existing draft config on mount (ONCE ONLY - prevents overwriting live changes)
  useEffect(() => {
    // Read original extracted website colors if available
    try {
      const rawExtracted = localStorage.getItem(`app_draft_${ulid}_extracted_colors`);
      if (rawExtracted) {
        setOriginalWebsiteColors(JSON.parse(rawExtracted));
      }
    } catch {}

    if (hasLoadedDraft.current) return;
    let isMounted = true;
    apiClient(`/api/apps/${ulid}/config/draft`)
      .then((res) => {
        if (isMounted && res.config && !hasLoadedDraft.current) {
          hasLoadedDraft.current = true;

          let savedLocalColors: Record<string, string> = {};
          try {
            const raw = localStorage.getItem(`app_draft_${ulid}_colors`);
            if (raw) savedLocalColors = JSON.parse(raw);
          } catch {}

          setConfig((prev: any) => {
            const preservedHome =
              Array.isArray(res.config.home) && res.config.home.length > 0
                ? res.config.home
                : prev.home;

            const finalName =
              res.config.app?.name ||
              previewData?.store_name ||
              prev.app?.name ||
              "My Mobile Store";

            return {
              ...prev,
              ...res.config,
              home: preservedHome,
              theme: {
                ...prev.theme,
                ...(res.config.theme || {}),
                colors: {
                  ...DEFAULT_THEME_COLORS,
                  ...(savedLocalColors || {}),
                  ...(prev.theme?.colors || {}),
                  ...(res.config.theme?.colors || {}),
                },
              },
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

  // Handle debounced updates to draft config (immediate state update for 0ms lag + 400ms debounce save)
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

  // Color changes
  const handleSpecificColorChange = (key: string, value: string) => {
    setConfig((prev: any) => {
      const updatedColors = {
        ...prev.theme?.colors,
        [key]: value,
      };
      const updated = {
        ...prev,
        theme: {
          ...prev.theme,
          colors: updatedColors,
        },
      };

      try {
        localStorage.setItem(`app_draft_${ulid}_colors`, JSON.stringify(updatedColors));
      } catch {}

      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }

      debounceTimer.current = setTimeout(async () => {
        try {
          await apiClient(`/api/apps/${ulid}/config/draft`, {
            method: "PUT",
            body: JSON.stringify(updated),
          });
        } catch (err) {
          console.error("Failed to autosave draft", err);
        }
      }, 400);

      return updated;
    });
  };

  const handleColorReset = (key: string) => {
    const fallback =
      originalWebsiteColors?.[key] || DEFAULT_THEME_COLORS[key] || "#046BD2";
    handleSpecificColorChange(key, fallback);
  };

  const handleResetToWebsiteColors = () => {
    const websiteColors = {
      ...DEFAULT_THEME_COLORS,
      ...(originalWebsiteColors || {}),
    };

    setConfig((prev: any) => {
      const updated = {
        ...prev,
        theme: {
          ...prev.theme,
          colors: websiteColors,
        },
      };

      try {
        localStorage.setItem(`app_draft_${ulid}_colors`, JSON.stringify(websiteColors));
      } catch {}

      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }

      debounceTimer.current = setTimeout(async () => {
        try {
          await apiClient(`/api/apps/${ulid}/config/draft`, {
            method: "PUT",
            body: JSON.stringify(updated),
          });
        } catch (err) {
          console.error("Failed to autosave draft", err);
        }
      }, 400);

      return updated;
    });
  };

  const handleFontChange = (fontName: string) => {
    setConfig((prev: any) => {
      const updated = {
        ...prev,
        theme: {
          ...prev.theme,
          font: fontName,
        },
      };

      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }
      debounceTimer.current = setTimeout(async () => {
        try {
          await apiClient(`/api/apps/${ulid}/config/draft`, {
            method: "PUT",
            body: JSON.stringify(updated),
          });
        } catch (err) {
          console.error("Failed to autosave draft", err);
        }
      }, 400);

      return updated;
    });
  };

  const handleIconUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const url = URL.createObjectURL(file);
      const updated = {
        ...config,
        app: {
          ...config.app,
          logo_url: url,
        },
        theme: {
          ...config.theme,
          logo_url: url,
        },
      };
      updateConfigDebounced(updated);
    }
  };

  // ─── HERO BANNER EDITING LOGIC ───
  const homeBannerSection = Array.isArray(config.home)
    ? config.home.find((s: any) => s.type === "banner")
    : null;
  const bannerItem = homeBannerSection?.items?.[0] || {};
  const activeBannerImage =
    bannerItem.image ||
    config.app?.banner_url ||
    config.theme?.banner_url ||
    previewData?.banner_image ||
    "https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=800&q=80";
  const activeBannerTitle = bannerItem.title || "Exclusive Online Collection";
  const activeBannerBadge = bannerItem.badge || "Featured Store Deal";
  const activeBannerButton = bannerItem.button_text || "Shop Now →";

  const updateBanner = (updates: {
    image?: string;
    title?: string;
    badge?: string;
    button_text?: string;
  }) => {
    setConfig((prev: any) => {
      const currentHome = Array.isArray(prev.home) ? [...prev.home] : [];
      const bannerIndex = currentHome.findIndex((s: any) => s.type === "banner");

      const existingItem =
        bannerIndex >= 0 ? currentHome[bannerIndex].items?.[0] || {} : {};
      const newItem = {
        ...existingItem,
        link: existingItem.link || "category:all",
        image: updates.image !== undefined ? updates.image : (existingItem.image || activeBannerImage),
        title: updates.title !== undefined ? updates.title : (existingItem.title || activeBannerTitle),
        badge: updates.badge !== undefined ? updates.badge : (existingItem.badge || activeBannerBadge),
        button_text: updates.button_text !== undefined ? updates.button_text : (existingItem.button_text || activeBannerButton),
      };

      if (bannerIndex >= 0) {
        currentHome[bannerIndex] = {
          ...currentHome[bannerIndex],
          items: [newItem],
        };
      } else {
        currentHome.unshift({
          type: "banner",
          items: [newItem],
        });
      }

      const updated = {
        ...prev,
        home: currentHome,
        app: {
          ...prev.app,
          banner_url: newItem.image,
        },
      };

      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }
      debounceTimer.current = setTimeout(async () => {
        try {
          await apiClient(`/api/apps/${ulid}/config/draft`, {
            method: "PUT",
            body: JSON.stringify(updated),
          });
        } catch (err) {
          console.error("Failed to autosave draft banner", err);
        }
      }, 400);

      return updated;
    });
  };

  const handleBannerFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setBannerUploadError(null);
    setIsUploadingBanner(true);

    // Immediate 0ms local preview
    const tempUrl = URL.createObjectURL(file);
    updateBanner({ image: tempUrl });

    try {
      const formData = new FormData();
      formData.append("file", file);
      formData.append("type", "banner");

      const token = typeof window !== "undefined" ? localStorage.getItem("token") || "" : "";
      const res = await fetch(`http://localhost:8000/api/apps/${ulid}/upload-asset`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: formData,
      });

      if (!res.ok) {
        const errorData = await res.json().catch(() => ({}));
        throw new Error(errorData.message || "Failed to upload banner.");
      }

      const data = await res.json();
      if (data.url) {
        updateBanner({ image: data.url });
      }
    } catch (err: any) {
      console.error("Banner upload error:", err);
      setBannerUploadError(err.message || "Upload failed. Image is active in local preview.");
    } finally {
      setIsUploadingBanner(false);
      if (bannerFileInputRef.current) {
        bannerFileInputRef.current.value = "";
      }
    }
  };

  const handleEditBannerFromPhone = () => {
    if (mobileTab !== "controls") {
      setMobileTab("controls");
    }
    setTimeout(() => {
      if (bannerSectionRef.current) {
        bannerSectionRef.current.scrollIntoView({ behavior: "smooth", block: "center" });
        setBannerHighlight(true);
        setTimeout(() => setBannerHighlight(false), 2000);
      }
    }, 60);
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

  const currentColors = {
    ...DEFAULT_THEME_COLORS,
    ...(config.theme?.colors || {}),
  };

  return (
    <div className="space-y-6">
      {/* Top Section Header */}
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
          className="w-full sm:w-auto justify-center px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2 shadow-md shadow-emerald-600/20 disabled:opacity-70 cursor-pointer"
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
          className={`flex-1 py-2 rounded-lg transition flex items-center justify-center gap-1.5 cursor-pointer ${
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
          className={`flex-1 py-2 rounded-lg transition flex items-center justify-center gap-1.5 cursor-pointer ${
            mobileTab === "controls"
              ? "bg-white text-indigo-700 shadow-sm font-bold"
              : "text-slate-600 hover:text-slate-900"
          }`}
        >
          <Palette className="w-3.5 h-3.5" />
          <span>Theme Controls</span>
        </button>
      </div>

      {/* Two-Column Layout: Left Controls Scroll With Page + Right Emulator Stays Fixed */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        {/* Real-time Live Controls (Left column: scrolls naturally with page) */}
        <div
          className={`lg:col-span-7 bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 shadow-sm space-y-6 ${
            mobileTab === "controls" ? "block" : "hidden lg:block"
          }`}
        >
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <div className="flex items-center gap-2 text-sm font-bold text-slate-900">
              <Eye className="w-4 h-4 text-amber-700" />
              <span>Live Theme Quick Controls</span>
            </div>
            <div className="flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
              <Sparkles className="w-3 h-3 text-emerald-600" />
              <span>Synced with Store</span>
            </div>
          </div>

          {/* ═════════════════════════════════════════════════════════════════════════
              1. HERO BANNER IMAGE & PROMOTION CONTROLS
             ═════════════════════════════════════════════════════════════════════════ */}
          <div
            ref={bannerSectionRef}
            className={`rounded-2xl p-4 sm:p-5 border transition-all duration-300 ${
              bannerHighlight
                ? "bg-indigo-50/70 border-indigo-400 ring-4 ring-indigo-200 shadow-lg"
                : "bg-slate-50/70 border-slate-200 hover:border-slate-300"
            }`}
          >
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                  <ImageIcon className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">
                    HERO BANNER IMAGE
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    Primary promotional banner on your app home screen
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={() =>
                  updateBanner({
                    image:
                      previewData?.banner_image ||
                      "https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=800&q=80",
                    title: "Exclusive Online Collection",
                    badge: "Featured Store Deal",
                    button_text: "Shop Now →",
                  })
                }
                className="text-[11px] font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1 hover:bg-white px-2 py-1 rounded-lg border border-transparent hover:border-slate-200 transition cursor-pointer"
                title="Reset banner to default"
              >
                <RotateCcw className="w-3 h-3" />
                <span>Reset</span>
              </button>
            </div>

            {/* Banner Live Thumbnail Card */}
            <div className="relative w-full h-36 sm:h-40 rounded-xl overflow-hidden border border-slate-200 shadow-sm group bg-slate-900 mb-3.5">
              <img
                src={activeBannerImage}
                alt="Banner Preview"
                className="w-full h-full object-cover transition duration-300 group-hover:scale-102"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent flex flex-col justify-end p-3.5 text-white">
                <span
                  className="text-[9.5px] uppercase font-bold tracking-wider px-2 py-0.5 rounded backdrop-blur-md shadow-xs self-start mb-1"
                  style={{
                    backgroundColor: currentColors.secondary || "#045CB4",
                    color: "#FFFFFF",
                  }}
                >
                  {activeBannerBadge}
                </span>
                <h4 className="font-extrabold text-sm text-white drop-shadow truncate">
                  {activeBannerTitle}
                </h4>
                <span className="text-[10px] text-white/90 underline font-semibold mt-0.5">
                  {activeBannerButton}
                </span>
              </div>

              {isUploadingBanner && (
                <div className="absolute inset-0 bg-slate-900/80 backdrop-blur-xs flex flex-col items-center justify-center text-white gap-2">
                  <Loader2 className="w-6 h-6 animate-spin text-indigo-400" />
                  <span className="text-xs font-semibold">Uploading Banner Image...</span>
                </div>
              )}

              <div className="absolute top-2.5 right-2.5 px-2 py-0.5 bg-black/60 backdrop-blur-md text-white rounded-md text-[10px] font-semibold border border-white/20">
                Live on Emulator
              </div>
            </div>

            {bannerUploadError && (
              <div className="mb-3 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2">
                <AlertCircle className="w-4 h-4 flex-shrink-0 text-rose-500" />
                <span>{bannerUploadError}</span>
              </div>
            )}

            {/* Upload Button & URL Input */}
            <input
              ref={bannerFileInputRef}
              type="file"
              accept="image/png,image/jpeg,image/webp,image/jpg"
              onChange={handleBannerFileUpload}
              className="hidden"
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mb-3.5">
              <button
                type="button"
                onClick={() => bannerFileInputRef.current?.click()}
                disabled={isUploadingBanner}
                className="w-full py-2.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-xs cursor-pointer disabled:opacity-60"
              >
                <Upload className="w-3.5 h-3.5" />
                <span>Upload Custom Image</span>
              </button>

              <div className="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-2.5 py-1 shadow-2xs">
                <LinkIcon className="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
                <input
                  type="url"
                  placeholder="Paste Image URL..."
                  value={bannerUrlInput}
                  onChange={(e) => {
                    setBannerUrlInput(e.target.value);
                    if (e.target.value.startsWith("http")) {
                      updateBanner({ image: e.target.value });
                    }
                  }}
                  className="w-full text-xs text-slate-800 bg-transparent outline-none placeholder:text-slate-400"
                />
                {bannerUrlInput && (
                  <button
                    type="button"
                    onClick={() => {
                      if (bannerUrlInput.startsWith("http")) {
                        updateBanner({ image: bannerUrlInput });
                      }
                    }}
                    className="px-2 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-[10px] font-bold hover:bg-indigo-100 cursor-pointer"
                  >
                    Apply
                  </button>
                )}
              </div>
            </div>

            {/* Curated 1-Click Banner Presets */}
            <div className="space-y-2 pt-2 border-t border-slate-200/60">
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold text-slate-700 uppercase tracking-wider">
                  Curated E-Commerce Presets (1-Click)
                </span>
                <span className="text-[10px] text-slate-400">High-res banners</span>
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                {BANNER_PRESETS.map((preset) => (
                  <button
                    key={preset.id}
                    type="button"
                    onClick={() =>
                      updateBanner({
                        image: preset.image,
                        title: preset.title,
                        badge: preset.badge,
                        button_text: preset.button_text,
                      })
                    }
                    className={`group relative overflow-hidden rounded-xl border p-1 text-left transition-all cursor-pointer ${
                      activeBannerImage === preset.image
                        ? "border-indigo-600 ring-2 ring-indigo-500 bg-indigo-50/50 shadow-xs"
                        : "border-slate-200 bg-white hover:border-slate-300 hover:shadow-xs"
                    }`}
                  >
                    <div className="w-full h-12 rounded-lg overflow-hidden bg-slate-100 mb-1 relative">
                      <img
                        src={preset.image}
                        alt={preset.name}
                        className="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                      />
                      {activeBannerImage === preset.image && (
                        <div className="absolute top-1 right-1 w-4 h-4 bg-indigo-600 rounded-full flex items-center justify-center text-white shadow-xs">
                          <Check className="w-2.5 h-2.5 stroke-[3]" />
                        </div>
                      )}
                    </div>
                    <span className="block text-[10.5px] font-bold text-slate-800 truncate px-0.5">
                      {preset.name}
                    </span>
                    <span className="block text-[9px] text-slate-500 truncate px-0.5">
                      {preset.badge}
                    </span>
                  </button>
                ))}
              </div>
            </div>

            {/* Banner Promotional Text Customization (Collapsible) */}
            <div className="pt-2.5 mt-2 border-t border-slate-200/60">
              <button
                type="button"
                onClick={() => setShowAdvancedBannerText(!showAdvancedBannerText)}
                className="w-full flex items-center justify-between text-[11px] font-bold text-slate-700 hover:text-slate-900 py-1 cursor-pointer"
              >
                <span>Customize Banner Headline &amp; Promotion Badge</span>
                {showAdvancedBannerText ? (
                  <ChevronUp className="w-3.5 h-3.5 text-slate-500" />
                ) : (
                  <ChevronDown className="w-3.5 h-3.5 text-slate-500" />
                )}
              </button>

              {showAdvancedBannerText && (
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2.5">
                  <div>
                    <label className="block text-[10px] font-bold text-slate-600 uppercase mb-1">
                      Promotional Badge
                    </label>
                    <input
                      type="text"
                      value={activeBannerBadge}
                      onChange={(e) => updateBanner({ badge: e.target.value })}
                      placeholder="e.g. 50% OFF"
                      className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 bg-white text-slate-800 focus:border-indigo-600 focus:outline-none"
                    />
                  </div>

                  <div>
                    <label className="block text-[10px] font-bold text-slate-600 uppercase mb-1">
                      Banner Headline
                    </label>
                    <input
                      type="text"
                      value={activeBannerTitle}
                      onChange={(e) => updateBanner({ title: e.target.value })}
                      placeholder="e.g. Summer Collection"
                      className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 bg-white text-slate-800 focus:border-indigo-600 focus:outline-none"
                    />
                  </div>

                  <div>
                    <label className="block text-[10px] font-bold text-slate-600 uppercase mb-1">
                      Button Label
                    </label>
                    <input
                      type="text"
                      value={activeBannerButton}
                      onChange={(e) => updateBanner({ button_text: e.target.value })}
                      placeholder="e.g. Shop Now →"
                      className="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 bg-white text-slate-800 focus:border-indigo-600 focus:outline-none"
                    />
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* ═════════════════════════════════════════════════════════════════════════
              2. THEME COLORS (EXTRACTED FROM WEBSITE & FULLY CUSTOMIZABLE)
             ═════════════════════════════════════════════════════════════════════════ */}
          <div className="space-y-3.5 pt-2 border-t border-slate-100">
            <div className="flex items-center justify-between">
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                  THEME COLOR PALETTE
                </label>
                <p className="text-[11px] text-slate-500">
                  Extracted from your website. Click any color or hex to change live.
                </p>
              </div>

              <button
                type="button"
                onClick={handleResetToWebsiteColors}
                className="text-[11px] font-semibold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg border border-indigo-200 transition flex items-center gap-1 cursor-pointer"
                title="Reset all colors back to website branding"
              >
                <RotateCcw className="w-3 h-3" />
                <span>Reset to Website Colors</span>
              </button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              {COLOR_FIELDS.map((item) => (
                <div
                  key={item.key}
                  className="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-white hover:shadow-xs transition"
                >
                  <div className="flex items-center gap-2.5 min-w-0">
                    <input
                      type="color"
                      value={currentColors[item.key] || item.default}
                      onChange={(e) => handleSpecificColorChange(item.key, e.target.value)}
                      className="w-8 h-8 rounded-lg cursor-pointer border border-slate-200 p-0.5 flex-shrink-0"
                    />
                    <div className="min-w-0">
                      <span className="text-xs font-bold text-slate-800 block truncate">
                        {item.label}
                      </span>
                      <span className="text-[10px] text-slate-400 block truncate">
                        {item.desc}
                      </span>
                    </div>
                  </div>

                  <div className="flex items-center gap-1.5 flex-shrink-0 ml-2">
                    <input
                      type="text"
                      value={currentColors[item.key] || item.default}
                      onChange={(e) => handleSpecificColorChange(item.key, e.target.value)}
                      className="w-20 px-2 py-1 font-mono text-[11px] font-bold text-slate-700 uppercase bg-white border border-slate-200 rounded-lg text-center focus:border-indigo-600 focus:outline-none"
                    />
                    <button
                      type="button"
                      title="Reset this color"
                      onClick={() => handleColorReset(item.key)}
                      className="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                    >
                      <RotateCcw className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* ═════════════════════════════════════════════════════════════════════════
              3. APP DISPLAY NAME
             ═════════════════════════════════════════════════════════════════════════ */}
          <div className="pt-2 border-t border-slate-100">
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
              APP DISPLAY NAME
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
              className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-indigo-600 focus:outline-none text-slate-900 bg-white"
            />
          </div>

          {/* ═════════════════════════════════════════════════════════════════════════
              4. CORNER RADIUS
             ═════════════════════════════════════════════════════════════════════════ */}
          <div className="pt-2 border-t border-slate-100">
            <div className="flex items-center justify-between mb-2">
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                CORNER RADIUS
              </label>
              <span className="px-3 py-1 bg-slate-100 rounded-lg text-xs font-bold text-slate-700 border border-slate-200">
                {config.theme?.radius !== undefined ? config.theme.radius : 12}px
              </span>
            </div>
            <div className="space-y-1.5">
              <input
                type="range"
                min="0"
                max="24"
                value={config.theme?.radius !== undefined ? config.theme.radius : 12}
                onChange={(e) =>
                  updateConfigDebounced({
                    ...config,
                    theme: { ...config.theme, radius: parseInt(e.target.value, 10) },
                  })
                }
                className="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-600"
              />
              <div className="flex items-center justify-between text-[11px] font-semibold text-slate-400">
                <span>0px (Sharp)</span>
                <span>12px (Smooth)</span>
                <span>24px (Rounded)</span>
              </div>
            </div>
          </div>

          {/* ═════════════════════════════════════════════════════════════════════════
              5. APP ICON & SPLASH
             ═════════════════════════════════════════════════════════════════════════ */}
          <div className="space-y-3 pt-2 border-t border-slate-100">
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              APP ICON &amp; LOGO
            </label>

            <input
              ref={fileInputRef}
              type="file"
              accept="image/png,image/jpeg,image/webp"
              onChange={handleIconUpload}
              className="hidden"
            />

            <div className="flex items-center justify-between p-3.5 rounded-xl border border-slate-200 bg-slate-50/50">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 shadow-xs flex-shrink-0 overflow-hidden">
                  {config?.app?.logo_url || config?.theme?.logo_url ? (
                    <img
                      src={config.app?.logo_url || config.theme?.logo_url}
                      alt="Logo"
                      className="w-full h-full object-contain p-1"
                    />
                  ) : (
                    <Upload className="w-5 h-5 text-slate-500" />
                  )}
                </div>
                <div>
                  <h4 className="font-bold text-slate-900 text-xs">Custom App Icon / Logo</h4>
                  <span className="text-[11px] text-slate-400 font-medium">PNG or JPEG, 512×512</span>
                </div>
              </div>

              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                className="px-4 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-bold text-xs text-slate-700 shadow-xs transition cursor-pointer"
              >
                Upload Icon
              </button>
            </div>
          </div>

          {/* ═════════════════════════════════════════════════════════════════════════
              6. APP TYPOGRAPHY
             ═════════════════════════════════════════════════════════════════════════ */}
          <div className="space-y-3 pt-2 border-t border-slate-100">
            <div className="flex items-center justify-between">
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                APP TYPOGRAPHY
              </label>
              <span className="text-[11px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100">
                {config.theme?.font || "Poppins"}
              </span>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              {[
                { name: "Poppins", label: "Poppins", font: "'Poppins', sans-serif" },
                { name: "Plus Jakarta Sans", label: "Plus Jakarta", font: "'Plus Jakarta Sans', sans-serif" },
                { name: "Inter", label: "Inter", font: "'Inter', sans-serif" },
                { name: "Roboto", label: "Roboto", font: "'Roboto', sans-serif" },
              ].map((f) => (
                <button
                  key={f.name}
                  type="button"
                  onClick={() => handleFontChange(f.name)}
                  style={{ fontFamily: f.font }}
                  className={`p-2.5 rounded-xl border text-xs font-semibold transition text-center cursor-pointer active:scale-95 ${
                    (config.theme?.font || "Poppins") === f.name
                      ? "border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-xs ring-1 ring-indigo-500 font-bold"
                      : "border-slate-200 bg-white hover:bg-slate-50 text-slate-700"
                  }`}
                >
                  {f.label}
                </button>
              ))}
            </div>
          </div>

          {/* ═════════════════════════════════════════════════════════════════════════
              7. IN-APP CAPABILITIES
             ═════════════════════════════════════════════════════════════════════════ */}
          <div className="space-y-3 pt-2 border-t border-slate-100">
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              IN-APP CAPABILITIES
            </label>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              {[
                { label: "Product Wishlist", key: "wishlist" },
                { label: "Push Notifications", key: "notifications" },
                { label: "Customer Reviews", key: "reviews" },
                { label: "Fast Guest Checkout", key: "otp_login" },
              ].map((feat) => {
                const isEnabled = config.features?.[feat.key] ?? true;
                return (
                  <button
                    key={feat.key}
                    type="button"
                    onClick={() =>
                      updateConfigDebounced({
                        ...config,
                        features: {
                          ...config.features,
                          [feat.key]: !isEnabled,
                        },
                      })
                    }
                    className={`flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition cursor-pointer ${
                      isEnabled
                        ? "border-emerald-300 bg-emerald-50/50 text-emerald-800"
                        : "border-slate-200 bg-slate-50/60 text-slate-500"
                    }`}
                  >
                    <span>{feat.label}</span>
                    <span
                      className={`text-[10px] font-bold px-2 py-0.5 rounded-md ${
                        isEnabled
                          ? "bg-emerald-600 text-white"
                          : "bg-slate-200 text-slate-600"
                      }`}
                    >
                      {isEnabled ? "ON" : "OFF"}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Live Store Connection Info */}
          <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
            <p className="font-semibold text-slate-800 mb-0.5">Live Store Data Connected:</p>
            <p className="text-[11px] leading-relaxed">
              Displaying {previewData?.products?.length || 0} real catalog products synced directly from WooCommerce via REST v3 connector.
            </p>
          </div>
        </div>

        {/* ═════════════════════════════════════════════════════════════════════════
            INTERACTIVE MOBILE PHONE FRAME WRAPPER (Right column: sticky at top: 24px)
           ═════════════════════════════════════════════════════════════════════════ */}
        <div
          className={`lg:col-span-5 flex flex-col items-center justify-center lg:sticky lg:top-6 z-20 self-start w-full ${
            mobileTab === "phone" ? "flex" : "hidden lg:flex"
          }`}
          style={{ top: "24px" }}
        >
          <PhoneFrame
            config={config}
            products={previewData?.products}
            categories={previewData?.categories}
            logoUrl={config?.app?.logo_url || config?.theme?.logo_url || previewData?.website_logo}
            bannerUrl={activeBannerImage}
            onEditBanner={handleEditBannerFromPhone}
          />
        </div>
      </div>
    </div>
  );
}
