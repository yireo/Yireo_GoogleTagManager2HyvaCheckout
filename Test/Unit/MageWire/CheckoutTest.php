<?php
declare(strict_types=1);

namespace Yireo\GoogleTagManager2HyvaCheckout\Test\Unit\MageWire;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Yireo\GoogleTagManager2\DataLayer\Event\AddPaymentInfo;
use Yireo\GoogleTagManager2\DataLayer\Event\AddShippingInfo;

class CheckoutTest extends TestCase
{
    private DataObject $sessionData;
    private CheckoutSession&MockObject $checkoutSession;
    private AddShippingInfo&MockObject $addShippingInfo;
    private AddPaymentInfo&MockObject $addPaymentInfo;

    protected function setUp(): void
    {
        $this->sessionData = new DataObject();
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuoteId', 'getQuote', 'getData', '__call'])
            ->getMock();
        $this->checkoutSession->method('getData')->willReturnCallback(
            fn (string $key = '') => $this->sessionData->getData($key)
        );
        $this->checkoutSession->method('__call')->willReturnCallback(
            fn (string $method, array $args) => $this->sessionData->$method(...$args)
        );

        $this->addShippingInfo = $this->createMock(AddShippingInfo::class);
        $this->addPaymentInfo = $this->createMock(AddPaymentInfo::class);
    }

    public function testNoQuoteDispatchesNothing(): void
    {
        $this->checkoutSession->method('getQuoteId')->willReturn(null);
        $this->checkoutSession->expects($this->never())->method('getQuote');
        $this->addShippingInfo->expects($this->never())->method('get');
        $this->addPaymentInfo->expects($this->never())->method('get');

        $component = $this->createComponent();
        $component->triggerShippingMethod();
        $component->triggerPaymentMethod(method: 'checkmo');

        $this->assertSame([], $component->dispatched);
        $this->assertSame(2, $component->skipRenderCount);
    }

    public function testMissingCartIsHandledSilently(): void
    {
        $this->givenQuote(42, 'checkmo');
        $this->addPaymentInfo->method('get')->willThrowException(new NoSuchEntityException());

        $component = $this->createComponent();
        $component->triggerPaymentMethod();

        $this->assertSame([], $component->dispatched);
    }

    public function testEmptyPaymentMethodDispatchesNothing(): void
    {
        $this->givenQuote(42, '');
        $this->addPaymentInfo->expects($this->never())->method('get');

        $component = $this->createComponent();
        $component->triggerPaymentMethod();

        $this->assertSame([], $component->dispatched);
    }

    public function testPaymentInfoIsReadFromSessionQuoteAndNotFromPayload(): void
    {
        $this->givenQuote(42, 'checkmo');
        $this->addPaymentInfo->expects($this->once())->method('setCartId')->with(42);
        $this->addPaymentInfo->expects($this->once())->method('setPaymentMethod')->with('checkmo');
        $this->addPaymentInfo->method('get')->willReturn($this->event('add_payment_info', 'checkmo'));

        $component = $this->createComponent();
        $component->triggerPaymentMethod(method: 'evil', cartId: 1);

        $this->assertCount(1, $component->dispatched);
        $this->assertSame('ga:trigger-event', $component->dispatched[0][0]);
        $this->assertSame($this->event('add_payment_info', 'checkmo'), $component->dispatched[0][1]);
    }

    public function testIdenticalEventIsDispatchedOnlyOnce(): void
    {
        $this->givenQuote(42, 'checkmo');
        $this->addPaymentInfo->method('get')->willReturn($this->event('add_payment_info', 'checkmo'));

        $component = $this->createComponent();
        $component->triggerPaymentMethod();
        $component->triggerPaymentMethod();
        $nextRequestComponent = $this->createComponent();
        $nextRequestComponent->triggerPaymentMethod();

        $this->assertCount(1, $component->dispatched);
        $this->assertSame([], $nextRequestComponent->dispatched);
    }

    public function testChangedEventIsDispatchedAgain(): void
    {
        $this->givenQuote(42, 'checkmo');
        $this->addShippingInfo->method('get')->willReturnOnConsecutiveCalls(
            $this->event('add_shipping_info', 'flatrate_flatrate'),
            $this->event('add_shipping_info', 'tablerate_bestway'),
            $this->event('add_shipping_info', 'flatrate_flatrate')
        );

        $component = $this->createComponent();
        $component->triggerShippingMethod();
        $component->triggerShippingMethod();
        $component->triggerShippingMethod();

        $this->assertCount(3, $component->dispatched);
    }

    public function testMountResetsPreviousPushes(): void
    {
        $this->givenQuote(42, 'checkmo');
        $this->addPaymentInfo->method('get')->willReturn($this->event('add_payment_info', 'checkmo'));

        $component = $this->createComponent();
        $component->triggerPaymentMethod();
        $component->mount();
        $component->triggerPaymentMethod();

        $this->assertCount(2, $component->dispatched);
    }

    public function testShippingLoadsSessionQuoteBeforeBuildingEvent(): void
    {
        $this->givenQuote(42, 'checkmo');
        $this->checkoutSession->expects($this->once())->method('getQuote');
        $this->addShippingInfo->method('get')->willReturn($this->event('add_shipping_info', 'flatrate_flatrate'));

        $component = $this->createComponent();
        $component->triggerShippingMethod();

        $this->assertCount(1, $component->dispatched);
    }

    public function testStaleQuoteIdDispatchesNothing(): void
    {
        $this->givenQuote(0, 'checkmo', 42);
        $this->addShippingInfo->expects($this->never())->method('get');
        $this->addPaymentInfo->expects($this->never())->method('get');

        $component = $this->createComponent();
        $component->triggerShippingMethod();
        $component->triggerPaymentMethod();

        $this->assertSame([], $component->dispatched);
    }

    public function testEmptyShippingEventDispatchesNothing(): void
    {
        $this->givenQuote(42, 'checkmo');
        $this->addShippingInfo->method('get')->willReturn([]);

        $component = $this->createComponent();
        $component->triggerShippingMethod();

        $this->assertSame([], $component->dispatched);
    }

    private function givenQuote(int $quoteId, string $paymentMethod, ?int $sessionQuoteId = null): void
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod'])
            ->getMock();
        $payment->method('getMethod')->willReturn($paymentMethod);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getPayment'])
            ->getMock();
        $quote->method('getId')->willReturn($quoteId);
        $quote->method('getPayment')->willReturn($payment);

        $this->checkoutSession->method('getQuoteId')->willReturn($sessionQuoteId ?? $quoteId);
        $this->checkoutSession->method('getQuote')->willReturn($quote);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $eventName, string $method): array
    {
        return ['event' => $eventName, 'ecommerce' => ['method' => $method]];
    }

    private function createComponent(): CheckoutDouble
    {
        return new CheckoutDouble($this->checkoutSession, $this->addShippingInfo, $this->addPaymentInfo);
    }
}
