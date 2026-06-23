<template>
  <div class="space-y-1.5">
    <label v-if="label" :for="id" class="text-sm font-medium text-slate-700">{{ label }}</label>
    <input
      :id="id"
      :value="modelValue"
      :type="type"
      :placeholder="placeholder"
      :disabled="disabled"
      class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-500 focus:ring-2 focus:ring-slate-200 disabled:cursor-not-allowed disabled:bg-slate-100"
      @input="onInput"
    />
    <p v-if="hint" class="text-xs text-slate-500">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
interface Props {
  modelValue: string;
  id?: string;
  label?: string;
  type?: string;
  placeholder?: string;
  hint?: string;
  disabled?: boolean;
}

withDefaults(defineProps<Props>(), {
  id: undefined,
  label: undefined,
  type: 'text',
  placeholder: '',
  hint: undefined,
  disabled: false,
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const onInput = (event: Event): void => {
  emit('update:modelValue', (event.target as HTMLInputElement).value);
};
</script>

