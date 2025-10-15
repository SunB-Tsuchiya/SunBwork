<?php

return [
    // When accumulated characters since last summary exceed this, queue a summary
    'char_threshold' => env('AI_SUMMARY_CHAR_THRESHOLD', 20000),

    // Or when the number of new messages exceeds this, queue a summary
    'message_threshold' => env('AI_SUMMARY_MESSAGE_THRESHOLD', 200),

    // How many messages to include in a single summarization job at most
    'max_messages_per_job' => env('AI_SUMMARY_MAX_MESSAGES_PER_JOB', 500),

    // Cooldown minutes to avoid re-dispatching a summary for the same conversation
    'dispatch_cooldown_minutes' => env('AI_SUMMARY_DISPATCH_COOLDOWN', 10),
];
