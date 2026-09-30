<?php

declare(strict_types=1);

return [
    'preset' => 'laravel',
    // ponytail: fijo porque la autodetección usa `wmic`, que Windows 11 ya no trae
    'threads' => 4,
];
