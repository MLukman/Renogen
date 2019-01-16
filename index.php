<?php

if (($tz = getenv('PHP_TIMEZONE')) && in_array($tz, timezone_identifiers_list())) {
    date_default_timezone_set($tz);
}

include_once __DIR__.'/vendor/autoload.php';

Renogen\Application::execute(true);

