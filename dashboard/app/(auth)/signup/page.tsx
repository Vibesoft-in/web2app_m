"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { apiClient } from "@/lib/api-client";
import { AlertCircle, ArrowRight, Building, CheckCircle2, Loader2, Lock, Mail, User } from "lucide-react";

const signupSchema = z
  .object({
    name: z.string().min(2, "Name must be at least 2 characters").max(100),
    business_name: z.string().min(2, "Business name is required").max(150),
    email: z.string().email("Please enter a valid email address"),
    password: z
      .string()
      .min(10, "Password must be at least 10 characters as per security guidelines"),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords do not match",
    path: ["password_confirmation"],
  });

type SignupFormData = z.infer<typeof signupSchema>;

export default function SignupPage() {
  const router = useRouter();
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<SignupFormData>({
    resolver: zodResolver(signupSchema),
  });

  const onSubmit = async (data: SignupFormData) => {
    setIsSubmitting(true);
    setErrorMessage(null);

    try {
      await apiClient("/api/auth/signup", {
        method: "POST",
        body: JSON.stringify(data),
      });

      router.push(`/verify-email?email=${encodeURIComponent(data.email)}`);
    } catch (err: any) {
      setErrorMessage(
        err.message || "Failed to create account. Please check the form and try again."
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 p-4 py-12">
      <div className="max-w-md w-full bg-white rounded-2xl shadow-xl border border-slate-100 p-8">
        <div className="text-center mb-8">
          <Link href="/" className="inline-block mb-4">
            <span className="text-2xl font-black bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">
              WebIntoApp
            </span>
          </Link>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
            Start your free 14-day trial
          </h1>
          <p className="text-sm text-slate-500 mt-2">
            No credit card required. Build native apps for your store.
          </p>
        </div>

        {errorMessage && (
          <div className="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 flex items-start gap-3 text-sm">
            <AlertCircle className="w-5 h-5 flex-shrink-0 text-rose-500 mt-0.5" />
            <div className="flex-1">
              <span className="font-medium">{errorMessage}</span>
              {errorMessage.toLowerCase().includes("already been taken") && (
                <div className="mt-2.5 pt-2.5 border-t border-rose-200/60 flex items-center justify-between">
                  <span className="text-xs text-rose-600">Aapka account already bana hua hai:</span>
                  <Link
                    href="/login"
                    className="inline-flex items-center gap-1 text-xs font-bold text-indigo-700 hover:text-indigo-800 bg-white px-2.5 py-1 rounded-lg border border-indigo-200 shadow-sm"
                  >
                    <span>Sign In Karein &rarr;</span>
                  </Link>
                </div>
              )}
            </div>
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
              Your Full Name
            </label>
            <div className="relative">
              <User className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
              <input
                {...register("name")}
                type="text"
                placeholder="Jane Doe"
                className={`w-full pl-11 pr-4 py-2.5 rounded-xl border text-sm transition focus:outline-none focus:ring-2 ${
                  errors.name
                    ? "border-rose-300 focus:ring-rose-200 bg-rose-50/20"
                    : "border-slate-200 focus:border-indigo-600 focus:ring-indigo-100"
                }`}
              />
            </div>
            {errors.name && (
              <p className="text-xs text-rose-600 mt-1">{errors.name.message}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
              Store / Business Name
            </label>
            <div className="relative">
              <Building className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
              <input
                {...register("business_name")}
                type="text"
                placeholder="Glow Beauty Naturals"
                className={`w-full pl-11 pr-4 py-2.5 rounded-xl border text-sm transition focus:outline-none focus:ring-2 ${
                  errors.business_name
                    ? "border-rose-300 focus:ring-rose-200 bg-rose-50/20"
                    : "border-slate-200 focus:border-indigo-600 focus:ring-indigo-100"
                }`}
              />
            </div>
            {errors.business_name && (
              <p className="text-xs text-rose-600 mt-1">{errors.business_name.message}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
              Business Email Address
            </label>
            <div className="relative">
              <Mail className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
              <input
                {...register("email")}
                type="email"
                placeholder="jane@glowbeauty.com"
                className={`w-full pl-11 pr-4 py-2.5 rounded-xl border text-sm transition focus:outline-none focus:ring-2 ${
                  errors.email
                    ? "border-rose-300 focus:ring-rose-200 bg-rose-50/20"
                    : "border-slate-200 focus:border-indigo-600 focus:ring-indigo-100"
                }`}
              />
            </div>
            {errors.email && (
              <p className="text-xs text-rose-600 mt-1">{errors.email.message}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
              Password (min. 10 chars)
            </label>
            <div className="relative">
              <Lock className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
              <input
                {...register("password")}
                type="password"
                placeholder="••••••••••••"
                className={`w-full pl-11 pr-4 py-2.5 rounded-xl border text-sm transition focus:outline-none focus:ring-2 ${
                  errors.password
                    ? "border-rose-300 focus:ring-rose-200 bg-rose-50/20"
                    : "border-slate-200 focus:border-indigo-600 focus:ring-indigo-100"
                }`}
              />
            </div>
            {errors.password && (
              <p className="text-xs text-rose-600 mt-1">{errors.password.message}</p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
              Confirm Password
            </label>
            <div className="relative">
              <CheckCircle2 className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
              <input
                {...register("password_confirmation")}
                type="password"
                placeholder="••••••••••••"
                className={`w-full pl-11 pr-4 py-2.5 rounded-xl border text-sm transition focus:outline-none focus:ring-2 ${
                  errors.password_confirmation
                    ? "border-rose-300 focus:ring-rose-200 bg-rose-50/20"
                    : "border-slate-200 focus:border-indigo-600 focus:ring-indigo-100"
                }`}
              />
            </div>
            {errors.password_confirmation && (
              <p className="text-xs text-rose-600 mt-1">{errors.password_confirmation.message}</p>
            )}
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full mt-2 py-3 px-4 rounded-xl font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-100 transition shadow-lg shadow-indigo-600/25 flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed"
          >
            {isSubmitting ? (
              <>
                <Loader2 className="w-5 h-5 animate-spin" />
                <span>Creating your account...</span>
              </>
            ) : (
              <>
                <span>Start Free Trial</span>
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>
        </form>

        <div className="mt-8 text-center text-sm text-slate-500">
          Already have an account?{" "}
          <Link
            href="/login"
            className="font-semibold text-indigo-600 hover:text-indigo-700 transition"
          >
            Sign in
          </Link>
        </div>
      </div>
    </div>
  );
}
