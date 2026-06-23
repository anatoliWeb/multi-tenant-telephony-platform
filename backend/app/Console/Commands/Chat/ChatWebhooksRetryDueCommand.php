<?php

namespace App\Console\Commands\Chat;

use App\Console\Commands\BaseCommand;
use App\Models\ChatWebhookDelivery;
use App\Services\Chat\ChatWebhookDeliveryService;

class ChatWebhooksRetryDueCommand extends BaseCommand
{
    protected $signature = 'chat:webhooks:retry-due {--limit=100}';

    protected $description = 'Dispatch delivery jobs for due pending/retrying chat webhooks.';

    public function __construct(
        protected ChatWebhookDeliveryService $deliveryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);

        $deliveries = ChatWebhookDelivery::query()
            ->whereIn('status', ['pending', 'retrying'])
            ->where(function ($q): void {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $dispatched = 0;
        foreach ($deliveries as $delivery) {
            $this->deliveryService->dispatchDelivery($delivery);
            $dispatched++;
        }

        $this->renderSummary([
            'limit' => $limit,
            'dispatched' => $dispatched,
        ], 'Chat Webhooks Retry Due');

        return self::SUCCESS;
    }
}

