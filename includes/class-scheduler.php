<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Scheduler Class
 * 
 * Manages the WP-Cron events for scheduling review generation.
 */
class AI_Review_Generator_Scheduler {

    const CRON_HOOK = 'ai_review_generator_cron';

    /**
     * Initialize scheduler hooks.
     */
    public function init() {
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action(self::CRON_HOOK, ['AI_Review_Generator_Cron_Handler', 'run_generation_event']);

        // Schedule or unschedule based on settings upon plugin activation or settings update
        // This is typically handled in the core class or on settings save.
    }

    /**
     * Schedule the review generation event.
     *
     * @param string $frequency The desired frequency (e.g., 'daily', 'twice_daily').
     */
    public static function schedule_event($frequency) {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), $frequency, self::CRON_HOOK);
        }
    }

    /**
     * Unschedule the review generation event.
     */
    public static function unschedule_event() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Reschedule the event, useful when settings change.
     *
     * @param string $new_frequency The new frequency to schedule.
     */
    public static function reschedule_event($new_frequency) {
        self::unschedule_event();
        if ($new_frequency !== 'disabled') {
            self::schedule_event($new_frequency);
        }
    }

    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing cron schedules.
     * @return array Modified cron schedules.
     */
    public function add_cron_schedules($schedules) {
        $schedules['twice_daily'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __('Twice Daily'),
        ];
        $schedules['four_times_daily'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __('Four Times Daily'),
        ];
        $schedules['hourly'] = [
            'interval' => HOUR_IN_SECONDS,
            'display'  => __('Once Hourly'),
        ];
        return $schedules;
    }
}
