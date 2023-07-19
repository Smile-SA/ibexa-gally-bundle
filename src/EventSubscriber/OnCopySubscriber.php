<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use eZ\Publish\API\Repository\Events\Content\CopyContentEvent;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnCopySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IndexDocument $indexDocument,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CopyContentEvent::class => ['onCopyContent', 0],
        ];
    }

    public function onCopyContent(CopyContentEvent $event): void
    {
        try {
            $this->indexDocument->sendContent(
                $event->getContent()
            );
        } catch (\Exception $e) {
            $this->logger->error($e);
            dump($e);
        }
    }
}
