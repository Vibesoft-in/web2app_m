"use client";

import { useState } from "react";
import { ArrowRight, Bell, Heart, Loader2, Lock, MessageSquare, ShieldCheck, Star } from "lucide-react";
import { apiClient } from "@/lib/api-client";

interface StepFeaturesProps {
  ulid: string;
  onNext: () => void;
}

export function StepFeatures({ ulid, onNext }: StepFeaturesProps) {
  const [features, setFeatures] = useState({
    wishlist: true,
    otp_login: true,
    google_login: true,
    notifications: true,
    reviews: false,
  });
  const [isSaving, setIsSaving] = useState(false);

  const toggle = (key: keyof typeof features) => {
    setFeatures((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const handleSave = async () => {
    setIsSaving(true);
    try {
      // Save draft with features
      await apiClient(`/api/apps/${ulid}/config/draft`, {
        method: "PUT",
        body: JSON.stringify({
          features,
          theme: { layout: "theme_a", colors: { primary: "#E91E63", secondary: "#222222", background: "#FFFFFF", surface: "#FAFAFA", text: "#111111" } },
        }),
      });
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
          Select App Features
        </h2>
        <p className="text-sm text-slate-500 mt-1">
          Toggle interactive modules to tailor the customer experience.
        </p>
      </div>

      <div className="space-y-3">
        {[
          {
            key: "wishlist" as const,
            title: "Wishlist & Favorites",
            desc: "Allow customers to bookmark items for future purchases.",
            icon: Heart,
          },
          {
            key: "otp_login" as const,
            title: "Phone OTP Fast Login",
            desc: "Frictionless authentication via 6-digit SMS OTP.",
            icon: ShieldCheck,
          },
          {
            key: "google_login" as const,
            title: "Google One-Tap Sign In",
            desc: "Instant customer account creation with verified Google identity.",
            icon: Lock,
          },
          {
            key: "notifications" as const,
            title: "FCM Push Notifications",
            desc: "Order status alerts and automated abandoned cart reminders.",
            icon: Bell,
          },
          {
            key: "reviews" as const,
            title: "Customer Reviews & Star Ratings",
            desc: "Display verified buyer reviews directly on product details.",
            icon: Star,
          },
        ].map((item) => {
          const Icon = item.icon;
          const active = features[item.key];
          return (
            <div
              key={item.key}
              onClick={() => toggle(item.key)}
              className={`p-4 rounded-xl border transition flex items-center justify-between cursor-pointer ${
                active
                  ? "bg-indigo-50/20 border-indigo-200"
                  : "bg-white border-slate-200 hover:border-slate-300"
              }`}
            >
              <div className="flex items-center gap-3 min-w-0 pr-2">
                <div
                  className={`w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 ${
                    active ? "bg-indigo-600 text-white" : "bg-slate-100 text-slate-400"
                  }`}
                >
                  <Icon className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <h4 className="font-bold text-sm text-slate-900">{item.title}</h4>
                  <p className="text-xs text-slate-500 line-clamp-2">{item.desc}</p>
                </div>
              </div>

              <div
                className={`w-11 h-6 rounded-full transition relative p-0.5 flex-shrink-0 ${
                  active ? "bg-indigo-600" : "bg-slate-200"
                }`}
              >
                <div
                  className={`w-5 h-5 rounded-full bg-white transition shadow-sm ${
                    active ? "translate-x-5" : "translate-x-0"
                  }`}
                />
              </div>
            </div>
          );
        })}
      </div>

      <div className="flex justify-end pt-4">
        <button
          onClick={handleSave}
          disabled={isSaving}
          className="w-full sm:w-auto justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold transition flex items-center gap-2 shadow-md shadow-indigo-600/20 disabled:opacity-70"
        >
          {isSaving ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              <span>Saving...</span>
            </>
          ) : (
            <>
              <span>Save & Continue to Themes</span>
              <ArrowRight className="w-4 h-4" />
            </>
          )}
        </button>
      </div>
    </div>
  );
}
