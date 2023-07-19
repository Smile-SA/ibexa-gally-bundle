<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use eZ\Publish\API\Repository\Events\Location\MoveSubtreeEvent;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnMoveSubscriber implements EventSubscriberInterface
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
            MoveSubtreeEvent::class => ['onMoveSubtree', 0],
        ];
    }

    public function onMoveSubtree(MoveSubtreeEvent $event): void
    {
        try {
            $this->indexDocument->indexSubtree($event->getLocation()->pathString);
        } catch (\Exception $e) {
            $this->logger->error($e);
            dump($e);
        }
    }
}
