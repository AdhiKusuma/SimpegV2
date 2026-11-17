<?php

return [
    // Keep production cleanup opt-in. Local/testing keeps the feature available
    // for development databases without requiring a deployment setting.
    'allow_production_history_cleanup' => (bool) env('PAYROLL_PRODUCTION_HISTORY_CLEANUP', false),
];
