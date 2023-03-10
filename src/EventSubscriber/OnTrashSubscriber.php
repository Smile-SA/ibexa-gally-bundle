<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use Ibexa\Contracts\Core\Repository\Events\Trash\RecoverEvent;
use Ibexa\Contracts\Core\Repository\Events\Trash\TrashEvent;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnTrashSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IndexDocument $indexDocument,
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TrashEvent::class => ['onTrash', -1],
            RecoverEvent::class => ['onRecover', -1],
        ];
    }

    public function onTrash()
    {
        try {
            $this->indexDocument->reindexAll(
                fn ($message) => $this->logger->info($message)
            );
        } catch (\Exception $e) {
            dump($e);
        }
    }

    public function onRecover(RecoverEvent $event)
    {
        $this->indexDocument->index(
            $this->container->getParameter('ibexa.site_access.default'),
            $event->getTrashItem()->getContent()
        );
    }
}
