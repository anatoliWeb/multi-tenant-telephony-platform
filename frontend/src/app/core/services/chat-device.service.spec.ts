import { ChatDeviceService } from './chat-device.service';

describe('ChatDeviceService', () => {
  let service: ChatDeviceService;

  beforeEach(() => {
    localStorage.clear();
    service = new ChatDeviceService();
  });

  it('returns stable device key between calls', () => {
    const key1 = service.getDeviceKey();
    const key2 = service.getDeviceKey();
    expect(key1).toBe(key2);
  });

  it('device key does not include sensitive data markers', () => {
    const key = service.getDeviceKey().toLowerCase();
    expect(key).not.toContain('@');
    expect(key).not.toContain('token');
    expect(key).not.toContain('secret');
    expect(key).not.toContain('user');
    expect(key).toMatch(/^chatdev_[a-f0-9]+$/);
  });
});

