<template>
  <article
    class="stat-card"
    :class="[
      `is-variant-${variant}`,
      { 'is-positive': trendDirection === 'up', 'is-negative': trendDirection === 'down', 'is-live': isLive },
    ]"
  >
    <header class="stat-card__head">
      <div class="stat-card__identity">
        <span v-if="$slots.icon" class="stat-card__icon-bubble">
          <slot name="icon" />
        </span>
        <p class="stat-card__title">{{ title }}</p>
      </div>
      <span v-if="trend" class="stat-card__trend" :class="trendDirectionClass">{{ trend }}</span>
    </header>

    <div class="stat-card__value">{{ value }}</div>

    <p v-if="subtitle" class="stat-card__subtitle">{{ subtitle }}</p>

    <footer class="stat-card__meta">
      <div class="stat-card__sparkline" :class="{ 'has-custom-sparkline': !!$slots.sparkline }">
        <slot name="sparkline">
          <svg viewBox="0 0 120 32" preserveAspectRatio="none" aria-hidden="true">
            <path d="M2 25 C18 10, 36 16, 52 12 S84 7, 118 5" />
          </svg>
        </slot>
      </div>
      <p v-if="meta" class="stat-card__meta-text">{{ meta }}</p>
    </footer>
  </article>
</template>

<script setup lang="ts">
/**
 * Reusable compact analytics metric card.
 *
 * WHY THIS HIERARCHY:
 * Analytics dashboards are scanned in seconds, so cards must foreground the
 * metric value while preserving context and trend signals in compact zones.
 * A props-driven card with optional icon/sparkline keeps the widget reusable
 * for future realtime, billing, and usage metrics without redesign.
 */
interface Props {
  title: string;
  value: string | number;
  subtitle?: string;
  trend?: string;
  trendDirection?: 'up' | 'down' | 'neutral';
  meta?: string;
  variant?: 'neutral' | 'success' | 'warning' | 'live';
  isLive?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  subtitle: '',
  trend: '',
  trendDirection: 'neutral',
  meta: '',
  variant: 'neutral',
  isLive: false,
});

const trendDirectionClass = {
  'is-up': props.trendDirection === 'up',
  'is-down': props.trendDirection === 'down',
  'is-neutral': props.trendDirection === 'neutral',
};
</script>

<style scoped>
.stat-card {
  border: 1px solid rgba(71, 85, 105, 0.5);
  background: linear-gradient(155deg, rgba(30, 41, 59, 0.84), rgba(15, 23, 42, 0.9));
  border-radius: 12px;
  padding: 12px;
  display: grid;
  gap: 7px;
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-1px);
  border-color: rgba(96, 165, 250, 0.45);
  box-shadow: 0 12px 20px rgba(2, 6, 23, 0.34);
}

.stat-card:active {
  transform: translateY(0);
}

.stat-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.stat-card__identity {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 7px;
}

.stat-card__icon-bubble {
  width: 22px;
  height: 22px;
  border-radius: 999px;
  background: rgba(59, 130, 246, 0.18);
  color: #93c5fd;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.stat-card__title {
  margin: 0;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.02em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.stat-card__value {
  font-size: 34px;
  line-height: 0.95;
  font-weight: 800;
  color: #f8fafc;
}

.stat-card__subtitle {
  margin: 0;
  color: #cbd5e1;
  font-size: 12px;
  font-weight: 500;
}

.stat-card__meta {
  display: grid;
  gap: 5px;
  margin-top: 2px;
}

.stat-card__sparkline {
  height: 24px;
  border-radius: 8px;
  background: rgba(15, 23, 42, 0.45);
  padding: 2px 4px;
}

.stat-card__sparkline svg {
  width: 100%;
  height: 100%;
}

.stat-card__sparkline path {
  fill: none;
  stroke: rgba(56, 189, 248, 0.9);
  stroke-width: 2;
  stroke-linecap: round;
}

.stat-card__trend {
  width: fit-content;
  border-radius: 999px;
  padding: 2px 7px;
  font-size: 10px;
  font-weight: 700;
  flex: 0 0 auto;
}

.stat-card__trend.is-up {
  color: #6ee7b7;
  background: rgba(16, 185, 129, 0.14);
}

.stat-card__trend.is-down {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.14);
}

.stat-card__trend.is-neutral {
  color: #cbd5e1;
  background: rgba(148, 163, 184, 0.16);
}

.stat-card__meta-text {
  margin: 0;
  color: #64748b;
  font-size: 11px;
  line-height: 1.3;
}

.stat-card.is-variant-success {
  border-color: rgba(16, 185, 129, 0.36);
}

.stat-card.is-variant-warning {
  border-color: rgba(245, 158, 11, 0.38);
}

.stat-card.is-variant-live {
  border-color: rgba(56, 189, 248, 0.44);
}

.stat-card.is-live {
  box-shadow: 0 0 0 1px rgba(34, 211, 238, 0.22), 0 12px 20px rgba(2, 6, 23, 0.3);
}
</style>
