<?php
declare(strict_types=1);

namespace Yireo\GoogleTagManager2HyvaCheckout\MageWire;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magewirephp\Magewire\Attributes\On;
use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\Features\SupportMagewireBackwardsCompatibility\HandleBackwardsCompatibility;
use Yireo\GoogleTagManager2\DataLayer\Event\AddPaymentInfo;
use Yireo\GoogleTagManager2\DataLayer\Event\AddShippingInfo;

/**
 * Native Magewire 3 component: runs without the Hyvä Checkout v1bc layer.
 */
#[HandleBackwardsCompatibility(enabled: false)]
class Checkout extends Component
{
    private CheckoutSession $checkoutSession;
    private AddShippingInfo $addShippingInfo;
    private AddPaymentInfo $addPaymentInfo;

    public function __construct(
        CheckoutSession $checkoutSession,
        AddShippingInfo $addShippingInfo,
        AddPaymentInfo $addPaymentInfo
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->addShippingInfo = $addShippingInfo;
        $this->addPaymentInfo = $addPaymentInfo;
    }

    /**
     * Hyvä Checkout passes the selection as named arguments (method, carrier, code);
     * the data layer reads it from the quote instead, so the payload is unused.
     */
    #[On('shipping_method_selected')]
    public function triggerShippingMethod(mixed ...$payload): void
    {
        $this->pushEvent($this->addShippingInfo->get());
    }

    #[On('payment_method_selected')]
    public function triggerPaymentMethod(mixed ...$payload): void
    {
        $quote = $this->checkoutSession->getQuote();
        $this->addPaymentInfo->setCartId((int) $quote->getId());
        $this->addPaymentInfo->setPaymentMethod((string) $quote->getPayment()->getMethod());
        $this->pushEvent($this->addPaymentInfo->get());
    }

    /**
     * Passed as one positional param: spreading it would bind its "event" key to
     * dispatch()'s own $event argument. The browser receives it as detail[0].
     */
    private function pushEvent(array $event): void
    {
        $this->skipRender();
        if ($event !== []) {
            $this->dispatch('ga:trigger-event', $event);
        }
    }
}
