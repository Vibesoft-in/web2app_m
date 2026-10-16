import Link from "next/link";

export default function HomePage() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-between p-8 md:p-24">
      <div className="z-10 max-w-5xl w-full items-center justify-between font-mono text-sm flex">
        <p className="fixed left-0 top-0 flex w-full justify-center border-b border-gray-300 bg-gradient-to-b from-zinc-200 pb-6 pt-8 backdrop-blur-2xl dark:border-neutral-800 dark:bg-zinc-800/30 dark:from-inherit lg:static lg:w-auto lg:rounded-xl lg:border lg:bg-gray-200 lg:p-4 lg:dark:bg-zinc-800/30 font-semibold">
          WebIntoApp Platform
        </p>
        <div className="flex gap-4">
          <Link
            href="/login"
            className="text-sm font-medium hover:text-indigo-600 transition"
          >
            Sign In
          </Link>
          <Link
            href="/signup"
            className="text-sm font-medium bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition"
          >
            Get Started
          </Link>
        </div>
      </div>

      <div className="text-center my-16 max-w-3xl">
        <h1 className="text-4xl md:text-6xl font-extrabold tracking-tight text-slate-900 mb-6">
          Convert Your Store Into a White-Label Mobile App
        </h1>
        <p className="text-lg md:text-xl text-slate-600 mb-8 leading-relaxed">
          Zero coding required. Connect your WooCommerce or Shopify store, customize your brand theme, and publish high-performance native apps to Google Play and Apple App Store.
        </p>
        <div className="flex justify-center gap-4">
          <Link
            href="/signup"
            className="px-6 py-3 bg-indigo-600 text-white rounded-xl font-medium shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition"
          >
            Start Free Trial
          </Link>
          <Link
            href="/pricing"
            className="px-6 py-3 border border-slate-300 rounded-xl font-medium text-slate-700 hover:bg-slate-100 transition"
          >
            View Pricing
          </Link>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl w-full">
        <div className="p-6 border border-slate-200 rounded-2xl bg-white shadow-sm">
          <h3 className="font-semibold text-lg text-slate-900 mb-2">Automated Sync</h3>
          <p className="text-sm text-slate-600">
            Real-time catalog, categories, inventory, and order synchronization directly via secure store connectors.
          </p>
        </div>
        <div className="p-6 border border-slate-200 rounded-2xl bg-white shadow-sm">
          <h3 className="font-semibold text-lg text-slate-900 mb-2">Native Flutter Engine</h3>
          <p className="text-sm text-slate-600">
            60 FPS buttery smooth native user experience with dynamic sections, caching, and instant checkout.
          </p>
        </div>
        <div className="p-6 border border-slate-200 rounded-2xl bg-white shadow-sm">
          <h3 className="font-semibold text-lg text-slate-900 mb-2">Push Notifications</h3>
          <p className="text-sm text-slate-600">
            Recover abandoned carts and re-engage customers with automated FCM notifications and campaign broadcasts.
          </p>
        </div>
      </div>
    </main>
  );
}
