<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use eZ\Publish\API\Repository\Events\Content\DeleteTranslationEvent;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnDeleteTranslationSubscriber implements EventSubscriberInterface
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
            DeleteTranslationEvent::class => ['onDeleteTranslation', 0],
        ];
    }

    public function onDeleteTranslation(DeleteTranslationEvent $event): void
    {
        try {
            $this->indexDocument->deleteContent(
                $event->getContentInfo()->id,
                [$event->getLanguageCode()]
            );
        } catch (\Exception $e) {
            $this->logger->error($e);
            dump($e);
        }
    }
}
