import { createServerClient } from '@supabase/ssr';
import { NextResponse, type NextRequest } from 'next/server';

const DEMO_SUPABASE_URL = 'https://demo.supabase.co';

function isDemoMode(): boolean {
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  return !url || url === DEMO_SUPABASE_URL || url === 'https://your-project.supabase.co';
}

export async function updateSession(request: NextRequest) {
  const supabaseResponse = NextResponse.next({ request });

  // In demo mode (no real Supabase credentials), skip auth checks entirely.
  // The dashboard layout auto-logs in as CEO using seed data.
  if (isDemoMode()) {
    return supabaseResponse;
  }

  // --- Production mode: real Supabase auth ---
  let response = supabaseResponse;

  const supabase = createServerClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!,
    {
      cookies: {
        getAll() {
          return request.cookies.getAll();
        },
        setAll(cookiesToSet) {
          cookiesToSet.forEach(({ name, value }) =>
            request.cookies.set(name, value)
          );
          response = NextResponse.next({ request });
          cookiesToSet.forEach(({ name, value, options }) =>
            response.cookies.set(name, value, options)
          );
        },
      },
    }
  );

  const {
    data: { user },
  } = await supabase.auth.getUser();

  const isAuthPage = request.nextUrl.pathname === '/login';
  const isPublicPath = request.nextUrl.pathname.startsWith('/api/webhooks');

  if (isPublicPath) {
    return response;
  }

  if (!user && !isAuthPage) {
    const url = request.nextUrl.clone();
    url.pathname = '/login';
    return NextResponse.redirect(url);
  }

  if (user && isAuthPage) {
    const url = request.nextUrl.clone();
    url.pathname = '/dashboard';
    return NextResponse.redirect(url);
  }

  return response;
}
