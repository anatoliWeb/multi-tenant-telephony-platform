import { HttpClient } from '@angular/common/http';
import { Inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiClientService } from '../../api/services/api-client.service';
import { APP_CONFIG, AppEnvironment } from '../tokens/app-config.token';
import type { ApiResponse } from '../../api/models/api-response.model';
import type { ChatAttachment, ChatConversation, ChatDevice, ChatMessage, ChatParticipant } from '../../features/chat/models/chat.model';

type QueryParamValue = string | number | boolean | undefined | null;
type QueryParams = Record<string, QueryParamValue>;

@Injectable({ providedIn: 'root' })
export class ChatApiService {
  constructor(
    private readonly apiClient: ApiClientService,
    private readonly http: HttpClient,
    @Inject(APP_CONFIG) private readonly config: AppEnvironment,
  ) {}

  listConversations(params?: QueryParams) {
    return this.apiClient.get<ChatConversation[]>('/v1/chat/conversations', { params: this.compactParams(params) });
  }

  getConversation(conversationId: number) {
    return this.apiClient.get<ChatConversation>(`/v1/chat/conversations/${conversationId}`);
  }

  listConversationParticipants(conversationId: number) {
    return this.apiClient.get<ChatParticipant[]>(`/v1/chat/conversations/${conversationId}/participants`);
  }

  listMessages(conversationId: number, params?: QueryParams) {
    return this.apiClient.get<ChatMessage[]>(`/v1/chat/conversations/${conversationId}/messages`, {
      params: this.compactParams(params),
    });
  }

  searchMessages(conversationId: number, params?: QueryParams) {
    return this.apiClient.get<ChatMessage[]>(`/v1/chat/conversations/${conversationId}/messages/search`, {
      params: this.compactParams(params),
    });
  }

  sendMessage(conversationId: number, payload: { body: string; type?: 'text' | 'system' }) {
    return this.apiClient.post<ChatMessage, { body: string; type?: 'text' | 'system' }>(
      `/v1/chat/conversations/${conversationId}/messages`,
      payload,
    );
  }

  createCallStartedMessage(conversationId: number, payload: Record<string, unknown> = {}) {
    return this.apiClient.post<ChatMessage, Record<string, unknown>>(
      `/v1/chat/conversations/${conversationId}/call-started`,
      payload,
    );
  }

  editMessage(messageId: number, payload: { body: string }) {
    return this.apiClient.patch<ChatMessage, { body: string }>(`/v1/chat/messages/${messageId}`, payload);
  }

  deleteMessage(messageId: number) {
    return this.apiClient.delete<{ id: number; conversation_id: number; status: string }>(`/v1/chat/messages/${messageId}`);
  }

  createDirectConversation(payload: { user_id: number }) {
    return this.apiClient.post<ChatConversation, { user_id: number }>('/v1/chat/conversations/direct', payload);
  }

  createGroupConversation(payload: Record<string, unknown>) {
    return this.apiClient.post<ChatConversation, Record<string, unknown>>('/v1/chat/conversations/group', payload);
  }

  createPrivateGroupFromDirect(conversationId: number, payload: Record<string, unknown>) {
    return this.apiClient.post<ChatConversation, Record<string, unknown>>(
      `/v1/chat/conversations/${conversationId}/create-private-group`,
      payload,
    );
  }

  registerDevice(payload: ChatDevice) {
    return this.apiClient.post<Record<string, unknown>, ChatDevice>('/v1/chat/devices', payload);
  }

  markConversationRead(conversationId: number, payload: { device_key: string; until_message_id?: number }) {
    return this.apiClient.patch<Record<string, unknown>, { device_key: string; until_message_id?: number }>(
      `/v1/chat/conversations/${conversationId}/read`,
      payload,
    );
  }

  markMessageRead(messageId: number, payload: { device_key: string }) {
    return this.apiClient.patch<Record<string, unknown>, { device_key: string }>(`/v1/chat/messages/${messageId}/read`, payload);
  }

  startTyping(conversationId: number, payload?: { device_key?: string; device_type?: string }) {
    return this.apiClient.post<Record<string, unknown>, { device_key?: string; device_type?: string }>(
      `/v1/chat/conversations/${conversationId}/typing/start`,
      payload ?? {},
    );
  }

  stopTyping(conversationId: number, payload?: { device_key?: string; device_type?: string }) {
    return this.apiClient.post<Record<string, unknown>, { device_key?: string; device_type?: string }>(
      `/v1/chat/conversations/${conversationId}/typing/stop`,
      payload ?? {},
    );
  }

  leavePresence(conversationId: number, payload?: { device_key?: string }) {
    return this.apiClient.post<Record<string, unknown>, { device_key?: string }>(
      `/v1/chat/conversations/${conversationId}/presence/leave`,
      payload ?? {},
    );
  }

  uploadAttachment(messageId: number, file: File, extra?: Record<string, string>) {
    const formData = new FormData();
    formData.append('file', file);
    Object.entries(extra ?? {}).forEach(([key, value]) => {
      formData.append(key, value);
    });

    return this.http.post<ApiResponse<ChatAttachment>>(
      this.resolveUrl(`/v1/chat/messages/${messageId}/attachments`),
      formData,
    );
  }

  getAttachmentDownloadUrl(attachmentId: number): string {
    return this.resolveUrl(`/v1/chat/attachments/${attachmentId}/download`);
  }

  deleteAttachment(attachmentId: number) {
    return this.apiClient.delete<Record<string, unknown>>(`/v1/chat/attachments/${attachmentId}`);
  }

  async registerDeviceOnce(payload: ChatDevice): Promise<void> {
    await firstValueFrom(this.registerDevice(payload));
  }

  private compactParams(params?: QueryParams): Record<string, string | number | boolean> | undefined {
    if (!params) {
      return undefined;
    }

    const entries = Object.entries(params)
      .filter(([, value]) => value !== undefined && value !== null)
      .map(([key, value]) => [key, value as string | number | boolean]);

    return entries.length > 0 ? Object.fromEntries(entries) : undefined;
  }

  private resolveUrl(path: string): string {
    const normalizedBase = this.config.apiBaseUrl.replace(/\/+$/, '');
    const normalizedPath = path.startsWith('/') ? path : `/${path}`;
    return `${normalizedBase}${normalizedPath}`;
  }
}
