<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Console\Command;

class DispatchCatalogNotificationCommand extends Command
{
    protected $signature = 'catalog:notify
        {title : Notification title}
        {body : Notification body}
        {--source=* : Filter by source slug}
        {--journal=* : Filter by journal compound id}
        {--year=* : Filter by preferred year}';

    protected $description = 'Dispatch a catalog notification to matching devices';

    public function handle(NotificationDispatchService $notifications): int
    {
        $result = $notifications->dispatch(
            title: (string) $this->argument('title'),
            body: (string) $this->argument('body'),
            sources: array_values(array_filter((array) $this->option('source'))),
            journals: array_values(array_filter((array) $this->option('journal'))),
            years: array_values(array_filter((array) $this->option('year'))),
        );

        if (! $result['configured']) {
            $this->warn('FCM is not configured. The notification was logged but not sent.');
        }

        $this->info(sprintf(
            'Matched devices: %d | Sent: %d | Failed: %d',
            $result['matchedDevices'],
            $result['sent'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
