<?php
declare(strict_types=1);

namespace MettwareSlack\Command;

use MettwareSlack\Handler\OpenInvoiceHandler;
use MettwareSlack\Handler\OpenInvoiceMessage;
use MettwareSlack\Service\InvoiceService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class OpenInvoiceCommand extends Command
{
    protected static $defaultName = 'mw:invoice';

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

    protected function configure()
    {
        $this->addOption(
            self::OPTION_ADDITIONAL_ACCOUNTS,
            '-u',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Slack Ids which should get all invoices as copy.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $additionalSlackIds = $input->getOption(self::OPTION_ADDITIONAL_ACCOUNTS);
        $slackIds = $this->invoiceService->getSlackIdsForOpenInvoices($context);

        foreach ($slackIds as $slackId) {
            $message = new OpenInvoiceMessage($slackId, $additionalSlackIds);
            $this->messageBus->dispatch($message);
        }

        return 0;
    }
}
