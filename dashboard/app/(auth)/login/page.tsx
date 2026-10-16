"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { apiClient } from "@/lib/api-client";
import { AlertCircle, ArrowRight, Loader2, Lock, Mail } from "lucide-react";

const loginSchema = z.object({
  email: z.string().email("Please enter a valid email address"),
  password: z.string().min(1, "Password is required"),
});

type LoginFormData = z.infer<typeof loginSchema>;

export default function LoginPage() {
  const router = useRouter();
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = async (data: LoginFormData) => {
    setIsSubmitting(true);
    setErrorMessage(null);

    try {
      await apiClient("/api/auth/login", {
        method: "POST",
        body: JSON.stringify(data),
      });

      router.push("/dashboard");
      router.refresh();
    } catch (err: any) {
      if (err.code === "EMAIL_NOT_VERIFIED") {
        setErrorMessage("Please verify your email address before logging in.");
      } else {
        setErrorMessage(err.message || "Invalid email or password.");
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 p-4">
      <div className="max-w-md w-full bg-white rounded-2xl shadow-xl border border-slate-100 p-8">
        <div className="text-center mb-8">
          <Link href="/" className="inline-block mb-4">
            <span className="text-2xl font-black bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">
              WebIntoApp
            </span>
          </Link>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
            Sign in to your account
          </h1>
          <p className="text-sm text-slate-500 mt-2">
            Manage your apps, theme designs, and store connections
          </p>
        </div>

        {errorMessage && (
          <div className="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 flex items-start gap-3 text-sm">
            <AlertCircle className="w-5 h-5 flex-shrink-0 text-rose-500 mt-0.5" />
            <span>{errorMessage}</span>
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">
              Email Address
            </label>
            <div className="relative">
              <Mail className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
              <input
                {...register("email")}
                type="email"
                placeholder="name@store.com"
                className={`w-full pl-11 pr-4 py-2.5 rounded-xl border text-sm transition focus:outline-none focus:ring-2 ${
                  errors.email
                    ? "border-rose-300 focus:ring-rose-200 bg-rose-50/20"
                    : "border-slate-200 focus:border-indigo-600 focus:ring-indigo-100"
                }`}
              />
            </div>
            {errors.email && (
              <p className="text-xs text-rose-600 mt-1.5">{errors.email.message}</p>
            )}
          </div>

          <div>
            <div className="flex justify-between items-center mb-2">
              <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                Password
              </label>
              <Link
                href="/forgot-password"
                className="text-xs font-medium text-indigo-600 hover:text-indigo-700 transition"
              >
                Forgot password?
              </Link>
            </div>
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
              <p className="text-xs text-rose-600 mt-1.5">{errors.password.message}</p>
            )}
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full py-3 px-4 rounded-xl font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-100 transition shadow-lg shadow-indigo-600/25 flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed"
          >
            {isSubmitting ? (
              <>
                <Loader2 className="w-5 h-5 animate-spin" />
                <span>Signing in...</span>
              </>
            ) : (
              <>
                <span>Sign In</span>
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>
        </form>

        <div className="mt-8 text-center text-sm text-slate-500">
          Don't have an account yet?{" "}
          <Link
            href="/signup"
            className="font-semibold text-indigo-600 hover:text-indigo-700 transition"
          >
            Create free account
          </Link>
        </div>
      </div>
    </div>
  );
}
