<?php

return [
    // Recovery processing fee (XAF) charged before revealing the pickup location.
    'fee' => (int) env('DOCUTRACK_FEE', 1000),
    'payment_methods' => ['MTN Mobile Money', 'Orange Money', 'Credit/Debit Card'],
    'feedback_statuses' => ['Pending', 'In Progress', 'Resolved'],
];
