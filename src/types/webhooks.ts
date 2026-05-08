export type HcpEventType =
  | 'customer.created' | 'customer.deleted' | 'customer.updated'
  | 'estimate.completed' | 'estimate.copy_to_job' | 'estimate.created'
  | 'estimate.on_my_way' | 'estimate.option.approval_status_changed'
  | 'estimate.option.created' | 'estimate.scheduled' | 'estimate.sent' | 'estimate.updated'
  | 'invoice.amount_updated' | 'invoice.canceled' | 'invoice.created'
  | 'invoice.paid' | 'invoice.payment.failed' | 'invoice.payment.succeeded'
  | 'invoice.refund.succeeded' | 'invoice.sent' | 'invoice.voided'
  | 'job.appointment.appointment_discarded' | 'job.appointment.appointment_pros_assigned'
  | 'job.appointment.appointment_pros_unassigned' | 'job.appointment.rescheduled'
  | 'job.appointment.scheduled' | 'job.canceled' | 'job.completed'
  | 'job.created' | 'job.deleted' | 'job.on_my_way' | 'job.paid'
  | 'job.scheduled' | 'job.started' | 'job.updated'
  | 'lead.converted' | 'lead.created' | 'lead.deleted' | 'lead.lost' | 'lead.updated'
  | 'pro.created';

export interface HcpWebhookPayload {
  event: HcpEventType;
  event_id: string;
  data: Record<string, unknown>;
  timestamp: string;
}

/**
 * Shape we expect on `data` for job.* events. Loosely typed; HCP may vary.
 * Verified field names: TBD against production payload.
 */
export interface HcpJobData {
  id?: string;
  notes?: string | null;
  assigned_employees?: { id: string; assigned_at?: string }[];
}

/**
 * Shape we expect on `data` for invoice.* events.
 */
export interface HcpInvoiceData {
  id?: string;
  job_id?: string;
  created_at?: string;
}

export type MarketingChannel =
  | 'google_ads'
  | 'google_lsa'
  | 'meta_ads'
  | 'website_contact_form'
  | 'website_chat'
  | 'organic'
  | 'referral';

export const MARKETING_CHANNEL_LABELS: Record<MarketingChannel, string> = {
  google_ads: 'Google Ads',
  google_lsa: 'Google LSA',
  meta_ads: 'Meta Ads',
  website_contact_form: 'Website Contact Form',
  website_chat: 'Website Chat',
  organic: 'Organic / Direct',
  referral: 'Referral',
};
