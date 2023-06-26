<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use Ibexa\Contracts\Core\Repository\Events\Location\MoveSubtreeEvent;
use Ibexa\Contracts\Core\Repository\Events\Location\SwapLocationEvent;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnSwapSubscriber implements EventSubscriberInterface
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
            SwapLocationEvent::class => ['onSwapLocation', 0],
        ];
    }

    public function onSwapLocation(SwapLocationEvent $event): void
    {
        try {
            $this->indexDocument->indexSubtree($event->getLocation1()->pathString);
            $this->indexDocument->indexSubtree($event->getLocation2()->pathString);
        } catch (\Exception $e) {
            $this->logger->error($e);
            dump($e);
        }
    }
}
