<template>
  <main class="login-page">
    <section class="login-card">
      <header class="login-card__header">
        <h1>{{ t('common.authPage.title') }}</h1>
        <p>{{ t('common.authPage.subtitle') }}</p>
      </header>

      <BaseForm :model="form.model" @submit="handleSubmit">
        <BaseFormField :label="t('common.authPage.emailLabel')" :required="true" :error="form.getFieldError('email')">
          <input
            type="email"
            autocomplete="email"
            :placeholder="t('common.authPage.emailPlaceholder')"
            :value="String(form.model.email)"
            @input="form.setField('email', ($event.target as HTMLInputElement).value)"
          />
        </BaseFormField>

        <BaseFormField :label="t('common.authPage.passwordLabel')" :required="true" :error="form.getFieldError('password')">
          <input
            type="password"
            autocomplete="current-password"
            :placeholder="t('common.authPage.passwordPlaceholder')"
            :value="String(form.model.password)"
            @input="form.setField('password', ($event.target as HTMLInputElement).value)"
          />
        </BaseFormField>

        <label class="login-remember">
          <input
            type="checkbox"
            :checked="Boolean(form.model.remember)"
            @change="form.setField('remember', ($event.target as HTMLInputElement).checked)"
          />
          <span>{{ t('common.authPage.rememberMe') }}</span>
        </label>

        <BaseFormActions
          :loading="asyncForm.isSubmitting.value"
          :submit-disabled="!canSubmit"
          :submit-label="t('common.authPage.signIn')"
          :loading-label="t('common.authPage.signingIn')"
          :cancel-disabled="true"
          :cancel-label="t('common.actions.cancel')"
          @cancel="noop"
        />
      </BaseForm>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import { BaseForm, BaseFormActions, BaseFormField, useAsyncForm, useForm } from '../../../shared/forms';
import { isNormalizedApiError } from '../../../services/api/interceptors';
import { useAuthStore } from '../../../stores/auth.store';
import { useToast } from '../../../shared/toast';

/**
 * Login view is intentionally built on shared form/async infrastructure so
 * auth flows follow the same UX contracts as CRUD workflows (validation, async
 * states, and toast feedback), keeping platform behavior predictable.
 */
const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const toast = useToast();
const { t } = useI18n({ useScope: 'global' });

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

const asyncForm = useAsyncForm();

const canSubmit = computed(() => {
  return !asyncForm.isSubmitting.value
    && String(form.model.email).trim().length > 0
    && String(form.model.password).trim().length > 0;
});

const resolveRedirectPath = (): string => {
  const redirect = route.query.redirect;
  if (typeof redirect === 'string' && redirect.startsWith('/')) {
    return redirect;
  }

  return '/dashboard';
};

const handleSubmit = async (): Promise<void> => {
  form.clearErrors();
  let submissionError: unknown = null;

  const result = await asyncForm.submit(async () => {
    try {
      await authStore.login({
        email: String(form.model.email).trim(),
        password: String(form.model.password),
        remember: Boolean(form.model.remember),
      });
      return true;
    } catch (error) {
      submissionError = error;
      throw error;
    }
  });

  if (!result) {
    if (isNormalizedApiError(submissionError)) {
      const normalizedErrors = submissionError.errors as Record<string, string[] | string> | null;
      if (normalizedErrors) {
        const preparedErrors = Object.fromEntries(
          Object.entries(normalizedErrors).map(([field, value]) => [field, Array.isArray(value) ? value : [String(value)]]),
        );
        form.setErrors(preparedErrors);
      }

      toast.error({
        title: t('common.authPage.signInFailed'),
        message: submissionError.message,
      });
      return;
    }

    toast.error({
      title: t('common.authPage.signInFailed'),
      message: t('common.authPage.unexpectedError'),
    });
    return;
  }

  toast.success({
    title: t('common.authPage.welcomeBack'),
    message: t('common.authPage.loginSuccess'),
  });

  await router.replace(resolveRedirectPath());
};

const noop = (): void => undefined;
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px;
  background:
    radial-gradient(circle at top right, rgba(59, 130, 246, 0.2), transparent 45%),
    radial-gradient(circle at bottom left, rgba(14, 116, 144, 0.18), transparent 45%),
    #020617;
}

.login-card {
  width: min(420px, 100%);
  border: 1px solid rgba(71, 85, 105, 0.55);
  border-radius: 16px;
  background: rgba(15, 23, 42, 0.9);
  box-shadow: 0 24px 54px rgba(2, 6, 23, 0.55);
  padding: 18px;
  display: grid;
  gap: 14px;
}

.login-card__header h1 {
  margin: 0;
  color: #f8fafc;
  font-size: 22px;
  font-weight: 700;
}

.login-card__header p {
  margin: 6px 0 0;
  color: #94a3b8;
  font-size: 13px;
}

.login-remember {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #cbd5e1;
  font-size: 12px;
}

.login-remember input[type='checkbox'] {
  width: 14px;
  height: 14px;
  accent-color: #3b82f6;
}
</style>
