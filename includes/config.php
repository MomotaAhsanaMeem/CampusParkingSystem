<?php
// Central configuration for DEMO_MODE toggle and mode constants.
// Flip DEMO_MODE to false to restore production mode before final submission.

if (!defined('DEMO_MODE')) {
    define('DEMO_MODE', true);
}

if (DEMO_MODE) {
    if (!defined('SLOT_UNIT_SECONDS'))    define('SLOT_UNIT_SECONDS', 30);
    if (!defined('DEMO_LATE_SECONDS'))    define('DEMO_LATE_SECONDS', 15);
    if (!defined('LOCK_DURATION_SECONDS')) define('LOCK_DURATION_SECONDS', 60);
    if (!defined('GRACE_SECONDS'))         define('GRACE_SECONDS', 0); // Grace time removed
} else {
    if (!defined('SLOT_UNIT_SECONDS'))    define('SLOT_UNIT_SECONDS', 3600);
    if (!defined('OPERATING_START'))      define('OPERATING_START', '08:00:00');
    if (!defined('OPERATING_END'))        define('OPERATING_END', '17:00:00');
    if (!defined('LOCK_DURATION_SECONDS')) define('LOCK_DURATION_SECONDS', 86400);
    if (!defined('GRACE_SECONDS'))         define('GRACE_SECONDS', 0); // Grace time removed
}
