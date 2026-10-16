import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const BACKEND_BASE_URL = process.env.BACKEND_API_URL || "http://127.0.0.1:8000";

export async function POST(req: NextRequest) {
  const token = cookies().get("auth_token")?.value;

  if (token) {
    try {
      await fetch(`${BACKEND_BASE_URL}/api/auth/logout`, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });
    } catch {
      // Ignore backend logout errors, always clear cookie
    }
  }

  const res = NextResponse.json({ message: "Logged out successfully" });
  res.cookies.delete("auth_token");
  return res;
}
