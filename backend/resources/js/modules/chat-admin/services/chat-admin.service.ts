import { api } from '../../../services/api/client';
import type { ApiResponse, PaginationMeta } from '../../../types/response.types';
import type {
  ChatAdminConversation,
  ChatAdminMessage,
  ChatAdminParticipant,
  ChatAdminWebhookDeliverySummary,
} from '../types/chat-admin.types';

export interface ChatAdminListParams {
  search?: string;
  type?: string;
  status?: string;
  visibility?: string;
  source?: string;
  unread?: boolean;
  assignment?: 'all' | 'assigned' | 'unassigned';
  participant_restriction?: 'all' | 'blocked' | 'restricted';
  failed_webhook_delivery?: boolean;
  imported?: boolean;
  per_page?: number;
}

export interface ChatAdminMessagesParams {
  per_page?: number;
}

export interface ChatAdminWebhookDeliveriesParams {
  per_page?: number;
}

export interface ChatAdminBlockParticipantPayload {
  block_display_mode: 'hide_chat' | 'show_notice' | 'show_read_only_history';
  blocked_reason?: string | null;
}

export interface ChatAdminParticipantAccessPayload {
  access_state: 'full' | 'read_only' | 'hidden' | 'blocked';
  block_display_mode?: 'hide_chat' | 'show_notice' | 'show_read_only_history' | null;
}

export interface ChatAdminParticipantCapabilitiesPayload {
  can_invite?: boolean;
  can_remove?: boolean;
  can_send?: boolean;
  can_attach?: boolean;
  can_manage?: boolean;
  can_moderate?: boolean;
}

const normalizeMessagesPayload = (payload: unknown): ChatAdminMessage[] => {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload as ChatAdminMessage[];
  if (typeof payload === 'object' && payload !== null && Array.isArray((payload as { data?: unknown[] }).data)) {
    return ((payload as { data?: unknown[] }).data ?? []) as ChatAdminMessage[];
  }
  return [];
};

export const chatAdminService = {
  async listConversations(params: ChatAdminListParams = {}): Promise<{ items: ChatAdminConversation[]; meta?: PaginationMeta | Record<string, unknown> }> {
    const response = await api.get<ChatAdminConversation[] | { data?: ChatAdminConversation[] }>('/v1/chat/conversations', { params });
    const payload = response as ApiResponse<ChatAdminConversation[] | { data?: ChatAdminConversation[] }>;
    const items = Array.isArray(payload.data)
      ? payload.data
      : (payload.data?.data ?? []);

    return {
      items,
      meta: payload.meta,
    };
  },

  async getUnreadConversationsCount(): Promise<number> {
    const response = await this.listConversations({
      unread: true,
      per_page: 1,
    });
    const total = Number((response.meta as { total?: unknown } | undefined)?.total ?? 0);
    return Number.isFinite(total) ? total : 0;
  },

  async getConversation(conversationId: number): Promise<ChatAdminConversation | null> {
    const response = await api.get<ChatAdminConversation>(`/v1/chat/conversations/${conversationId}`);
    return response.data ?? null;
  },

  async listMessages(conversationId: number, params: ChatAdminMessagesParams = {}): Promise<ChatAdminMessage[]> {
    const response = await api.get<ChatAdminMessage[] | { data?: ChatAdminMessage[] }>(
      `/v1/chat/conversations/${conversationId}/messages`,
      { params },
    );

    return normalizeMessagesPayload(response.data);
  },

  async searchMessages(
    conversationId: number,
    params: { q?: string; type?: string; sender_id?: number; from?: string; to?: string } = {},
  ): Promise<ChatAdminMessage[]> {
    const response = await api.get<ChatAdminMessage[] | { data?: ChatAdminMessage[] }>(
      `/v1/chat/conversations/${conversationId}/messages/search`,
      { params },
    );

    return normalizeMessagesPayload(response.data);
  },

  async listParticipants(conversationId: number): Promise<ChatAdminParticipant[]> {
    const response = await api.get<ChatAdminParticipant[] | { data?: ChatAdminParticipant[] }>(
      `/v1/chat/conversations/${conversationId}/participants`,
    );

    if (Array.isArray(response.data)) {
      return response.data;
    }

    return response.data?.data ?? [];
  },

  async getConversationWebhookDeliveries(
    conversationId: number,
    params: ChatAdminWebhookDeliveriesParams = {},
  ): Promise<ChatAdminWebhookDeliverySummary[]> {
    const response = await api.get<ChatAdminWebhookDeliverySummary[] | { data?: ChatAdminWebhookDeliverySummary[] }>(
      `/v1/chat/conversations/${conversationId}/webhook-deliveries`,
      { params },
    );

    if (Array.isArray(response.data)) {
      return response.data;
    }

    return response.data?.data ?? [];
  },

  async sendMessage(conversationId: number, payload: { body: string; type?: 'text' }): Promise<ChatAdminMessage | null> {
    const response = await api.post<ChatAdminMessage, { body: string; type?: 'text' }>(
      `/v1/chat/conversations/${conversationId}/messages`,
      payload,
    );

    return response.data ?? null;
  },

  async deleteMessage(messageId: number): Promise<void> {
    await api.delete(`/v1/chat/messages/${messageId}`);
  },

  async closeConversation(conversationId: number): Promise<ChatAdminConversation | null> {
    const response = await api.patch<ChatAdminConversation>(`/v1/chat/conversations/${conversationId}/close`, {});
    return response.data ?? null;
  },

  async archiveConversation(conversationId: number): Promise<ChatAdminConversation | null> {
    const response = await api.patch<ChatAdminConversation>(`/v1/chat/conversations/${conversationId}/archive`, {});
    return response.data ?? null;
  },

  async blockParticipant(
    conversationId: number,
    participantUserId: number,
    payload: ChatAdminBlockParticipantPayload,
  ): Promise<ChatAdminParticipant | null> {
    const response = await api.patch<ChatAdminParticipant, ChatAdminBlockParticipantPayload>(
      `/v1/chat/conversations/${conversationId}/participants/${participantUserId}/block`,
      payload,
    );
    return response.data ?? null;
  },

  async unblockParticipant(conversationId: number, participantUserId: number): Promise<ChatAdminParticipant | null> {
    const response = await api.patch<ChatAdminParticipant>(
      `/v1/chat/conversations/${conversationId}/participants/${participantUserId}/unblock`,
      {},
    );
    return response.data ?? null;
  },

  async updateParticipantAccess(
    conversationId: number,
    participantUserId: number,
    payload: ChatAdminParticipantAccessPayload,
  ): Promise<ChatAdminParticipant | null> {
    const response = await api.patch<ChatAdminParticipant, ChatAdminParticipantAccessPayload>(
      `/v1/chat/conversations/${conversationId}/participants/${participantUserId}/access`,
      payload,
    );
    return response.data ?? null;
  },

  async updateParticipantCapabilities(
    conversationId: number,
    participantUserId: number,
    payload: ChatAdminParticipantCapabilitiesPayload,
  ): Promise<ChatAdminParticipant | null> {
    const response = await api.patch<ChatAdminParticipant, ChatAdminParticipantCapabilitiesPayload>(
      `/v1/chat/conversations/${conversationId}/participants/${participantUserId}/capabilities`,
      payload,
    );
    return response.data ?? null;
  },
};
