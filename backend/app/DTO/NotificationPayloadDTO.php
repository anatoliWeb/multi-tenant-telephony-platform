<?php

namespace App\DTO;

/**
 * Notification payload transfer object.
 *
 * WHY:
 * NotificationService returns a stable payload consumed by multiple
 * API endpoints. DTO keeps the shape explicit and reusable.
 */
class NotificationPayloadDTO
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $title,
        public readonly ?string $message,
        public readonly array $data,
        public readonly bool $isRead,
        public readonly ?string $readAt,
        public readonly ?string $createdAt,
    ) {
    }

    /**
     * @return array{
     *   id:string,
     *   type:string,
     *   title:?string,
     *   message:?string,
     *   data:array<string, mixed>,
     *   is_read:bool,
     *   read_at:?string,
     *   created_at:?string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'is_read' => $this->isRead,
            'read_at' => $this->readAt,
            'created_at' => $this->createdAt,
        ];
    }
}

