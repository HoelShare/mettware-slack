<?php declare(strict_types=1);

namespace MettwareSlack\Handler;

class OpenInvoiceMessage
{
    private string $slackId;
    private ?array $additionalContacts;

    public function __construct(string $slackId, array $additionalContacts = null)
    {
        $this->slackId = $slackId;
        $this->additionalContacts = $additionalContacts;
    }

    public function getSlackId(): string
    {
        return $this->slackId;
    }

    public function getAdditionalContacts(): ?array
    {
        return $this->additionalContacts;
    }
}
