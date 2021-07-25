<?php

declare(strict_types=1);

namespace MettwareSlack\Command;

use MettwareSlack\Handler\OpenInvoiceMessage;
use MettwareSlack\Service\InvoiceService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class OpenInvoiceCommand extends Command
{
    protected static $defaultName = 'mw:invoice';

    private const OPTION_FILTER_SLACK_ID = 'filterSlackId';
    private const OPTION_ADDITIONAL_ACCOUNTS = 'additionalIds';
    private InvoiceService $invoiceService;
    private MessageBusInterface $messageBus;

    public function __construct(
        InvoiceService $invoiceService,
        MessageBusInterface $messageBus
    ) {
        parent::__construct();
        $this->invoiceService = $invoiceService;
        $this->messageBus = $messageBus;
    }

    protected function configure(): void
    {
        $this->addOption(
            self::OPTION_ADDITIONAL_ACCOUNTS,
            '-u',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Slack Ids which should get all invoices as copy.'
        );

        $this->addOption(
            self::OPTION_FILTER_SLACK_ID,
            'f',
            InputOption::VALUE_OPTIONAL,
            'Send only invoices to Slack ID'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $context = $context->enableInheritance(fn(Context $context) => $context);
        $additionalSlackIds = $input->getOption(self::OPTION_ADDITIONAL_ACCOUNTS);
        $messages = $this->invoiceService->getSlackIdsForOpenInvoices($context);

        $filterSlackId = $input->getOption(self::OPTION_FILTER_SLACK_ID);
        if ($filterSlackId !== null) {
            $messages = array_filter(
                $messages,
                fn(OpenInvoiceMessage $message) => $message->getSlackId() === $filterSlackId
            );
        }

        $delayFactor = 1000 * count($additionalSlackIds);
        $delay = 1;
        foreach ($messages as $message) {
            $message->setAdditionalContacts($additionalSlackIds);
            $this->messageBus->dispatch(
                new Envelope(
                    $message,
                    [new DelayStamp($delay++ * $delayFactor)]
                )
            );
        }

        return 0;
    }
}
