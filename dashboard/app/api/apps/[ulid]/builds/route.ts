import { NextRequest, NextResponse } from "next/server";
import { serverFetch } from "@/lib/server-api";

export async function GET(
  req: NextRequest,
  { params }: { params: { ulid: string } }
) {
  const result = await serverFetch(`/api/apps/${params.ulid}/builds`);

  if (result.error) {
    return NextResponse.json(result.error, { status: result.status });
  }

  return NextResponse.json(result.data);
}

export async function POST(
  req: NextRequest,
  { params }: { params: { ulid: string } }
) {
  const result = await serverFetch(`/api/apps/${params.ulid}/builds`, {
    method: "POST",
  });

  if (result.error) {
    return NextResponse.json(result.error, { status: result.status });
  }

  return NextResponse.json(result.data, { status: result.status });
}
