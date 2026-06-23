import { Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ChatApiService } from './chat-api.service';
import type { ChatDevice } from '../../features/chat/models/chat.model';

@Injectable({ providedIn: 'root' })
export class ChatDeviceService {
  private readonly storageKey = 'chat_device_key_v1';
  private registered = false;

  getDeviceKey(): string {
    const existing = localStorage.getItem(this.storageKey);
    if (existing && existing.length > 0) {
      return existing;
    }

    const generated = this.generateDeviceKey();
    localStorage.setItem(this.storageKey, generated);
    return generated;
  }

  buildRegisterPayload(): ChatDevice {
    const nav = globalThis.navigator;
    const userAgent = nav?.userAgent ?? '';

    return {
      device_key: this.getDeviceKey(),
      device_name: 'Web Browser',
      device_type: 'browser',
      platform: nav?.platform ?? 'unknown',
      browser: this.detectBrowser(userAgent),
      app_version: 'web-1',
    };
  }

  async ensureRegistered(chatApi: ChatApiService): Promise<void> {
    if (this.registered) {
      return;
    }

    await firstValueFrom(chatApi.registerDevice(this.buildRegisterPayload()));
    this.registered = true;
  }

  private generateDeviceKey(): string {
    const random = this.secureRandomHex(16);
    return `chatdev_${random}`;
  }

  private secureRandomHex(bytes: number): string {
    const cryptoApi = globalThis.crypto;
    if (cryptoApi && typeof cryptoApi.getRandomValues === 'function') {
      const arr = new Uint8Array(bytes);
      cryptoApi.getRandomValues(arr);
      return Array.from(arr, (v) => v.toString(16).padStart(2, '0')).join('');
    }

    let fallback = '';
    for (let i = 0; i < bytes; i += 1) {
      fallback += Math.floor(Math.random() * 256).toString(16).padStart(2, '0');
    }
    return fallback;
  }

  private detectBrowser(userAgent: string): string {
    const ua = userAgent.toLowerCase();
    if (ua.includes('edg/')) return 'edge';
    if (ua.includes('chrome/')) return 'chrome';
    if (ua.includes('safari/') && !ua.includes('chrome/')) return 'safari';
    if (ua.includes('firefox/')) return 'firefox';
    return 'unknown';
  }
}
