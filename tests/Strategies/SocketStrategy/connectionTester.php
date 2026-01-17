<?php

declare(strict_types=1);

use Amp\Sync\Channel;

return function (Channel $channel): void {

    $address                        = $channel->receive(new \Amp\TimeoutCancellation(20));
    $result                         = false;

    // Retry multiple times with delays (server might still be starting up)
    for ($attempt = 0; $attempt < 5; $attempt++) {
        if ($attempt > 0) {
            sleep(1);
        }

        $result = @file_get_contents($address);

        if ($result !== false) {
            break;
        }
    }

    if ($result === false) {
        $lastError = error_get_last();
        $errorMsg = $lastError ? $lastError['message'] : 'Unknown error';
        $channel->send('Failed to get content after 5 attempts: ' . $errorMsg);
        return;
    }

    $channel->send($result);
};
