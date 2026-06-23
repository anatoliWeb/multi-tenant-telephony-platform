<template>
  <div class="space-y-1.5">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">{{ label }}</label>
    <select
      :id="id"
      :value="modelValue"
      :disabled="disabled"
      class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 disabled:cursor-not-allowed disabled:bg-slate-100"
      @change="onChange"
    >
      <option v-if="placeholder" value="">{{ placeholder }}</option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
  </div>
</template>

<script setup lang="ts">
export interface BaseSelectOption {
  value: string;
  label: string;
}

interface Props {
  modelValue: string;
  options: BaseSelectOption[];
  id?: string;
  label?: string;
  placeholder?: string;
  disabled?: boolean;
}

withDefaults(defineProps<Props>(), {
  id: undefined,
  label: undefined,
  placeholder: '',
  disabled: false,
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const onChange = (event: Event): void => {
  emit('update:modelValue', (event.target as HTMLSelectElement).value);
};
</script>

