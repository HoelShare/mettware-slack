<?php declare(strict_types=1);

namespace MettwareSlack\Handler;

class OpenInvoiceMessage
{
    private string $slackId;
    private ?array $additionalContacts;
    private string $languageId;

    public function __construct(string $slackId, string $languageId, array $additionalContacts = null)
    {
        $this->slackId = $slackId;
        $this->languageId = $languageId;
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

    public function setAdditionalContacts(?array $additionalContacts): void
    {
        $this->additionalContacts = $additionalContacts;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }
}
