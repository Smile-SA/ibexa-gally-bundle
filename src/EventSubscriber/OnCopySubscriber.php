<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use Ibexa\Contracts\Core\Repository\Events\Content\CopyContentEvent;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnCopySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IndexDocument $indexDocument,
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
        $this->indexDocument->index(
            $event->getContent()
        );
    }
}
