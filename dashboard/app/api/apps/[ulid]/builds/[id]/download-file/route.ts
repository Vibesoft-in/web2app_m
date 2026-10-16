import { NextRequest, NextResponse } from "next/server";
import { serverFetch } from "@/lib/server-api";

export async function GET(
  req: NextRequest,
  { params }: { params: { ulid: string; id: string } }
) {
  const format = req.nextUrl.searchParams.get("format") || "apk";

  // 1. Fetch signed download route from backend
  const signedRes = await serverFetch<{ download_url: string; version_code: number; format?: string }>(
    `/api/apps/${params.ulid}/builds/${params.id}/download?format=${format}`
  );

  let targetUrl = signedRes.data?.download_url;
  if (!targetUrl) {
    targetUrl = `http://127.0.0.1:8000/api/apps/${params.ulid}/builds/${params.id}/download-file?format=${format}`;
  }

  // 2. Fetch binary stream from the backend URL
  try {
    const fileRes = await fetch(targetUrl);
    if (!fileRes.ok) {
      return NextResponse.json(
        { error: "Artifact binary not found on build host." },
        { status: fileRes.status }
      );
    }

    const arrayBuffer = await fileRes.arrayBuffer();
    const resolvedFormat = signedRes.data?.format || format;
    const filename = resolvedFormat === "admin"
      ? `app-${params.ulid}-admin-portal.json`
      : `app-${params.ulid}-v${signedRes.data?.version_code || 1}.${resolvedFormat}`;

    const contentType = resolvedFormat === "apk" 
      ? "application/vnd.android.package-archive" 
      : resolvedFormat === "admin"
      ? "application/json"
      : "application/octet-stream";

    return new NextResponse(arrayBuffer, {
      status: 200,
      headers: {
        "Content-Type": contentType,
        "Content-Disposition": `attachment; filename="${filename}"`,
        "Cache-Control": "no-store, no-cache, must-revalidate",
      },
    });
  } catch (err: any) {
    return NextResponse.json(
      { error: "Unable to stream build package: " + err.message },
      { status: 502 }
    );
  }
}
