<?php

return ['web_url' => env('CONTENT_WEB_URL'), 'public_rate_limit' => max(30, (int) env('CONTENT_PUBLIC_RATE_LIMIT', 240))];
