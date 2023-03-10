<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use Ibexa\Contracts\Core\Repository\Events\Content\PublishVersionEvent;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnPublishSubscriber implements EventSubscriberInterface
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
            PublishVersionEvent::class => ['onPublishVersion', 0],
        ];
    }

    public function onPublishVersion(PublishVersionEvent $event)
    {
        $this->indexDocument->index(
            $this->container->getParameter('ibexa.site_access.default'),
            $event->getContent()
        );
    }
}
