<?php

declare(strict_types=1);

// This is the index that will be used to bootstrap the actual app in /build.
if (file_exists(__DIR__.'/index.html')) {
    file_get_contents(__DIR__.'/index.html');

    exit(0);
}

echo '<!DOCTYPE html>
<body>
    <h1>Please run <strong>app:strava:import-data</strong></h1>
</body>
</html>';
