"use client";

import { useMemo } from "react";
import { Heart, Home, Search, ShoppingBag, User, Wifi, Battery, Sparkles } from "lucide-react";

interface PhoneFrameProps {
  config: any;
  products?: any[];
  categories?: any[];
  logoUrl?: string;
  bannerUrl?: string;
}

export function PhoneFrame({ config, products = [], categories = [], logoUrl, bannerUrl }: PhoneFrameProps) {
  const theme = config?.theme || {};
  const colors = theme.colors || {
    primary: "#E91E63",
    secondary: "#222222",
    background: "#FFFFFF",
    surface: "#FAFAFA",
    text: "#111111",
  };
  const radius = theme.radius !== undefined ? `${theme.radius}px` : "12px";
  const appName = (config?.app?.name && config.app.name !== "Glow Beauty") ? config.app.name : "My Mobile Store";
  const currency = config?.app?.currency || "INR";
  const currencySymbol = currency === "USD" ? "$" : currency === "EUR" ? "€" : "₹";

  const homeSections = useMemo(() => {
    if (Array.isArray(config?.home) && config.home.length > 0) {
      return config.home;
    }
    return [
      {
        type: "banner",
        items: [{ image: bannerUrl || "", link: "category:all" }],
      },
      { type: "categories" },
      { type: "product_rail", title: "All Products", source: "recent" },
    ];
  }, [config?.home, bannerUrl]);

  const fallbackBanner = bannerUrl || products[0]?.images?.[0] || null;

  return (
    <div className="relative mx-auto w-full max-w-[290px] xs:max-w-[310px] sm:max-w-[340px] md:max-w-[360px] h-[540px] sm:h-[620px] md:h-[660px] bg-slate-950 rounded-[42px] sm:rounded-[46px] p-2.5 sm:p-3 shadow-2xl border-4 border-slate-800 ring-1 ring-slate-900/50 flex flex-col select-none transition-all duration-300">
      {/* Speaker / Dynamic Island */}
      <div className="absolute top-3.5 left-1/2 -translate-x-1/2 w-28 h-5 bg-black rounded-full z-30 flex items-center justify-center shadow-inner">
        <div className="w-2.5 h-2.5 rounded-full bg-slate-900 mr-2" />
        <div className="w-10 h-1 bg-slate-800 rounded-full" />
      </div>

      {/* Screen container */}
      <div
        className="w-full h-full rounded-[34px] sm:rounded-[38px] overflow-hidden flex flex-col relative text-xs"
        style={{
          backgroundColor: colors.background || "#FFFFFF",
          color: colors.text || "#111111",
        }}
      >
        {/* Status Bar */}
        <div className="h-7 pt-1 px-5 flex items-center justify-between z-20 text-[10px] font-medium text-slate-500">
          <span>9:41</span>
          <div className="flex items-center gap-1.5">
            <Wifi className="w-3 h-3" />
            <Battery className="w-3.5 h-3.5" />
          </div>
        </div>

        {/* In-App Header */}
        <div
          className="px-4 py-2.5 border-b flex items-center justify-between z-10"
          style={{
            borderColor: "rgba(0,0,0,0.06)",
            backgroundColor: colors.surface || "#FAFAFA",
          }}
        >
          <div className="flex items-center gap-2">
            {logoUrl ? (
              <img
                src={logoUrl}
                alt={appName}
                className="w-6 h-6 rounded-md object-contain bg-white shadow-sm"
              />
            ) : (
              <div
                className="w-6 h-6 rounded-md flex items-center justify-center text-white font-bold text-[10px] shadow-sm"
                style={{ backgroundColor: colors.primary || "#E91E63" }}
              >
                {appName.charAt(0)}
              </div>
            )}
            <span className="font-bold text-sm tracking-tight truncate max-w-[130px] sm:max-w-[160px]">
              {appName}
            </span>
          </div>
          <div className="flex items-center gap-2 text-slate-500">
            <Search className="w-4 h-4 cursor-pointer hover:text-slate-800" />
            <ShoppingBag className="w-4 h-4 cursor-pointer hover:text-slate-800" />
          </div>
        </div>

        {/* App Content Scrollable (No native desktop scrollbar, smooth touch/mouse scroll) */}
        <div className="flex-1 overflow-y-auto no-scrollbar pb-24 space-y-4 pt-2">
          {homeSections.map((sec: any, idx: number) => {
            if (sec.type === "banner") {
              return (
                <div key={idx} className="px-4">
                  <div
                    className="w-full h-32 rounded-xl overflow-hidden relative shadow-sm flex items-end p-3 text-white"
                    style={{
                      borderRadius: radius,
                      background: colors.primary
                        ? `linear-gradient(135deg, ${colors.primary}, #1E1B4B)`
                        : "linear-gradient(135deg, #E91E63, #1E1B4B)",
                    }}
                  >
                    {fallbackBanner && (
                      <img
                        src={fallbackBanner}
                        alt="Hero Banner"
                        className="absolute inset-0 w-full h-full object-cover mix-blend-overlay opacity-60"
                      />
                    )}
                    <div className="relative z-10">
                      <span className="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 bg-black/40 rounded backdrop-blur-md">
                        Featured Store Deal
                      </span>
                      <h4 className="font-extrabold text-sm mt-1 text-white drop-shadow">
                        Exclusive Online Collection
                      </h4>
                    </div>
                  </div>
                </div>
              );
            }

            if (sec.type === "categories") {
              const cats = categories.length > 0 ? categories : [
                { id: 1, name: "Clothing" },
                { id: 2, name: "Accessories" },
                { id: 3, name: "Featured" },
              ];

              return (
                <div key={idx} className="px-4">
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-bold text-xs tracking-tight">Categories</span>
                    <span className="text-[10px] text-slate-400 cursor-pointer">See all</span>
                  </div>
                  <div className="flex gap-3 overflow-x-auto pb-1 no-scrollbar">
                    {cats.map((c: any) => {
                      const imgUrl = c.image?.src || (typeof c.image === "string" ? c.image : null);

                      return (
                        <div
                          key={c.id || c.name}
                          className="flex flex-col items-center gap-1.5 flex-shrink-0 cursor-pointer group"
                        >
                          <div className="w-12 h-12 rounded-full overflow-hidden border border-slate-200 bg-slate-100 flex items-center justify-center shadow-sm">
                            {imgUrl ? (
                              <img
                                src={imgUrl}
                                alt={c.name}
                                className="w-full h-full object-cover group-hover:scale-105 transition"
                              />
                            ) : (
                              <div
                                className="w-full h-full flex items-center justify-center text-white text-xs font-bold"
                                style={{ backgroundColor: colors.primary || "#E91E63" }}
                              >
                                {c.name.charAt(0)}
                              </div>
                            )}
                          </div>
                          <span className="text-[10px] font-medium text-slate-700 truncate max-w-[56px] text-center">
                            {c.name}
                          </span>
                        </div>
                      );
                    })}
                  </div>
                </div>
              );
            }

            if (sec.type === "product_rail") {
              const items = products.length > 0 ? products : [
                { id: 101, title: "Store Product", price: 79 },
              ];

              return (
                <div key={idx} className="px-4">
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-bold text-xs tracking-tight">{sec.title || "Store Products"}</span>
                    <span className="text-[10px] text-slate-400 cursor-pointer">View all ({items.length})</span>
                  </div>
                  <div className="grid grid-cols-2 gap-2.5">
                    {items.map((prod: any) => {
                      const prodImg = prod.images?.[0] || null;

                      return (
                        <div
                          key={prod.id}
                          className="border border-slate-100 rounded-xl p-2 flex flex-col justify-between shadow-xs hover:shadow-sm transition"
                          style={{
                            backgroundColor: colors.surface || "#FAFAFA",
                            borderRadius: radius,
                          }}
                        >
                          <div className="w-full h-24 bg-white rounded-lg mb-2 flex items-center justify-center text-slate-400 overflow-hidden relative border border-slate-100/60">
                            {prodImg ? (
                              <img
                                src={prodImg}
                                alt={prod.title}
                                className="w-full h-full object-cover hover:scale-105 transition duration-300"
                              />
                            ) : (
                              <Sparkles className="w-6 h-6 text-slate-300" />
                            )}
                          </div>
                          <div>
                            <p className="font-semibold text-[11px] truncate text-slate-800" title={prod.title}>
                              {prod.title}
                            </p>
                            <div className="flex items-center justify-between mt-1">
                              <span
                                className="font-black text-xs"
                                style={{ color: colors.primary || "#E91E63" }}
                              >
                                {currencySymbol}{prod.price}
                              </span>
                              <Heart className="w-3.5 h-3.5 text-slate-400 hover:text-rose-500 cursor-pointer" />
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              );
            }

            return null;
          })}
        </div>

        {/* Bottom Nav Bar with Rounded Bottom Corners & Home Indicator */}
        <div
          className="absolute bottom-0 left-0 right-0 border-t z-20 rounded-b-[34px] sm:rounded-b-[38px] flex flex-col pt-1.5 pb-2 px-5 shadow-lg"
          style={{
            backgroundColor: colors.surface || "#FAFAFA",
            borderColor: "rgba(0,0,0,0.06)",
          }}
        >
          <div className="flex items-center justify-between">
            <div className="flex flex-col items-center text-[9px] font-semibold" style={{ color: colors.primary }}>
              <Home className="w-4 h-4" />
              <span className="mt-0.5">Home</span>
            </div>
            <div className="flex flex-col items-center text-[9px] text-slate-400 font-medium">
              <Search className="w-4 h-4" />
              <span className="mt-0.5">Explore</span>
            </div>
            <div className="flex flex-col items-center text-[9px] text-slate-400 font-medium">
              <ShoppingBag className="w-4 h-4" />
              <span className="mt-0.5">Cart</span>
            </div>
            <div className="flex flex-col items-center text-[9px] text-slate-400 font-medium">
              <User className="w-4 h-4" />
              <span className="mt-0.5">Account</span>
            </div>
          </div>
          {/* Home indicator bar for modern authentic smartphone feel */}
          <div className="w-20 h-1 bg-slate-300 rounded-full mx-auto mt-1.5" />
        </div>
      </div>
    </div>
  );
}
