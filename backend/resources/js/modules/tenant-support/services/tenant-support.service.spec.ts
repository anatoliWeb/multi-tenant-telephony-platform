import { beforeEach, describe, expect, it, vi } from 'vitest';

const getMock = vi.fn();

vi.mock('../../../services/api/client', () => ({
  api: {
    get: getMock,
  },
}));

describe('tenantSupportService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('requests tenant-scoped support URLs for telephony data', async () => {
    getMock.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } });
    const { tenantSupportService } = await import('./tenant-support.service');

    await tenantSupportService.listContacts();
    await tenantSupportService.listExtensions();
    await tenantSupportService.listRingGroups();
    await tenantSupportService.listCallQueues();
    await tenantSupportService.listIvrMenus();
    await tenantSupportService.listPhoneNumbers();
    await tenantSupportService.listCallLogs();
    await tenantSupportService.getCallLogStatistics();

    expect(getMock).toHaveBeenCalledWith('/v1/contacts', { params: { per_page: 20 } });
    expect(getMock).toHaveBeenCalledWith('/v1/extensions', { params: { per_page: 20 } });
    expect(getMock).toHaveBeenCalledWith('/v1/ring-groups', { params: { per_page: 20 } });
    expect(getMock).toHaveBeenCalledWith('/v1/call-queues', { params: { per_page: 20 } });
    expect(getMock).toHaveBeenCalledWith('/v1/ivr-menus', { params: { per_page: 20 } });
    expect(getMock).toHaveBeenCalledWith('/v1/phone-numbers', { params: { per_page: 20 } });
    expect(getMock).toHaveBeenCalledWith('/v1/call-logs', { params: { per_page: 20 } });
    expect(getMock).toHaveBeenCalledWith('/v1/call-logs/statistics');
  });
});
