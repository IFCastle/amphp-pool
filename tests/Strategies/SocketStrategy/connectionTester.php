<?php

declare(strict_types=1);

use Amp\Sync\Channel;

return function (Channel $channel): void {

    $address                        = $channel->receive(new \Amp\TimeoutCancellation(15));
    $result                         = @file_get_contents($address);

    // Retry if first attempt failed (server might still be starting up)
    if ($result === false) {
        sleep(2);
        $result                     = @file_get_contents($address);
    }

    if ($result === false) {
        $lastError = error_get_last();
        $errorMsg = $lastError ? $lastError['message'] : 'Unknown error';
        $channel->send('Failed to get content: ' . $errorMsg);
        return;
    }

    $channel->send($result);
};
