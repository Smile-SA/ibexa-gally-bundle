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
        private readonly ContainerInterface $container,
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

    public function onCopyContent(CopyContentEvent $event)
    {
        $this->indexDocument->index(
            $this->container->getParameter('ibexa.site_access.default'),
            $event->getContent()
        );
    }
}
