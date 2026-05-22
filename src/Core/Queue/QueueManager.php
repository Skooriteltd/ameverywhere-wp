<?php

namespace RankSavvy\Core\Queue;

/**
 * Production-grade background job queue.
 *
 * Uses Action Scheduler (the WooCommerce-proven engine) when available,
 * with an automatic fallback to native wp_schedule_single_event for sites
 * that don't have Action Scheduler installed.
 *
 * Action Scheduler benefits:
 *   - Scalable to thousands of concurrent tasks
 *   - Guaranteed execution with retry logic
 *   - Built-in admin UI for monitoring failed jobs
 *   - Database-backed (no cron dependency for triggering)
 */
class QueueManager
{
    private const ACTION_HOOK = 'ranksavvy_process_job';

    public function boot(): void
    {
        add_action(self::ACTION_HOOK, [$this, 'process'], 10, 2);
    }

    /**
     * Push a job onto the background queue.
     *
     * @param string $jobClass  Fully qualified class name of the job.
     * @param array  $data      Arbitrary data payload passed to the job's handle() method.
     * @param int    $delay     Optional delay in seconds before the job should run.
     */
    public function push(string $jobClass, array $data = [], int $delay = 0): void
    {
        $args = [$jobClass, $data];

        if ($this->hasActionScheduler()) {
            // Action Scheduler — robust, scalable, database-backed queue
            if ($delay > 0) {
                as_schedule_single_action(time() + $delay, self::ACTION_HOOK, $args);
            } else {
                as_enqueue_async_action(self::ACTION_HOOK, $args);
            }
        } else {
            // Fallback to native WP-Cron (unreliable but functional)
            wp_schedule_single_event(time() + $delay, self::ACTION_HOOK, $args);
        }
    }

    /**
     * Schedule a recurring job.
     *
     * @param string $jobClass       Fully qualified class name.
     * @param array  $data           Payload data.
     * @param int    $intervalSeconds Interval between runs.
     * @param string $uniqueGroup    Optional group name to prevent duplicate schedules.
     */
    public function pushRecurring(string $jobClass, array $data = [], int $intervalSeconds = 3600, string $uniqueGroup = ''): void
    {
        $args = [$jobClass, $data];

        if ($this->hasActionScheduler()) {
            // Check for existing scheduled action to prevent duplicates
            if (!empty($uniqueGroup)) {
                $existing = as_get_scheduled_actions([
                    'hook'   => self::ACTION_HOOK,
                    'args'   => $args,
                    'group'  => $uniqueGroup,
                    'status' => \ActionScheduler_Store::STATUS_PENDING,
                ]);
                if (!empty($existing)) {
                    return; // Already scheduled
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
            // Fallback — WP-Cron recurring event
            $cronHook = self::ACTION_HOOK . '_recurring_' . md5($jobClass);
            if (!wp_next_scheduled($cronHook, $args)) {
                wp_schedule_event(time(), 'hourly', $cronHook, $args);
            }
            // Register the callback for the dynamic hook
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
                error_log("[RankSavvy Queue] Job class not found: {$jobClass}");
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
                error_log("[RankSavvy Queue] Job failed ({$jobClass}): " . $e->getMessage());
            }
            // Action Scheduler will automatically retry failed actions
            // For wp-cron fallback, we just log the error
            throw $e; // Re-throw so Action Scheduler marks it as failed
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
     * Cleanup on plugin deactivation: unschedule any pending cron fallback events.
     */
    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::ACTION_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::ACTION_HOOK);
        }
    }
}
