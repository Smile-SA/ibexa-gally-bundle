<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use Ibexa\Contracts\Core\Repository\Events\Content\HideContentEvent;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnHideSubscriber implements EventSubscriberInterface
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
            HideContentEvent::class => ['onHideContent', 0],
        ];
    }

    public function onHideContent(HideContentEvent $event): void
    {
        try {
            $this->indexDocument->deleteContent(
                $event->getContentInfo()->id
            );
        } catch (\Exception $e) {
            $this->logger->error($e);
            dump($e);
        }
    }
}
