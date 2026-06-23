import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BaseRealtimeStatus from './BaseRealtimeStatus.vue';

describe('BaseRealtimeStatus', () => {
  it('renders WS 1 for connected state', () => {
    const wrapper = mount(BaseRealtimeStatus, {
      props: {
        label: 'WS',
        count: 1,
        active: true,
        title: 'WS: WebSocket connection state',
      },
    });

    expect(wrapper.text()).toContain('WS');
    expect(wrapper.text()).toContain('1');
    expect(wrapper.attributes('data-active')).toBe('true');
  });

  it('renders EV/ON/PG counters and title safely', () => {
    const wrapper = mount(BaseRealtimeStatus, {
      props: {
        label: 'EV',
        count: 7,
        active: true,
        title: 'EV: Realtime events received',
      },
    });

    expect(wrapper.text()).toContain('EV');
    expect(wrapper.text()).toContain('7');
    expect(wrapper.attributes('title')).toContain('Realtime events');
  });

  it('reactively updates displayed metric value when prop changes', async () => {
    const wrapper = mount(BaseRealtimeStatus, {
      props: {
        label: 'PG',
        count: 0,
        active: false,
        title: 'PG: Joined presence groups/channels',
      },
    });

    await wrapper.setProps({ count: 2, active: true });

    expect(wrapper.text()).toContain('2');
    expect(wrapper.attributes('data-active')).toBe('true');
  });
});
