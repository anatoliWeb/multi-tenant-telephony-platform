<?php

namespace App\Services\Translation;

use App\Models\SystemTranslation;

class TranslationInspectionService
{
    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        return [
            'total' => SystemTranslation::query()->count(),
            'active' => SystemTranslation::query()->where('is_active', true)->count(),
            'inactive' => SystemTranslation::query()->where('is_active', false)->count(),
            'translated' => SystemTranslation::query()->where('is_translated', true)->count(),
            'missing' => SystemTranslation::query()->where('is_translated', false)->count(),
            'auto_generated' => SystemTranslation::query()->where('is_auto_generated', true)->count(),
            'locales' => SystemTranslation::query()->distinct('locale')->count('locale'),
            'groups' => SystemTranslation::query()->distinct('group')->count('group'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function missing(int $limit = 50): array
    {
        return SystemTranslation::query()
            ->where('is_translated', false)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'locale', 'group', 'key', 'source', 'is_auto_generated', 'updated_at'])
            ->map(fn (SystemTranslation $row) => [
                'id' => $row->id,
                'locale' => $row->locale,
                'group' => $row->group,
                'key' => $row->key,
                'source' => $row->source,
                'auto_generated' => $row->is_auto_generated ? 'yes' : 'no',
                'updated_at' => optional($row->updated_at)?->toDateTimeString() ?? '-',
            ])
            ->all();
    }
}
