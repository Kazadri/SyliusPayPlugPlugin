<?php

declare(strict_types=1);

namespace PayPlug\SyliusPayPlugPlugin\Provider;

use PayPlug\SyliusPayPlugPlugin\Gateway\PayPlugGatewayFactory;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\RefundPlugin\Provider\RefundPaymentMethodsProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SupportedRefundPaymentMethodsProviderDecorator implements RefundPaymentMethodsProviderInterface
{
    /** @var RefundPaymentMethodsProviderInterface */
    private $decorated;

    /** @var RequestStack */
    private $requestStack;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    public function __construct(
        RefundPaymentMethodsProviderInterface $decorated,
        RequestStack $requestStack,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->decorated = $decorated;
        $this->requestStack = $requestStack;
        $this->orderRepository = $orderRepository;
    }

    public function findForChannel(ChannelInterface $channel): array
    {
        $paymentMethods = $this->decorated->findForChannel($channel);
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || 'sylius_refund_order_refunds_list' !== $request->get('_route')) {
            return $paymentMethods;
        }

        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findOneByNumber($request->get('orderNumber'));

        if (!$order instanceof OrderInterface || $this->isPayPlugPayment($order)) {
            return $paymentMethods;
        }

        foreach ($paymentMethods as $key => $paymentMethod) {
            if (PayPlugGatewayFactory::FACTORY_NAME !== $paymentMethod->getCode()) {
                continue;
            }
            unset($paymentMethods[$key]);
        }

        return $paymentMethods;
    }

    private function isPayPlugPayment(OrderInterface $order): bool
    {
        $firstPayment = $order->getPayments()->first();
        if (!$firstPayment instanceof PaymentInterface) {
            return false;
        }

        $firstPaymentMethod = $firstPayment->getMethod();
        if (!$firstPaymentMethod instanceof PaymentMethodInterface) {
            return false;
        }

        if (PayPlugGatewayFactory::FACTORY_NAME !== $firstPaymentMethod->getCode()) {
            return false;
        }

        return true;
    }
}
