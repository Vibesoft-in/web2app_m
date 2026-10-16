import { NextRequest, NextResponse } from "next/server";
import { serverFetch } from "@/lib/server-api";

export async function PUT(request: NextRequest) {
  const body = await request.json();
  const result = await serverFetch("/api/user/password", {
    method: "PUT",
    body: JSON.stringify(body),
  });

  if (result.error) {
    return NextResponse.json(result.error, { status: result.status });
  }

  return NextResponse.json(result.data);
}
