<?php

declare(strict_types=1);

// This is the index that will be used to bootstrap the actual app in /build.
$index = dirname(__DIR__).'/build/html/index.html';
if (file_exists($index)) {
    echo file_get_contents($index);

    exit(0);
}

echo '<!DOCTYPE html>
<head>
    <link href="assets/flowbite/tailwind.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-50 h-screen flex justify-center items-center">
    <div>
        <p class="text-2xl font-semibold pb-5">Looks like you still need to import your Strava statistics</p>

        <dl class="text-gray-900 divide-y divide-gray-200">
            <div class="flex flex-col pb-3">
                <dt class="mb-1 text-gray-500 md:text-lg">Import Strava data</dt>
                <dd class="font-semibold"><code>docker compose exec app bin/console app:strava:import-data</code></dd>
            </div>
            <div class="flex flex-col py-3">
                <dt class="mb-1 text-gray-500 md:text-lg">Build static HTML files</dt>
                <dd class="font-semibold"><code>docker compose exec app bin/console app:strava:build-files</code></dd>
            </div>
        </dl>
    </div>

</body>
</html>';
