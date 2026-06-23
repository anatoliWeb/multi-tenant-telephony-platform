<template>
  <section class="billing-page">
    <header class="c-card billing-page__header">
      <div>
        <h2>Billing & Subscription</h2>
        <p>Plan, usage, invoices, and payment infrastructure for future subscription workflows.</p>
      </div>
      <button type="button" class="billing-page__cta">Upgrade Plan</button>
    </header>

    <div class="billing-page__metrics">
      <BillingMetricCard label="Current Plan" :value="overview.planName" meta="Managed subscription tier" />
      <BillingMetricCard label="Status" :value="overview.subscriptionStatus" meta="Billing lifecycle state" />
      <BillingMetricCard label="API Usage" :value="usageValue" :meta="usageMeta" />
    </div>

    <div class="billing-page__grid">
      <article class="c-card"><h3>Invoices</h3><p>Invoice history and downloadable statements will be connected here.</p></article>
      <article class="c-card"><h3>Payment Method</h3><p>Primary payment source and backup method management placeholder.</p></article>
      <article class="c-card"><h3>API Usage Policy</h3><p>Rate-limit quota and overage policy view for platform administrators.</p></article>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import BillingMetricCard from '../components/BillingMetricCard.vue';
import { billingService } from '../services/billing.service';
import type { BillingOverview } from '../types/billing.types';

/**
 * Billing module is separated from platform settings because subscription
 * concerns evolve with provider integrations, invoicing, quotas, and tenant
 * contracts. This page establishes that boundary early for scalable SaaS ops.
 */
const overview = ref<BillingOverview>({
  planName: 'Business',
  subscriptionStatus: 'active',
  monthlyApiUsage: 0,
  monthlyApiLimit: 0,
});

const usageValue = computed(() => `${overview.value.monthlyApiUsage.toLocaleString()} req`);
const usageMeta = computed(() => `of ${overview.value.monthlyApiLimit.toLocaleString()} monthly limit`);

onMounted(async () => {
  overview.value = await billingService.fetchOverview();
});
</script>

<style scoped>
.billing-page{display:grid;gap:12px}
.billing-page__header{margin-top:0;display:flex;justify-content:space-between;align-items:center;gap:12px}
.billing-page__header h2{margin:0;font-size:18px;color:#f8fafc}
.billing-page__header p{margin:6px 0 0;color:#94a3b8;font-size:13px}
.billing-page__cta{height:34px;border-radius:8px;border:1px solid rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe;padding:0 12px;font-size:12px}
.billing-page__metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.billing-page__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.billing-page__grid article{margin-top:0}
.billing-page__grid h3{margin:0 0 8px;color:#f8fafc;font-size:15px}
.billing-page__grid p{margin:0;color:#94a3b8;font-size:12px;line-height:1.5}
@media (max-width:1080px){.billing-page__metrics,.billing-page__grid{grid-template-columns:1fr}}
@media (max-width:760px){.billing-page__header{flex-direction:column;align-items:flex-start}}
</style>

