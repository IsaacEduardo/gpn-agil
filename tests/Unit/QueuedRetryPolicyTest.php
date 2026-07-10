<?php

namespace Tests\Unit;

use App\Notifications\Concerns\QueuedRetryPolicy;
use PHPUnit\Framework\TestCase;

class QueuedRetryPolicyTest extends TestCase
{
    public function test_define_tentativas_e_backoff(): void
    {
        $notification = new class
        {
            use QueuedRetryPolicy;
        };

        $this->assertSame(3, $notification->tries);
        $this->assertSame([30, 120, 300], $notification->backoff());
    }
}
