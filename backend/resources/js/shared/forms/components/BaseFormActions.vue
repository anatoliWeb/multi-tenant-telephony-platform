<template>
  <footer class="base-form-actions" :class="{ 'is-sticky': sticky }">
    <div class="base-form-actions__left">
      <slot name="left" />
    </div>

    <div class="base-form-actions__right">
      <button type="button" class="base-form-actions__btn" :disabled="loading || cancelDisabled" @click="emit('cancel')">
        {{ cancelLabel }}
      </button>
      <button type="submit" class="base-form-actions__btn base-form-actions__btn--primary" :disabled="loading || submitDisabled">
        {{ loading ? loadingLabel : submitLabel }}
      </button>
    </div>
  </footer>
</template>

<script setup lang="ts">
interface Props {
  loading?: boolean;
  sticky?: boolean;
  submitLabel?: string;
  cancelLabel?: string;
  loadingLabel?: string;
  submitDisabled?: boolean;
  cancelDisabled?: boolean;
}

withDefaults(defineProps<Props>(), {
  loading: false,
  sticky: false,
  submitLabel: 'Save',
  cancelLabel: 'Cancel',
  loadingLabel: 'Saving...',
  submitDisabled: false,
  cancelDisabled: false,
});

const emit = defineEmits<{
  cancel: [];
}>();
</script>

<style scoped>
.base-form-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:6px}
.base-form-actions.is-sticky{position:sticky;bottom:0;padding:10px 0 0;background:linear-gradient(180deg, rgba(15,23,42,0) 0%, rgba(15,23,42,.92) 34%, rgba(15,23,42,.97) 100%)}
.base-form-actions__left{display:flex;align-items:center;gap:8px}
.base-form-actions__right{display:flex;align-items:center;gap:8px}
.base-form-actions__btn{height:34px;padding:0 12px;border-radius:9px;border:1px solid rgba(71,85,105,.55);background:rgba(30,41,59,.82);color:#e2e8f0;font-size:12px}
.base-form-actions__btn--primary{border-color:rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe}
.base-form-actions__btn:disabled{opacity:.6;cursor:not-allowed}
</style>
