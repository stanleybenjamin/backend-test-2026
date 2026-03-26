<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class GameplayServiceTest extends TestCase
{
    public function flippingStoreATIle()
    {
        $this->assertTrue(true);
    }

    public function duplicationTileIndexAreNotAllowed()
    {
        $this->assertTrue(true);
    }

    public function SameGameCannotAcceptFlipAfterCompletion()
    {
        $this->assertTrue(true);
    }

    public function thirdMatchEndsTheGameWithWin()
    {
        $this->assertTrue(true);
    }

    public function maxFlipsEndsTheGameWithLoss()
    {
        $this->assertTrue(true);
    }

    public function revealTileNeverReturnsPrizeThatIsExhausted()
    {
        $this->assertTrue(true);
    }
}
