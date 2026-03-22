import { NextResponse } from 'next/server';

// Proxy/backup endpoint for HousecallPro webhooks
// Primary processing happens in Supabase Edge Function
// This endpoint can be used as a fallback or for local development

export async function POST(request: Request) {
  try {
    const payload = await request.json();

    // In production, forward to Supabase Edge Function
    const supabaseUrl = process.env.NEXT_PUBLIC_SUPABASE_URL;
    if (supabaseUrl) {
      const edgeFunctionUrl = `${supabaseUrl}/functions/v1/webhook-handler`;
      await fetch(edgeFunctionUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY}`,
        },
        body: JSON.stringify(payload),
      });
    }

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Webhook proxy error:', error);
    return NextResponse.json({ success: true }); // Always return 200 to prevent retries
  }
}
