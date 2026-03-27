import { NextResponse, type NextRequest } from 'next/server';

export async function updateSession(request: NextRequest) {
  const response = NextResponse.next({ request });

  // Skip auth — go straight to dashboard
  // TODO: Re-enable auth when user accounts are set up in Supabase Auth
  const isPublicPath = request.nextUrl.pathname.startsWith('/api/webhooks');
  if (isPublicPath) {
    return response;
  }

  return response;
}
