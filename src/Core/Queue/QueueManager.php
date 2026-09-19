<?php

namespace AmEveryWhere\Core\Queue;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Production-grade background job queue.
 *
 * Uses Action Scheduler when available, with an automatic fallback to native
 * wp_schedule_single_event for sites without Action Scheduler.
 */
class QueueManager
{
    private const ACTION_HOOK = 'ameverywhere_process_job';

    public function boot(): void
    {
        add_action(self::ACTION_HOOK, [$this, 'process'], 10, 2);
    }

    /**
     * Push a job onto the background queue.
     */
    public function push(string $jobClass, array $data = [], int $delay = 0): void
    {
        $args = [$jobClass, $data];

        if ($this->hasActionScheduler()) {
            if ($delay > 0) {
                as_schedule_single_action(time() + $delay, self::ACTION_HOOK, $args);
            } else {
                as_enqueue_async_action(self::ACTION_HOOK, $args);
            }
        } else {
            wp_schedule_single_event(time() + $delay, self::ACTION_HOOK, $args);
        }
    }

    /**
     * Schedule a recurring job.
     */
    public function pushRecurring(string $jobClass, array $data = [], int $intervalSeconds = 3600, string $uniqueGroup = ''): void
    {
        $args = [$jobClass, $data];

        if ($this->hasActionScheduler()) {
            if (!empty($uniqueGroup)) {
                $existing = as_get_scheduled_actions([
                    'hook'   => self::ACTION_HOOK,
                    'args'   => $args,
                    'group'  => $uniqueGroup,
                    'status' => \ActionScheduler_Store::STATUS_PENDING,
                ]);
                if (!empty($existing)) {
                    return;
                }
            }

            as_schedule_recurring_action(
                time(),
                $intervalSeconds,
                self::ACTION_HOOK,
                $args,
                $uniqueGroup ?: ''
            );
        } else {
            $cronHook = self::ACTION_HOOK . '_recurring_' . md5($jobClass);
            if (!wp_next_scheduled($cronHook, $args)) {
                wp_schedule_event(time(), 'hourly', $cronHook, $args);
            }
            add_action($cronHook, [$this, 'process'], 10, 2);
        }
    }

    /**
     * Process a queued job by instantiating its class and calling handle().
     */
    public function process(string $jobClass, array $data): void
    {
        if (!class_exists($jobClass)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AmEveryWhere Queue] Job class not found: {$jobClass}");
            }
            return;
        }

        try {
            $job = new $jobClass($data);
            if (method_exists($job, 'handle')) {
                $job->handle();
            }
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[AmEveryWhere Queue] Job failed ({$jobClass}): " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Check whether Action Scheduler is available.
     */
    private function hasActionScheduler(): bool
    {
        return function_exists('as_enqueue_async_action');
    }

    /**
     * Cleanup on plugin deactivation: unschedule pending cron fallback events.
     */
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::ACTION_HOOK);
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::ACTION_HOOK);
        }
    }
}
