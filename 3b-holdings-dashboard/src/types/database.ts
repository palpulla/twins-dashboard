// Placeholder types. Replace with real types generated from your Supabase schema.
//
// After creating the Supabase project and running supabase/schema.sql, regenerate
// this file from your live database:
//
//   # If using a linked local project:
//   npm run types:gen
//
//   # Or directly with the CLI against your cloud project:
//   npx supabase gen types typescript --project-id <your-project-ref> > src/types/database.ts
//
// The supabase client in src/lib/supabase.ts is typed against this file, so
// regenerating it gives Claude Design / the operator full end-to-end type safety.

export type Json = string | number | boolean | null | { [key: string]: Json | undefined } | Json[];

export interface Database {
  public: {
    Tables: Record<string, { Row: Record<string, unknown>; Insert: Record<string, unknown>; Update: Record<string, unknown> }>;
    Views: Record<string, { Row: Record<string, unknown> }>;
    Functions: Record<string, unknown>;
    Enums: Record<string, unknown>;
  };
}
