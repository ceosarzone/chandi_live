<?php

return ['marketplace_enabled' => (bool) env('MARKETPLACE_ENABLED', false), 'trusted_proxies' => array_filter(explode(',', env('TRUSTED_PROXIES', '')))];
