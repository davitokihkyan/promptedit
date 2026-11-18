<?php

return [

    // Proxies allowed to set X-Forwarded-* headers (scheme, client IP).
    // Laravel Cloud: "*", since the app is only reachable through Cloud's edge network.
    // nginx talking to php-fpm directly: leave unset, so clients can't spoof these headers.
    'proxies' => env('TRUSTED_PROXIES'),

];
