<?php

declare(strict_types=1);

// This is the index that will be used to bootstrap the actual app in /build.
$index = dirname(__DIR__).'/build/html/index.html';
if (file_exists($index)) {
    echo file_get_contents($index);

    exit(0);
}

echo '<!DOCTYPE html>
<body>
    <h1>Please run <strong>app:strava:import-data</strong></h1>
</body>
</html>';
