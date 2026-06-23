<template>
  <teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50" @click="closeOnBackdrop && close()" />
      <div class="relative z-10 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-4">
          <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
          <button class="text-slate-500 hover:text-slate-700" type="button" @click="close">×</button>
        </div>
        <div class="text-sm text-slate-700">
          <slot />
        </div>
        <div v-if="$slots.footer" class="mt-6 flex justify-end gap-2">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup lang="ts">
interface Props {
  modelValue: boolean;
  title?: string;
  closeOnBackdrop?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Modal',
  closeOnBackdrop: true,
});

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
}>();

const close = (): void => {
  emit('update:modelValue', false);
};
</script>

