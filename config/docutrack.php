<?php

declare(strict_types=1);

return [
    /*
     | Secret partagé protégeant les endpoints internes déclenchés par cron.
     | Vide => les endpoints répondent 404 pour tout le monde (VerifyInternalSecret).
     */
    'internal_cron_secret' => env('INTERNAL_CRON_SECRET', ''),

    /*
     | Clé HMAC des numéros de document (D-007). Distincte d'APP_KEY, hors base.
     | Sa rotation impose de recalculer tous les number_hmac : OPEN_QUESTIONS Q-26.
     */
    'document_number_hmac_key' => env('DOCUMENT_NUMBER_HMAC_KEY', ''),

    /*
     | Le paiement est un module désactivable (D-015) : la licéité du modèle
     | payant n'est pas tranchée. Désactivé, le parcours devient
     | vérification -> N3, sans réécriture.
     */
    'payment' => [
        'enabled' => env('PAYMENT_ENABLED', false),
        'provider' => env('PAYMENT_PROVIDER', 'fake'),
        'amount_minor' => (int) env('PAYMENT_AMOUNT_MINOR', 100000),
        'currency' => env('PAYMENT_CURRENCY', 'XAF'),
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'fake'),
    ],

    /*
     | Bornes des tâches internes. Le budget doit rester nettement sous la
     | limite d'exécution de la plateforme — NON VALIDÉE sur Vercel (D-017).
     */
    'internal_tasks' => [
        'lock_seconds' => (int) env('INTERNAL_TASK_LOCK_SECONDS', 60),
        'budget_seconds' => (int) env('INTERNAL_TASK_BUDGET_SECONDS', 20),
    ],

    /*
     | Rétention (D-010). Purge EFFECTIVE par cron, pas suppression logique.
     | Durées à valider juridiquement (COMPLIANCE_OPEN_QUESTIONS Q-C4).
     */
    'retention' => [
        'declaration_days' => (int) env('RETENTION_DECLARATION_DAYS', 180),
        'report_days' => (int) env('RETENTION_REPORT_DAYS', 180),
        'attachment_days' => (int) env('RETENTION_ATTACHMENT_DAYS', 90),
        'audit_log_days' => (int) env('RETENTION_AUDIT_LOG_DAYS', 1095),
        'search_request_days' => (int) env('RETENTION_SEARCH_REQUEST_DAYS', 7),
    ],
];
