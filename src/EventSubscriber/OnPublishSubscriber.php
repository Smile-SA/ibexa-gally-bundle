<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use eZ\Publish\API\Repository\Events\Content\PublishVersionEvent;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnPublishSubscriber implements EventSubscriberInterface
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
            PublishVersionEvent::class => ['onPublishVersion', 0],
        ];
    }

    public function onPublishVersion(PublishVersionEvent $event): void
    {
        try {
            $this->indexDocument->sendContent(
                $event->getContent(),
                $event->getTranslations()
            );
        } catch (\Exception $e) {
            $this->logger->error($e);
            dump($e);
        }
    }
}
