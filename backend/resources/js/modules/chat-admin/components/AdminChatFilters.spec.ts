import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AdminChatFilters from './AdminChatFilters.vue';

const baseProps = {
  search: '',
  type: 'all',
  status: 'all',
  visibility: 'all',
  source: 'all',
  unreadOnly: false,
  assignment: 'all' as const,
  participantRestriction: 'all' as const,
  failedWebhookDeliveryOnly: false,
  importedOnly: false,
};

describe('AdminChatFilters', () => {
  it('emits unread/assignment/restriction/failed/imported filters', async () => {
    const wrapper = mount(AdminChatFilters, { props: baseProps });

    await wrapper.get('[data-testid="filter-unread-only"]').setValue(true);
    await wrapper.get('[data-testid="filter-failed-webhook"]').setValue(true);
    await wrapper.get('[data-testid="filter-imported-only"]').setValue(true);
    await wrapper.get('[data-testid="filter-assignment"]').setValue('assigned');
    await wrapper.get('[data-testid="filter-participant-restriction"]').setValue('blocked');

    expect(wrapper.emitted('update:unreadOnly')?.[0]).toEqual([true]);
    expect(wrapper.emitted('update:failedWebhookDeliveryOnly')?.[0]).toEqual([true]);
    expect(wrapper.emitted('update:importedOnly')?.[0]).toEqual([true]);
    expect(wrapper.emitted('update:assignment')?.[0]).toEqual(['assigned']);
    expect(wrapper.emitted('update:participantRestriction')?.[0]).toEqual(['blocked']);
  });

  it('emits reset', async () => {
    const wrapper = mount(AdminChatFilters, { props: baseProps });
    await wrapper.get('[data-testid="filter-reset"]').trigger('click');
    expect(wrapper.emitted('reset')).toBeTruthy();
  });
});
