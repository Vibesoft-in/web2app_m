import { NextRequest, NextResponse } from "next/server";
import { serverFetch } from "@/lib/server-api";

export async function GET(
  req: NextRequest,
  { params }: { params: { ulid: string } }
) {
  const result = await serverFetch(`/api/apps/${params.ulid}/config/draft`);

  if (result.error) {
    return NextResponse.json(result.error, { status: result.status });
  }

  return NextResponse.json(result.data);
}

export async function PUT(
  req: NextRequest,
  { params }: { params: { ulid: string } }
) {
  const body = await req.json();
  const result = await serverFetch(`/api/apps/${params.ulid}/config/draft`, {
    method: "PUT",
    body: JSON.stringify(body),
  });

  if (result.error) {
    return NextResponse.json(result.error, { status: result.status });
  }

  return NextResponse.json(result.data);
}
