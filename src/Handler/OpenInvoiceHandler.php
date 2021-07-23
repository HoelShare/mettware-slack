<?php

declare(strict_types=1);

namespace MettwareSlack\Handler;

use GuzzleHttp\ClientInterface;
use MettwareSlack\Service\InvoiceService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

class OpenInvoiceHandler extends AbstractMessageHandler
{
    private ClientInterface $client;
    private SystemConfigService $systemConfigService;
    private InvoiceService $invoiceService;

    public function __construct(
        ClientInterface $client,
        SystemConfigService $systemConfigService,
        InvoiceService $invoiceService
    ) {
        $this->client = $client;
        $this->systemConfigService = $systemConfigService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * @param OpenInvoiceMessage $message
     */
    public function handle($message): void
    {
        $context = Context::createDefaultContext();
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
                        "blocks" => $this->getBlocks($orders, $paypalMeLink),
                    ],
                ]
            );
        }
    }

    private function getBlocks(EntitySearchResult $orders, string $paypalMeLink): array
    {
        /** @var OrderEntity $firstOrder */
        $firstOrder = $orders->first();
        $billingAddress = $firstOrder->getBillingAddress();

        $markdown = sprintf('Hi %s', $billingAddress->getFirstName()) . PHP_EOL;

        $total = 0.0;

        /** @var OrderEntity $order */
        foreach ($orders->getIterator() as $order) {
            $total += $order->getAmountTotal();

            foreach ($order->getLineItems()->getIterator() as $lineItem) {
                $name = $this->formatName($lineItem->getProduct());
                $markdown .= '>' . $order->getCreatedAt()->format('d.m.Y H:i') . ' - ' . $lineItem->getQuantity(
                    ) . 'x ' . $name . ' ' . $lineItem->getTotalPrice() . $order->getCurrency()->getSymbol() . PHP_EOL;
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

    private function formatName(ProductEntity $product): string
    {
        $name = $product->getTranslation('name');

        foreach ($product->getOptions() ?? [] as $index => $option) {
            if ($index === 0) {
                $name .= ' - ';
            }
            $name .= $option->getTranslation('name');

            if ($index + 1 < $product->getOptions()->count()) {
                $name .= ', ';
            }
        }

        return $name;
    }

    public static function getHandledMessages(): iterable
    {
        return [OpenInvoiceMessage::class];
    }
}
