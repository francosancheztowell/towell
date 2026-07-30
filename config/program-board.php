<?php

declare(strict_types=1);

return [
    'poll_seconds' => max(10, (int) env('PROGRAM_BOARD_POLL_SECONDS', 15)),
];
