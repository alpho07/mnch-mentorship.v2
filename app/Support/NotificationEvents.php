<?php

namespace App\Support;

/**
 * Central catalog of every notification event the platform sends, keyed by a
 * stable dotted identifier used in user notification preferences and at each
 * send site. Adding a new event here automatically exposes it on the
 * "My Notifications" preferences page (enabled by default).
 */
class NotificationEvents
{
    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_DATABASE = 'database';

    public const CHANNEL_BROADCAST = 'broadcast';

    public const CHANNELS = [
        self::CHANNEL_MAIL,
        self::CHANNEL_DATABASE,
        self::CHANNEL_BROADCAST,
    ];

    public const MENTORSHIP_CLASS_STARTED = 'mentorship.class_started';

    public const MENTORSHIP_CLASS_COMPLETED = 'mentorship.class_completed';

    public const MENTORSHIP_MODULE_STARTED = 'mentorship.module_started';

    public const MENTORSHIP_STALL_REMINDER = 'mentorship.stall_reminder';

    public const EMONC_ACTIVITY_COMPLETED = 'emonc.activity_completed';

    public const EMONC_QUIZ_SUBMITTED = 'emonc.quiz_submitted';

    public const EMONC_VIDEO_SUBMITTED = 'emonc.video_submitted';

    public const EMONC_VIDEO_REVIEWED = 'emonc.video_reviewed';

    public const EMONC_MODULE_COMPLETED = 'emonc.module_completed';

    public const EMONC_MENTOR_APPROVED = 'emonc.mentor_approved';

    public const EMONC_CERTIFIED = 'emonc.certified';

    public const EMONC_FEEDBACK_WRITTEN = 'emonc.feedback_written';

    public const INDICATOR_REPORT_SUBMITTED = 'indicator.report_submitted';

    public const INDICATOR_REPORT_VALIDATED = 'indicator.report_validated';

    public const INDICATOR_REPORT_REJECTED = 'indicator.report_rejected';

    public const STOCK_REQUEST_RECEIVED = 'inventory.stock_request_received';

    public const STOCK_REQUEST_APPROVED = 'inventory.stock_request_approved';

    public const STOCK_REQUEST_DISPATCHED = 'inventory.stock_request_dispatched';

    public const STOCK_REQUEST_RECEIVED_CONFIRMATION = 'inventory.stock_request_received_confirmation';

    public const STOCK_REQUEST_REJECTED = 'inventory.stock_request_rejected';

    public const STOCK_STATUS_UPDATE = 'inventory.stock_status_update';

    public const STOCK_OVERDUE_ALERT = 'inventory.stock_overdue_alert';

    public const STOCK_VERY_OVERDUE_ALERT = 'inventory.stock_very_overdue_alert';

    public const STOCK_LEVEL_ALERT = 'inventory.stock_level_alert';

    public const STOCK_BULK_RESULT = 'inventory.bulk_processing_result';

    /**
     * @var array<string, array<string, array{label: string, description: string}>>
     */
    public const GROUPS = [
        'Mentorship & EmONC Learning' => [
            self::MENTORSHIP_CLASS_STARTED => [
                'label' => 'Class started',
                'description' => 'A mentorship class you are enrolled in has started.',
            ],
            self::MENTORSHIP_CLASS_COMPLETED => [
                'label' => 'Class completed',
                'description' => 'A mentorship class you are enrolled in has ended.',
            ],
            self::MENTORSHIP_MODULE_STARTED => [
                'label' => 'Module started',
                'description' => 'A new module has begun in one of your classes.',
            ],
            self::MENTORSHIP_STALL_REMINDER => [
                'label' => 'Stalled mentorship reminder',
                'description' => 'Periodic nudges about your mentorships that have not started yet.',
            ],
            self::EMONC_ACTIVITY_COMPLETED => [
                'label' => 'Activity completed',
                'description' => 'Confirmation when all activities of a module are marked complete for you.',
            ],
            self::EMONC_QUIZ_SUBMITTED => [
                'label' => 'Quiz submitted (mentors)',
                'description' => 'A mentee submitted a pre-test or post-test.',
            ],
            self::EMONC_VIDEO_SUBMITTED => [
                'label' => 'Video submitted (mentors)',
                'description' => 'A mentee submitted a hands-on video for review.',
            ],
            self::EMONC_VIDEO_REVIEWED => [
                'label' => 'Video reviewed',
                'description' => 'Your submitted hands-on video has been reviewed.',
            ],
            self::EMONC_MODULE_COMPLETED => [
                'label' => 'Module completed',
                'description' => 'You completed a module. Congratulations!',
            ],
            self::EMONC_MENTOR_APPROVED => [
                'label' => 'Mentor approval / certification queue',
                'description' => 'Mentor approvals and pending Head DRMH certifications.',
            ],
            self::EMONC_CERTIFIED => [
                'label' => 'Certified',
                'description' => 'Your certificate has been issued and is ready for download.',
            ],
            self::EMONC_FEEDBACK_WRITTEN => [
                'label' => 'Mentor feedback',
                'description' => 'Your mentor wrote feedback on your progress.',
            ],
        ],
        'Indicators & Reports' => [
            self::INDICATOR_REPORT_SUBMITTED => [
                'label' => 'Report submitted (validators)',
                'description' => 'An indicator report was submitted and awaits your validation.',
            ],
            self::INDICATOR_REPORT_VALIDATED => [
                'label' => 'Report validated',
                'description' => 'One of your facility reports passed validation.',
            ],
            self::INDICATOR_REPORT_REJECTED => [
                'label' => 'Report rejected',
                'description' => 'One of your facility reports was rejected.',
            ],
        ],
        'Inventory & Commodities' => [
            self::STOCK_REQUEST_RECEIVED => [
                'label' => 'New stock request received',
                'description' => 'A facility submitted a stock request that needs your action.',
            ],
            self::STOCK_REQUEST_APPROVED => [
                'label' => 'Stock request approved',
                'description' => 'A stock request you track was approved.',
            ],
            self::STOCK_REQUEST_DISPATCHED => [
                'label' => 'Stock request dispatched',
                'description' => 'Approved items were dispatched from the central store.',
            ],
            self::STOCK_REQUEST_RECEIVED_CONFIRMATION => [
                'label' => 'Stock delivery received',
                'description' => 'The receiving facility confirmed delivery of dispatched items.',
            ],
            self::STOCK_REQUEST_REJECTED => [
                'label' => 'Stock request rejected',
                'description' => 'A stock request was rejected.',
            ],
            self::STOCK_STATUS_UPDATE => [
                'label' => 'Stock status updates',
                'description' => 'General status changes on stock requests you follow.',
            ],
            self::STOCK_OVERDUE_ALERT => [
                'label' => 'Overdue request alert',
                'description' => 'A stock request has been open longer than expected.',
            ],
            self::STOCK_VERY_OVERDUE_ALERT => [
                'label' => 'Very overdue request alert',
                'description' => 'Escalated alerts for long-overdue stock requests.',
            ],
            self::STOCK_LEVEL_ALERT => [
                'label' => 'Stock level alerts',
                'description' => 'Low-stock, critical or out-of-stock alerts.',
            ],
            self::STOCK_BULK_RESULT => [
                'label' => 'Bulk processing results',
                'description' => 'Outcome summaries after bulk stock-request processing.',
            ],
        ],
    ];

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function all(): array
    {
        return collect(self::GROUPS)->flatMap(fn (array $events) => $events)->all();
    }

    public static function isValidEvent(string $event): bool
    {
        return array_key_exists($event, self::all());
    }
}
