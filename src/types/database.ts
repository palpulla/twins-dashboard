export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  graphql_public: {
    Tables: {
      [_ in never]: never
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      graphql: {
        Args: {
          extensions?: Json
          operationName?: string
          query?: string
          variables?: Json
        }
        Returns: Json
      }
    }
    Enums: {
      [_ in never]: never
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
  public: {
    Tables: {
      app_settings: {
        Row: {
          co_tech_default_user_id: string | null
          digest_cron_expression: string
          digest_recipient_email: string
          digest_time: string
          digest_timezone: string
          enabled_button_checks: string[]
          id: number
          last_digest_sent_at: string | null
          notes_threshold_dollars: number
          pay_grace_hours: number
          updated_at: string
        }
        Insert: {
          co_tech_default_user_id?: string | null
          digest_cron_expression?: string
          digest_recipient_email: string
          digest_time?: string
          digest_timezone?: string
          enabled_button_checks?: string[]
          id?: number
          last_digest_sent_at?: string | null
          notes_threshold_dollars?: number
          pay_grace_hours?: number
          updated_at?: string
        }
        Update: {
          co_tech_default_user_id?: string | null
          digest_cron_expression?: string
          digest_recipient_email?: string
          digest_time?: string
          digest_timezone?: string
          enabled_button_checks?: string[]
          id?: number
          last_digest_sent_at?: string | null
          notes_threshold_dollars?: number
          pay_grace_hours?: number
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "app_settings_co_tech_default_user_id_fkey"
            columns: ["co_tech_default_user_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      audit_log: {
        Row: {
          action: string
          created_at: string
          id: string
          new_data: Json | null
          old_data: Json | null
          record_id: string
          table_name: string
          user_id: string | null
        }
        Insert: {
          action: string
          created_at?: string
          id?: string
          new_data?: Json | null
          old_data?: Json | null
          record_id: string
          table_name: string
          user_id?: string | null
        }
        Update: {
          action?: string
          created_at?: string
          id?: string
          new_data?: Json | null
          old_data?: Json | null
          record_id?: string
          table_name?: string
          user_id?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "audit_log_user_id_fkey"
            columns: ["user_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      call_records: {
        Row: {
          caller_name: string | null
          caller_phone: string | null
          channel: string
          created_at: string
          csr_id: string | null
          duration_seconds: number
          ghl_agency: string | null
          id: string
          notes: string | null
          outcome: string
          source: string
        }
        Insert: {
          caller_name?: string | null
          caller_phone?: string | null
          channel: string
          created_at?: string
          csr_id?: string | null
          duration_seconds?: number
          ghl_agency?: string | null
          id?: string
          notes?: string | null
          outcome?: string
          source: string
        }
        Update: {
          caller_name?: string | null
          caller_phone?: string | null
          channel?: string
          created_at?: string
          csr_id?: string | null
          duration_seconds?: number
          ghl_agency?: string | null
          id?: string
          notes?: string | null
          outcome?: string
          source?: string
        }
        Relationships: [
          {
            foreignKeyName: "call_records_csr_id_fkey"
            columns: ["csr_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      commission_records: {
        Row: {
          commission_amount: number
          created_at: string
          gross_revenue: number
          id: string
          job_id: string
          manager_bonus: number
          manager_id: string | null
          manager_override: number
          net_revenue: number
          parts_cost: number
          technician_id: string
          tier_rate: number
        }
        Insert: {
          commission_amount: number
          created_at?: string
          gross_revenue: number
          id?: string
          job_id: string
          manager_bonus?: number
          manager_id?: string | null
          manager_override?: number
          net_revenue: number
          parts_cost: number
          technician_id: string
          tier_rate: number
        }
        Update: {
          commission_amount?: number
          created_at?: string
          gross_revenue?: number
          id?: string
          job_id?: string
          manager_bonus?: number
          manager_id?: string | null
          manager_override?: number
          net_revenue?: number
          parts_cost?: number
          technician_id?: string
          tier_rate?: number
        }
        Relationships: [
          {
            foreignKeyName: "commission_records_job_id_fkey"
            columns: ["job_id"]
            isOneToOne: false
            referencedRelation: "jobs"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "commission_records_manager_id_fkey"
            columns: ["manager_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "commission_records_technician_id_fkey"
            columns: ["technician_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      commission_tiers: {
        Row: {
          created_at: string
          effective_date: string
          id: string
          rate: number
          tier_level: number
          user_id: string
        }
        Insert: {
          created_at?: string
          effective_date: string
          id?: string
          rate: number
          tier_level: number
          user_id: string
        }
        Update: {
          created_at?: string
          effective_date?: string
          id?: string
          rate?: number
          tier_level?: number
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "commission_tiers_user_id_fkey"
            columns: ["user_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      customers: {
        Row: {
          address: string | null
          created_at: string
          email: string | null
          hcp_id: string
          id: string
          name: string
          phone: string | null
          updated_at: string
        }
        Insert: {
          address?: string | null
          created_at?: string
          email?: string | null
          hcp_id: string
          id?: string
          name: string
          phone?: string | null
          updated_at?: string
        }
        Update: {
          address?: string | null
          created_at?: string
          email?: string | null
          hcp_id?: string
          id?: string
          name?: string
          phone?: string | null
          updated_at?: string
        }
        Relationships: []
      }
      estimates: {
        Row: {
          amount: number
          created_at: string
          customer_id: string | null
          hcp_id: string
          id: string
          status: string
          technician_id: string | null
          updated_at: string
        }
        Insert: {
          amount?: number
          created_at?: string
          customer_id?: string | null
          hcp_id: string
          id?: string
          status?: string
          technician_id?: string | null
          updated_at?: string
        }
        Update: {
          amount?: number
          created_at?: string
          customer_id?: string | null
          hcp_id?: string
          id?: string
          status?: string
          technician_id?: string | null
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "estimates_customer_id_fkey"
            columns: ["customer_id"]
            isOneToOne: false
            referencedRelation: "customers"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "estimates_technician_id_fkey"
            columns: ["technician_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      invoices: {
        Row: {
          amount: number
          created_at: string
          customer_id: string | null
          hcp_id: string
          id: string
          job_id: string | null
          paid_at: string | null
          status: string
          updated_at: string
        }
        Insert: {
          amount?: number
          created_at?: string
          customer_id?: string | null
          hcp_id: string
          id?: string
          job_id?: string | null
          paid_at?: string | null
          status?: string
          updated_at?: string
        }
        Update: {
          amount?: number
          created_at?: string
          customer_id?: string | null
          hcp_id?: string
          id?: string
          job_id?: string | null
          paid_at?: string | null
          status?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "invoices_customer_id_fkey"
            columns: ["customer_id"]
            isOneToOne: false
            referencedRelation: "customers"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "invoices_job_id_fkey"
            columns: ["job_id"]
            isOneToOne: false
            referencedRelation: "jobs"
            referencedColumns: ["id"]
          },
        ]
      }
      job_technicians: {
        Row: {
          assigned_at: string
          job_id: string
          technician_id: string
        }
        Insert: {
          assigned_at?: string
          job_id: string
          technician_id: string
        }
        Update: {
          assigned_at?: string
          job_id?: string
          technician_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "job_technicians_job_id_fkey"
            columns: ["job_id"]
            isOneToOne: false
            referencedRelation: "jobs"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "job_technicians_technician_id_fkey"
            columns: ["technician_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      jobs: {
        Row: {
          completed_at: string | null
          created_at: string
          customer_id: string | null
          hcp_id: string
          id: string
          invoiced_at: string | null
          job_type: string
          parts_cost: number
          parts_cost_override: number | null
          protection_plan_sold: boolean
          revenue: number
          scheduled_at: string | null
          started_at: string | null
          status: string
          technician_id: string | null
          updated_at: string
          work_notes: string | null
        }
        Insert: {
          completed_at?: string | null
          created_at?: string
          customer_id?: string | null
          hcp_id: string
          id?: string
          invoiced_at?: string | null
          job_type: string
          parts_cost?: number
          parts_cost_override?: number | null
          protection_plan_sold?: boolean
          revenue?: number
          scheduled_at?: string | null
          started_at?: string | null
          status?: string
          technician_id?: string | null
          updated_at?: string
          work_notes?: string | null
        }
        Update: {
          completed_at?: string | null
          created_at?: string
          customer_id?: string | null
          hcp_id?: string
          id?: string
          invoiced_at?: string | null
          job_type?: string
          parts_cost?: number
          parts_cost_override?: number | null
          protection_plan_sold?: boolean
          revenue?: number
          scheduled_at?: string | null
          started_at?: string | null
          status?: string
          technician_id?: string | null
          updated_at?: string
          work_notes?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "jobs_customer_id_fkey"
            columns: ["customer_id"]
            isOneToOne: false
            referencedRelation: "customers"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "jobs_technician_id_fkey"
            columns: ["technician_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      kpi_definitions: {
        Row: {
          created_at: string
          data_source: string
          description: string | null
          display_format: string
          formula: string
          id: string
          inverted_status: boolean
          is_active: boolean
          name: string
          sort_order: number
          target: number
          updated_at: string
        }
        Insert: {
          created_at?: string
          data_source: string
          description?: string | null
          display_format?: string
          formula: string
          id?: string
          inverted_status?: boolean
          is_active?: boolean
          name: string
          sort_order?: number
          target?: number
          updated_at?: string
        }
        Update: {
          created_at?: string
          data_source?: string
          description?: string | null
          display_format?: string
          formula?: string
          id?: string
          inverted_status?: boolean
          is_active?: boolean
          name?: string
          sort_order?: number
          target?: number
          updated_at?: string
        }
        Relationships: []
      }
      leads: {
        Row: {
          channel: string
          converted_at: string | null
          created_at: string
          customer_email: string | null
          customer_name: string | null
          customer_phone: string | null
          id: string
          job_id: string | null
          source: string
          status: string
          updated_at: string
        }
        Insert: {
          channel: string
          converted_at?: string | null
          created_at?: string
          customer_email?: string | null
          customer_name?: string | null
          customer_phone?: string | null
          id?: string
          job_id?: string | null
          source: string
          status?: string
          updated_at?: string
        }
        Update: {
          channel?: string
          converted_at?: string | null
          created_at?: string
          customer_email?: string | null
          customer_name?: string | null
          customer_phone?: string | null
          id?: string
          job_id?: string | null
          source?: string
          status?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "leads_job_id_fkey"
            columns: ["job_id"]
            isOneToOne: false
            referencedRelation: "jobs"
            referencedColumns: ["id"]
          },
        ]
      }
      marketing_spend: {
        Row: {
          campaign: string | null
          channel: string
          clicks: number
          conversions: number
          created_at: string
          date: string
          id: string
          impressions: number
          spend: number
        }
        Insert: {
          campaign?: string | null
          channel: string
          clicks?: number
          conversions?: number
          created_at?: string
          date: string
          id?: string
          impressions?: number
          spend?: number
        }
        Update: {
          campaign?: string | null
          channel?: string
          clicks?: number
          conversions?: number
          created_at?: string
          date?: string
          id?: string
          impressions?: number
          spend?: number
        }
        Relationships: []
      }
      raw_events: {
        Row: {
          error_message: string | null
          event_type: string
          id: string
          payload: Json
          processed_at: string | null
          received_at: string
          source: string
          status: string
        }
        Insert: {
          error_message?: string | null
          event_type: string
          id?: string
          payload: Json
          processed_at?: string | null
          received_at?: string
          source: string
          status?: string
        }
        Update: {
          error_message?: string | null
          event_type?: string
          id?: string
          payload?: Json
          processed_at?: string | null
          received_at?: string
          source?: string
          status?: string
        }
        Relationships: []
      }
      reviews: {
        Row: {
          created_at: string
          google_review_id: string | null
          id: string
          rating: number
          review_date: string
          review_text: string | null
          reviewer_name: string
          technician_id: string | null
        }
        Insert: {
          created_at?: string
          google_review_id?: string | null
          id?: string
          rating: number
          review_date: string
          review_text?: string | null
          reviewer_name: string
          technician_id?: string | null
        }
        Update: {
          created_at?: string
          google_review_id?: string | null
          id?: string
          rating?: number
          review_date?: string
          review_text?: string | null
          reviewer_name?: string
          technician_id?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "reviews_technician_id_fkey"
            columns: ["technician_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      supervisor_alerts: {
        Row: {
          alert_type: string
          attributed_tech_id: string | null
          created_at: string
          details: Json
          digest_date: string
          id: string
          job_id: string
          resolved_at: string | null
          resolved_by: string | null
        }
        Insert: {
          alert_type: string
          attributed_tech_id?: string | null
          created_at?: string
          details: Json
          digest_date: string
          id?: string
          job_id: string
          resolved_at?: string | null
          resolved_by?: string | null
        }
        Update: {
          alert_type?: string
          attributed_tech_id?: string | null
          created_at?: string
          details?: Json
          digest_date?: string
          id?: string
          job_id?: string
          resolved_at?: string | null
          resolved_by?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "supervisor_alerts_attributed_tech_id_fkey"
            columns: ["attributed_tech_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "supervisor_alerts_job_id_fkey"
            columns: ["job_id"]
            isOneToOne: false
            referencedRelation: "jobs"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "supervisor_alerts_resolved_by_fkey"
            columns: ["resolved_by"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
      users: {
        Row: {
          auth_id: string | null
          avatar_url: string | null
          created_at: string
          email: string
          full_name: string
          id: string
          is_active: boolean
          manager_id: string | null
          role: string
          updated_at: string
        }
        Insert: {
          auth_id?: string | null
          avatar_url?: string | null
          created_at?: string
          email: string
          full_name: string
          id?: string
          is_active?: boolean
          manager_id?: string | null
          role: string
          updated_at?: string
        }
        Update: {
          auth_id?: string | null
          avatar_url?: string | null
          created_at?: string
          email?: string
          full_name?: string
          id?: string
          is_active?: boolean
          manager_id?: string | null
          role?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "users_manager_id_fkey"
            columns: ["manager_id"]
            isOneToOne: false
            referencedRelation: "users"
            referencedColumns: ["id"]
          },
        ]
      }
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      calc_avg_ticket: {
        Args: { p_from?: string; p_technician_id?: string; p_to?: string }
        Returns: number
      }
      calc_conversion_rate: {
        Args: { p_from?: string; p_technician_id?: string; p_to?: string }
        Returns: number
      }
      calc_manager_earnings: {
        Args: { p_from?: string; p_manager_id: string; p_to?: string }
        Returns: {
          total_bonus: number
          total_override: number
        }[]
      }
      calc_total_commission: {
        Args: { p_from?: string; p_technician_id: string; p_to?: string }
        Returns: number
      }
      get_tech_scorecard: {
        Args: { p_from: string; p_technician_id: string; p_to: string }
        Returns: {
          avg_install_ticket: number
          avg_opportunity: number
          avg_repair_ticket: number
          avg_ticket: number
          callback_rate: number
          conversion_rate: number
          five_star_reviews: number
          new_doors_installed: number
          protection_plan_sales: number
          total_commission: number
          total_opportunities: number
        }[]
      }
      get_user_id: { Args: never; Returns: string }
      get_user_role: { Args: never; Returns: string }
      manages_user: { Args: { target_user_id: string }; Returns: boolean }
    }
    Enums: {
      [_ in never]: never
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DatabaseWithoutInternals = Omit<Database, "__InternalSupabase">

type DefaultSchema = DatabaseWithoutInternals[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof DatabaseWithoutInternals },
  EnumName extends DefaultSchemaEnumNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never = never,
> = DefaultSchemaEnumNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof DatabaseWithoutInternals },
  CompositeTypeName extends PublicCompositeTypeNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never = never,
> = PublicCompositeTypeNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  graphql_public: {
    Enums: {},
  },
  public: {
    Enums: {},
  },
} as const

