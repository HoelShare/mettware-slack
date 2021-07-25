<?php declare(strict_types=1);

namespace MettwareSlack\Service;

use MettwareSlack\Handler\OpenInvoiceMessage;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

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
        $criteria->addAssociation('lineItems.product.options');
        $criteria->addAssociation('lineItems.product.parent');
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

    /**
     * @return OpenInvoiceMessage[]
     */
    public function getSlackIdsForOpenInvoices(Context $context): array
    {
        $orders = $this->fetchOrders($this->getOpenOrdersCriteria(), $context);
        $slackIds = [];
        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            $languageId = Defaults::LANGUAGE_SYSTEM;
            if ($order->getOrderCustomer() !== null && $order->getOrderCustomer()->getCustomer() !== null) {
                $languageId = $order->getOrderCustomer()->getCustomer()->getLanguageId();
            }
            $slackIds[$order->getBillingAddress()->getAdditionalAddressLine1()] = $languageId;
        }

        $messages = [];
        foreach ($slackIds as $slackId => $languageId) {
            $messages[] = new OpenInvoiceMessage($slackId, $languageId);
        }

        return $messages;
    }

    public function getOpenInvoicesForSlackId(string $slackId, Context $context): EntitySearchResult
    {
        return $this->fetchOrders($this->getOpenOrdersForSlackIdCriteria($slackId), $context);
    }

    private function fetchOrders(Criteria $criteria, Context $context): EntitySearchResult
    {
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('transactions');
        $criteria->addSorting(new FieldSorting('order.orderDateTime'));

        return $this->orderRepository->search($criteria, $context);
    }
}
