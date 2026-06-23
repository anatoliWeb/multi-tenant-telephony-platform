import type { BillingOverview } from '../types/billing.types';

/**
 * Billing foundation service.
 *
 * This module intentionally returns placeholder contract data until subscription
 * providers (Stripe/Paddle) are integrated. Keeping a dedicated service now
 * allows future backend billing adapters without UI rewrites.
 */
export const billingService = {
  async fetchOverview(): Promise<BillingOverview> {
    return {
      planName: 'Business',
      subscriptionStatus: 'active',
      monthlyApiUsage: 38214,
      monthlyApiLimit: 100000,
    };
  },
};

