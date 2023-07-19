<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use eZ\Publish\API\Repository\Events\Trash\BeforeTrashEvent;
use eZ\Publish\API\Repository\Events\Trash\RecoverEvent;
use eZ\Publish\Core\Repository\SiteAccessAware\ContentService;
use Psr\Log\LoggerInterface;
use Smile\Ibexa\Gally\Service\Index\IndexDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OnTrashSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IndexDocument $indexDocument,
        private readonly ContentService $contentService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeTrashEvent::class => ['onBeforeTrash', -1],
            RecoverEvent::class => ['onRecover', -1],
        ];
    }

    public function onBeforeTrash(BeforeTrashEvent $event): void
    {
        try {
            $this->indexDocument->deleteContent(
                $event->getLocation()->contentId
            );
        } catch (\Exception $e) {
            $this->logger->error($e);
            dump($e);
        }
    }

    public function onRecover(RecoverEvent $event): void
    {
        try {
            $content = $this->contentService->loadContent(
                $event->getTrashItem()->contentId,
                $event->getTrashItem()->getContent()->getVersionInfo()->languageCodes
            );
            $this->indexDocument->sendContent(
                $content
            );
        } catch (\Exception $e) {
            $this->logger->error($e);
            dump($e);
        }
    }
}
