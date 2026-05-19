<?php

namespace RankSavvy\Core\Queue;

class QueueManager
{
    public function boot(): void
    {
        add_action('ranksavvy_process_job', [$this, 'process'], 10, 2);
    }

    public function push(string $jobClass, array $data = []): void
    {
        // Schedule the job to run in the background immediately
        wp_schedule_single_event(time(), 'ranksavvy_process_job', [$jobClass, $data]);
    }

    public function process(string $jobClass, array $data): void
    {
        if (class_exists($jobClass)) {
            $job = new $jobClass($data);
            if (method_exists($job, 'handle')) {
                $job->handle();
            }
        }
    }
}
