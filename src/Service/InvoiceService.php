<?php declare(strict_types=1);

namespace MettwareSlack\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class InvoiceService
{
    private EntityRepositoryInterface $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    private function getOpenOrdersCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('currency');

        $criteria->addFilter(new NotFilter('AND', [new EqualsFilter('order.transactions.stateMachineState.technicalName', 'paid')]));
        $criteria->addFilter(new RangeFilter('order.amountTotal', [RangeFilter::GT => 0]));
        return $criteria;
    }

    private function getOpenOrdersForSlackIdCriteria(string $slackId): Criteria
    {
        $criteria = $this->getOpenOrdersCriteria();
        $criteria->addFilter(new EqualsFilter('billingAddress.additionalAddressLine1', $slackId));

        return $criteria;
    }

    public function getSlackIdsForOpenInvoices(Context $context): array
    {
        $orders = $this->fetchOrders($this->getOpenOrdersCriteria(), $context);
        $slackIds = [];
        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            $slackIds[$order->getBillingAddress()->getAdditionalAddressLine1()] = true;
        }

        return array_keys($slackIds);
    }

    public function getOpenInvoicesForSlackId(string $slackId, Context $context): EntitySearchResult
    {
        return $this->fetchOrders($this->getOpenOrdersForSlackIdCriteria($slackId), $context);
    }

    private function fetchOrders(Criteria $criteria, Context $context): EntitySearchResult
    {
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('transactions');

        return $this->orderRepository->search($criteria, $context);
    }
}
