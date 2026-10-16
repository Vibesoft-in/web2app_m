"use client";

import { Suspense } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { MailCheck, ArrowRight } from "lucide-react";

function VerifyEmailContent() {
  const searchParams = useSearchParams();
  const email = searchParams.get("email") || "your email address";

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 p-4">
      <div className="max-w-md w-full bg-white rounded-2xl shadow-xl border border-slate-100 p-8 text-center">
        <div className="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-sm">
          <MailCheck className="w-8 h-8" />
        </div>
        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
          Verify your email address
        </h1>
        <p className="text-sm text-slate-600 mt-3 leading-relaxed">
          We sent a verification link to <strong className="text-slate-900 font-medium">{email}</strong>. Please check your inbox and click the link to activate your account.
        </p>

        <div className="mt-8 p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-500 text-left">
          <p className="font-semibold text-slate-700 mb-1">Didn't receive an email?</p>
          <p>Check your spam folder or allow a few minutes for delivery.</p>
        </div>

        <div className="mt-8">
          <Link
            href="/login"
            className="w-full py-3 px-4 rounded-xl font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20"
          >
            <span>Proceed to Login</span>
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
      </div>
    </div>
  );
}

export default function VerifyEmailPage() {
  return (
    <Suspense fallback={<div className="min-h-screen flex items-center justify-center bg-slate-50">Loading...</div>}>
      <VerifyEmailContent />
    </Suspense>
  );
}
