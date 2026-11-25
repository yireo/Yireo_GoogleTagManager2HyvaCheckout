<?php
declare(strict_types=1);

namespace Yireo\GoogleTagManager2HyvaCheckout\MageWire;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magewirephp\Magewire\Attributes\On;
use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\Features\SupportMagewireBackwardsCompatibility\HandleBackwardsCompatibility;
use Yireo\GoogleTagManager2\DataLayer\Event\AddPaymentInfo;
use Yireo\GoogleTagManager2\DataLayer\Event\AddShippingInfo;

/**
 * Native Magewire 3 component: runs without the Hyvä Checkout v1bc layer.
 *
 * Its listeners can also be called directly from the browser, so they never trust the payload, only push for an
 * existing quote and skip an event that equals the previous push of the same event.
 */
#[HandleBackwardsCompatibility(enabled: false)]
class Checkout extends Component
{
    public const SESSION_KEY_PREFIX = 'yireo_gtm2_hyva_checkout_last_';
    private const EVENT_NAMES = ['add_shipping_info', 'add_payment_info'];

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly AddShippingInfo $addShippingInfo,
        private readonly AddPaymentInfo $addPaymentInfo
    ) {
    }

    /**
     * Every (re)load of the checkout starts with a clean slate, so a selection made before is pushed again.
     */
    public function mount(): void
    {
        foreach (self::EVENT_NAMES as $eventName) {
            $this->checkoutSession->unsetData(self::SESSION_KEY_PREFIX . $eventName);
        }
    }

    /**
     * Hyvä Checkout passes the selection as named arguments (method, carrier, code);
     * the data layer reads it from the quote instead, so the payload is unused.
     */
    #[On('shipping_method_selected')]
    public function triggerShippingMethod(mixed ...$payload): void
    {
        $this->skipRender();
        if ($this->getQuoteId() === 0) {
            return;
        }

        try {
            if ((int)$this->checkoutSession->getQuote()->getId() === 0) {
                return;
            }

            $this->pushEvent($this->addShippingInfo->get());
        } catch (LocalizedException $exception) {
            return;
        }
    }

    #[On('payment_method_selected')]
    public function triggerPaymentMethod(mixed ...$payload): void
    {
        $this->skipRender();
        if ($this->getQuoteId() === 0) {
            return;
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            $paymentMethod = (string)$quote->getPayment()->getMethod();
            if ((int)$quote->getId() === 0 || $paymentMethod === '') {
                return;
            }

            $this->addPaymentInfo->setCartId((int)$quote->getId());
            $this->addPaymentInfo->setPaymentMethod($paymentMethod);
            $this->pushEvent($this->addPaymentInfo->get());
        } catch (LocalizedException $exception) {
            return;
        }
    }

    private function getQuoteId(): int
    {
        return (int)$this->checkoutSession->getQuoteId();
    }

    /**
     * Passed as one positional param: spreading it would bind its "event" key to
     * dispatch()'s own $event argument. The browser receives it as detail[0].
     *
     * @param array<string, mixed> $event
     */
    private function pushEvent(array $event): void
    {
        if ($event === []) {
            return;
        }

        $sessionKey = self::SESSION_KEY_PREFIX . (string)($event['event'] ?? '');
        $hash = hash('sha256', (string)json_encode($event));
        if ($this->checkoutSession->getData($sessionKey) === $hash) {
            return;
        }

        $this->checkoutSession->setData($sessionKey, $hash);
        $this->dispatch('ga:trigger-event', $event);
    }
}
