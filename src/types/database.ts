export interface Database {
  public: {
    Tables: {
      users: {
        Row: {
          id: string;
          email: string;
          full_name: string;
          role: 'technician' | 'csr' | 'manager' | 'owner';
          avatar_url: string | null;
          manager_id: string | null;
          is_active: boolean;
          created_at: string;
          updated_at: string;
        };
        Insert: Omit<Database['public']['Tables']['users']['Row'], 'id' | 'created_at' | 'updated_at'>;
        Update: Partial<Database['public']['Tables']['users']['Insert']>;
      };
      commission_tiers: {
        Row: {
          id: string;
          user_id: string;
          tier_level: number;
          rate: number;
          effective_date: string;
          created_at: string;
        };
        Insert: Omit<Database['public']['Tables']['commission_tiers']['Row'], 'id' | 'created_at'>;
        Update: Partial<Database['public']['Tables']['commission_tiers']['Insert']>;
      };
      customers: {
        Row: {
          id: string;
          hcp_id: string;
          name: string;
          email: string | null;
          phone: string | null;
          address: string | null;
          created_at: string;
          updated_at: string;
        };
        Insert: Omit<Database['public']['Tables']['customers']['Row'], 'id' | 'created_at' | 'updated_at'>;
        Update: Partial<Database['public']['Tables']['customers']['Insert']>;
      };
      jobs: {
        Row: {
          id: string;
          hcp_id: string;
          customer_id: string | null;
          technician_id: string | null;
          job_type: string;
          status: string;
          scheduled_at: string | null;
          completed_at: string | null;
          revenue: number;
          parts_cost: number;
          parts_cost_override: number | null;
          protection_plan_sold: boolean;
          created_at: string;
          updated_at: string;
        };
        Insert: Omit<Database['public']['Tables']['jobs']['Row'], 'id' | 'created_at' | 'updated_at'>;
        Update: Partial<Database['public']['Tables']['jobs']['Insert']>;
      };
      invoices: {
        Row: {
          id: string;
          hcp_id: string;
          job_id: string | null;
          customer_id: string | null;
          amount: number;
          status: string;
          paid_at: string | null;
          created_at: string;
          updated_at: string;
        };
        Insert: Omit<Database['public']['Tables']['invoices']['Row'], 'id' | 'created_at' | 'updated_at'>;
        Update: Partial<Database['public']['Tables']['invoices']['Insert']>;
      };
      estimates: {
        Row: {
          id: string;
          hcp_id: string;
          customer_id: string | null;
          technician_id: string | null;
          status: string;
          amount: number;
          created_at: string;
          updated_at: string;
        };
        Insert: Omit<Database['public']['Tables']['estimates']['Row'], 'id' | 'created_at' | 'updated_at'>;
        Update: Partial<Database['public']['Tables']['estimates']['Insert']>;
      };
      leads: {
        Row: {
          id: string;
          source: string;
          channel: string;
          status: string;
          customer_name: string | null;
          customer_phone: string | null;
          customer_email: string | null;
          converted_at: string | null;
          job_id: string | null;
          created_at: string;
          updated_at: string;
        };
        Insert: Omit<Database['public']['Tables']['leads']['Row'], 'id' | 'created_at' | 'updated_at'>;
        Update: Partial<Database['public']['Tables']['leads']['Insert']>;
      };
      commission_records: {
        Row: {
          id: string;
          job_id: string;
          technician_id: string;
          gross_revenue: number;
          parts_cost: number;
          net_revenue: number;
          tier_rate: number;
          commission_amount: number;
          manager_id: string | null;
          manager_override: number;
          manager_bonus: number;
          created_at: string;
        };
        Insert: Omit<Database['public']['Tables']['commission_records']['Row'], 'id' | 'created_at'>;
        Update: Partial<Database['public']['Tables']['commission_records']['Insert']>;
      };
      call_records: {
        Row: {
          id: string;
          caller_name: string | null;
          caller_phone: string | null;
          source: string;
          channel: string;
          duration_seconds: number;
          outcome: string;
          notes: string | null;
          csr_id: string | null;
          ghl_agency: string | null;
          created_at: string;
        };
        Insert: Omit<Database['public']['Tables']['call_records']['Row'], 'id' | 'created_at'>;
        Update: Partial<Database['public']['Tables']['call_records']['Insert']>;
      };
      marketing_spend: {
        Row: {
          id: string;
          channel: string;
          campaign: string | null;
          spend: number;
          impressions: number;
          clicks: number;
          conversions: number;
          date: string;
          created_at: string;
        };
        Insert: Omit<Database['public']['Tables']['marketing_spend']['Row'], 'id' | 'created_at'>;
        Update: Partial<Database['public']['Tables']['marketing_spend']['Insert']>;
      };
      reviews: {
        Row: {
          id: string;
          google_review_id: string | null;
          reviewer_name: string;
          rating: number;
          review_text: string | null;
          technician_id: string | null;
          review_date: string;
          created_at: string;
        };
        Insert: Omit<Database['public']['Tables']['reviews']['Row'], 'id' | 'created_at'>;
        Update: Partial<Database['public']['Tables']['reviews']['Insert']>;
      };
      kpi_definitions: {
        Row: {
          id: string;
          name: string;
          description: string | null;
          formula: string;
          data_source: string;
          target: number;
          display_format: string;
          is_active: boolean;
          inverted_status: boolean;
          sort_order: number;
          created_at: string;
          updated_at: string;
        };
        Insert: Omit<Database['public']['Tables']['kpi_definitions']['Row'], 'id' | 'created_at' | 'updated_at'>;
        Update: Partial<Database['public']['Tables']['kpi_definitions']['Insert']>;
      };
      raw_events: {
        Row: {
          id: string;
          event_type: string;
          source: string;
          payload: Record<string, unknown>;
          received_at: string;
          processed_at: string | null;
          status: string;
          error_message: string | null;
        };
        Insert: Omit<Database['public']['Tables']['raw_events']['Row'], 'id' | 'received_at'>;
        Update: Partial<Database['public']['Tables']['raw_events']['Insert']>;
      };
      audit_log: {
        Row: {
          id: string;
          table_name: string;
          record_id: string;
          action: string;
          old_data: Record<string, unknown> | null;
          new_data: Record<string, unknown> | null;
          user_id: string | null;
          created_at: string;
        };
        Insert: Omit<Database['public']['Tables']['audit_log']['Row'], 'id' | 'created_at'>;
        Update: Partial<Database['public']['Tables']['audit_log']['Insert']>;
      };
      parts_catalog: {
        Row: {
          id: string;
          sku: string | null;
          name: string;
          category: string;
          price: number;
          description: string | null;
          is_active: boolean;
          created_at: string;
          updated_at: string;
        };
        Insert: Omit<Database['public']['Tables']['parts_catalog']['Row'], 'id' | 'created_at' | 'updated_at'>;
        Update: Partial<Database['public']['Tables']['parts_catalog']['Insert']>;
      };
    };
  };
}

export type Tables<T extends keyof Database['public']['Tables']> = Database['public']['Tables'][T]['Row'];
export type InsertTables<T extends keyof Database['public']['Tables']> = Database['public']['Tables'][T]['Insert'];
export type UpdateTables<T extends keyof Database['public']['Tables']> = Database['public']['Tables'][T]['Update'];
