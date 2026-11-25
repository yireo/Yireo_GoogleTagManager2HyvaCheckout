<?php
declare(strict_types=1);

namespace Yireo\GoogleTagManager2HyvaCheckout\Test\Unit\MageWire;

use Yireo\GoogleTagManager2HyvaCheckout\MageWire\Checkout;

/**
 * Records dispatch() and skipRender() calls, which otherwise need the Magewire runtime
 */
class CheckoutDouble extends Checkout
{
    /**
     * @var array<int, array<int|string, mixed>>
     */
    public array $dispatched = [];
    public int $skipRenderCount = 0;

    /**
     * @param string $event
     * @param mixed ...$params
     * @return null
     */
    public function dispatch($event, ...$params)
    {
        $this->dispatched[] = [$event, ...$params];
        return null;
    }

    /**
     * @param mixed $html
     * @return void
     */
    public function skipRender($html = null): void
    {
        $this->skipRenderCount++;
    }
}
