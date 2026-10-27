<?php

declare(strict_types=1);

header_remove('X-Powered-By'); // expose_php n'est pas réglable sur OVH

require __DIR__ . '/../vendor/autoload.php';

(require __DIR__ . '/../src/app.php')->run();
