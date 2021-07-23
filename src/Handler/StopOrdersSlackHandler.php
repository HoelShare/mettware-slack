<?php

declare(strict_types=1);

namespace MettwareSlack\Handler;

use GuzzleHttp\ClientInterface;
use Mettware\Message\SlackMessage;
use Mettware\Message\StopOrdersMessage;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

class StopOrdersSlackHandler extends AbstractMessageHandler
{
    private ClientInterface $client;
    private SystemConfigService $systemConfigService;

    public function __construct(ClientInterface $client, SystemConfigService $systemConfigService)
    {
        $this->client = $client;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param StopOrdersMessage $message
     */
    public function handle($message): void
    {
        $token = $this->systemConfigService->get('MettwareSlack.config.slackBotToken');
        $channel = $this->systemConfigService->get('MettwareSlack.config.mettwareChannel');

        if (!$token ||!$channel) {
            return;
        }

        $blocks = $this->getBlocks($message);

        $this->client->request(
            Request::METHOD_POST,
            'https://slack.com/api/chat.postMessage',
            [
                'headers' => [
                  'Authorization' => 'Bearer ' . $token,
                ],
                'json' => [
                    'channel' => $channel,
                    "blocks" => $blocks,
                ],
            ]
        );
    }

    public static function getHandledMessages(): iterable
    {
        return [StopOrdersMessage::class];
    }

    /**
     * @param StopOrdersMessage $slackMessage
     */
    private function getBlocks(SlackMessage $slackMessage): array
    {
        $orders = $slackMessage->getOrders();


        $markdown = '';

        /** @var OrderEntity $order */
        foreach ($orders->getIterator() as $order) {
            $customerObject = $order->getOrderCustomer();
            if ($customerObject !== null) {
                $customer = $customerObject->getFirstName() . ' ' . $customerObject->getLastName();
            } else {
                $customer = '';
            }
            foreach ($order->getLineItems() as $lineItem) {
                $name = $this->formatName($lineItem->getProduct());
                $markdown .= '>' . $customer . ' - ' . $lineItem->getQuantity() . 'x '. $name . PHP_EOL;
            }
        }


        return [
            [
                "type" => "header",
                "text" => [
                    "type" => "plain_text",
                    "text" => "Orders stopped - Todays orders",
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
}
