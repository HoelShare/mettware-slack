<?php

declare(strict_types=1);

namespace MettwareSlack\Handler;

use GuzzleHttp\ClientInterface;
use MettwareSlack\Service\InvoiceService;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

class OpenInvoiceHandler extends AbstractMessageHandler
{
    private ClientInterface $client;
    private SystemConfigService $systemConfigService;
    private InvoiceService $invoiceService;
    private EntityRepositoryInterface $productRepository;

    public function __construct(
        ClientInterface $client,
        SystemConfigService $systemConfigService,
        InvoiceService $invoiceService,
        EntityRepositoryInterface $productRepository
    ) {
        $this->client = $client;
        $this->systemConfigService = $systemConfigService;
        $this->invoiceService = $invoiceService;
        $this->productRepository = $productRepository;
    }

    /**
     * @param OpenInvoiceMessage $message
     */
    public function handle($message): void
    {
        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$message->getLanguageId(), Defaults::LANGUAGE_SYSTEM]
        );

        $token = $this->systemConfigService->get('MettwareSlack.config.slackBotToken');
        $paypalMeLink = $this->systemConfigService->get('MettwareSlack.config.paypalMeLink');

        $orders = $this->invoiceService->getOpenInvoicesForSlackId($message->getSlackId(), $context);

        $receivers = [$message->getSlackId()];
        if ($message->getAdditionalContacts() !== null) {
            array_push($receivers, ...$message->getAdditionalContacts());
        }

        foreach ($receivers as $receiver) {
            $this->client->request(
                Request::METHOD_POST,
                'https://slack.com/api/chat.postMessage',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'json' => [
                        'channel' => $receiver,
                        "blocks" => $this->getBlocks($orders, $paypalMeLink, $context),
                    ],
                ]
            );
            sleep(1);
        }
    }

    private function getBlocks(EntitySearchResult $orders, string $paypalMeLink, Context $context): array
    {
        /** @var OrderEntity $firstOrder */
        $firstOrder = $orders->first();
        $billingAddress = $firstOrder->getBillingAddress();

        $markdown = sprintf('Hi %s', $billingAddress->getFirstName()) . PHP_EOL;

        $total = 0.0;

        /** @var OrderEntity $order */
        foreach ($orders->getIterator() as $order) {
            $total += $order->getAmountTotal();

            /** @var \DateTimeImmutable $orderCreationDate */
            $orderCreationDate = $order->getCreatedAt();
            $orderCreationDate = $orderCreationDate->setTimezone(new \DateTimeZone('Europe/Berlin'));

            foreach ($order->getLineItems()->getIterator() as $lineItem) {
                $name = $this->formatName($lineItem, $context);
                $markdown .= sprintf(
                    '> %s - %sx %s %1.2f%s%s',
                    $orderCreationDate->format('d.m.Y H:i'),
                    $lineItem->getQuantity(),
                    $name,
                    $lineItem->getTotalPrice(),
                    $order->getCurrency()->getSymbol(),
                    PHP_EOL,
                );
            }
        }

        return [
            [
                "type" => "header",
                "text" => [
                    "type" => "plain_text",
                    "text" => 'Monthly Invoice',
                    "emoji" => true
                ]
            ],
            [
                "type" => "divider"
            ],
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => $markdown,
                ],
                "accessory" => [
                    "type" => "button",
                    "text" => [
                        "type" => "plain_text",
                        "text" => "Pay",
                        "emoji" => true
                    ],
                    "value" => "paypal_me",
                    "url" => sprintf('%s/%s', $paypalMeLink, $total),
                    "action_id" => "button-action",
                ],
            ],
        ];
    }

    private function formatName(OrderLineItemEntity $lineItem, Context $context): string
    {
        $product = $lineItem->getProduct();
        if ($product === null) {
            return $lineItem->getLabel();
        }

        $name = $product->getTranslation('name');

        if ($name === null && $product->getParentId() !== null) {
            $name = $this->fetchProductName($product->getParentId(), $context);
        }

        if ($product->getOptions() === null) {
            return $name;
        }

        $index = 0;
        foreach ($product->getOptions() as $option) {
            if ($index === 0) {
                $name .= ' - ';
            }
            $name .= $option->getTranslation('name');

            if ($index + 1 < $product->getOptions()->count()) {
                $name .= ', ';
            }

            $index++;
        }

        return $name;
    }

    public static function getHandledMessages(): iterable
    {
        return [OpenInvoiceMessage::class];
    }

    private function fetchProductName(string $parentId, Context $context): string
    {
        $criteria = new Criteria([$parentId]);

        return $this->productRepository->search($criteria, $context)->get($parentId)->getTranslation('name');
    }
}
