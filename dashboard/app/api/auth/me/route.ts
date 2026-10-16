import { NextResponse } from "next/server";
import { serverFetch } from "@/lib/server-api";

export async function GET() {
  const result = await serverFetch("/api/user");

  if (result.error) {
    return NextResponse.json(result.error, { status: result.status });
  }

  return NextResponse.json({ user: result.data });
}
