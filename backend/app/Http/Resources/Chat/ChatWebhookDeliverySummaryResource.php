<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatWebhookDeliverySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event,
            'status' => $this->status,
            'attempts' => (int) $this->attempts,
            'max_attempts' => max((int) config('chat.webhooks.max_attempts', 5), 1),
            'next_retry_at' => $this->next_retry_at?->toISOString(),
            'last_status_code' => $this->response_status,
            'error_summary' => $this->error_message !== null ? mb_substr((string) $this->error_message, 0, 300) : null,
            'endpoint_name' => $this->endpoint?->name,
            'endpoint_url' => $this->endpoint?->url,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
        ];
    }
}
