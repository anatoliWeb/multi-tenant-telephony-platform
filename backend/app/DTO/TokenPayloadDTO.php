<?php

namespace App\DTO;

/**
 * Token payload transfer object.
 *
 * WHY:
 * TokenService returns token payloads in multiple flows
 * (list and create). This DTO keeps that shape explicit
 * and reusable without changing API contracts.
 */
class TokenPayloadDTO
{
    /**
     * @param array<int, string> $abilities
     * @param array{id:int,name:string} $owner
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $abilities,
        public readonly mixed $createdAt,
        public readonly array $owner,
    ) {
    }

    /**
     * Convert DTO to API-safe array payload.
     *
     * @return array{
     *   id:int,
     *   name:string,
     *   abilities:array<int,string>,
     *   created_at:mixed,
     *   owner:array{id:int,name:string}
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abilities' => $this->abilities,
            'created_at' => $this->createdAt,
            'owner' => $this->owner,
        ];
    }
}

