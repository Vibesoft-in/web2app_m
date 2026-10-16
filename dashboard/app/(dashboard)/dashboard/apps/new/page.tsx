"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { apiClient } from "@/lib/api-client";
import { ArrowRight, Loader2, Palette, Sparkles, Store } from "lucide-react";

const newAppSchema = z.object({
  name: z.string().min(2, "App name must be at least 2 characters").max(50),
  platform: z.enum(["woo", "shopify"]),
});

type NewAppForm = z.infer<typeof newAppSchema>;

export default function NewAppPage() {
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [selectedThemeInfo, setSelectedThemeInfo] = useState<{ name: string; price?: string } | null>(null);

  useEffect(() => {
    try {
      const saved = localStorage.getItem("appifyhub_selected_theme");
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed.name) {
          setSelectedThemeInfo({ name: parsed.name, price: parsed.price });
        }
      }
    } catch {}
  }, []);

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors },
  } = useForm<NewAppForm>({
    resolver: zodResolver(newAppSchema),
    defaultValues: {
      platform: "woo",
      name: "",
    },
  });

  const selectedPlatform = watch("platform");

  const onSubmit = async (data: NewAppForm) => {
    setIsSubmitting(true);
    setErrorMsg(null);

    try {
      const res = await apiClient("/api/apps", {
        method: "POST",
        body: JSON.stringify(data),
      });

      const ulid = res.ulid || res.app?.ulid;
      if (ulid) {
        router.push(`/dashboard/apps/${ulid}/setup/platform`);
      } else {
        router.push("/dashboard");
      }
    } catch (err: any) {
      setErrorMsg(err.message || "Failed to initialize application.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="max-w-xl mx-auto py-8">
      <div className="bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
        <div className="mb-8">
          <div className="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-4">
            <Sparkles className="w-6 h-6" />
          </div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
            Create New Mobile Application
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            Let's start by naming your app and choosing your e-commerce platform.
          </p>
        </div>

        {selectedThemeInfo && (
          <div className="mb-6 p-3 rounded-xl bg-amber-50/70 border border-amber-200/90 flex items-center justify-between text-xs">
            <div className="flex items-center gap-2">
              <Palette className="w-4 h-4 text-amber-800 flex-shrink-0" />
              <span className="text-slate-700">
                Active Theme: <strong className="text-amber-950">{selectedThemeInfo.name}</strong>{" "}
                {selectedThemeInfo.price && <span className="text-amber-900">({selectedThemeInfo.price})</span>}
              </span>
            </div>
            <span className="text-[11px] font-bold text-amber-900 bg-amber-100 px-2 py-0.5 rounded-full">
              Ready to apply
            </span>
          </div>
        )}

        {errorMsg && (
          <div className="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
            {errorMsg}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">
              Application Name
            </label>
            <input
              {...register("name")}
              type="text"
              placeholder="e.g. Glow Beauty Store"
              className="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition"
            />
            {errors.name && (
              <p className="text-xs text-rose-600 mt-1.5">{errors.name.message}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-3">
              Store Platform
            </label>
            <div className="grid grid-cols-2 gap-4">
              <button
                type="button"
                onClick={() => setValue("platform", "woo")}
                className={`p-4 rounded-xl border-2 text-left transition flex flex-col justify-between ${
                  selectedPlatform === "woo"
                    ? "border-indigo-600 bg-indigo-50/30"
                    : "border-slate-200 hover:border-slate-300"
                }`}
              >
                <div>
                  <div className="font-bold text-slate-900">WooCommerce</div>
                  <div className="text-xs text-slate-500 mt-1">WordPress REST API</div>
                </div>
                <div className="mt-4 text-xs font-semibold text-indigo-600">Recommended</div>
              </button>

              <button
                type="button"
                onClick={() => setValue("platform", "shopify")}
                className={`p-4 rounded-xl border-2 text-left transition flex flex-col justify-between ${
                  selectedPlatform === "shopify"
                    ? "border-indigo-600 bg-indigo-50/30"
                    : "border-slate-200 hover:border-slate-300"
                }`}
              >
                <div>
                  <div className="font-bold text-slate-900">Shopify</div>
                  <div className="text-xs text-slate-500 mt-1">Storefront & Admin API</div>
                </div>
                <div className="mt-4 text-xs font-semibold text-slate-400">Available</div>
              </button>
            </div>
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full py-3 px-4 rounded-xl font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-lg shadow-indigo-600/20 flex items-center justify-center gap-2 disabled:opacity-70"
          >
            {isSubmitting ? (
              <>
                <Loader2 className="w-5 h-5 animate-spin" />
                <span>Initializing App...</span>
              </>
            ) : (
              <>
                <span>Continue to Wizard</span>
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  );
}
