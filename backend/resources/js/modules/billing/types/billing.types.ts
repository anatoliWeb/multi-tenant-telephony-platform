export interface BillingOverview {
  planName: string;
  subscriptionStatus: 'active' | 'trial' | 'paused';
  monthlyApiUsage: number;
  monthlyApiLimit: number;
}

