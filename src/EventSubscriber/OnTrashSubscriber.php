<?php

namespace Smile\Ibexa\Gally\EventSubscriber;

use Ibexa\Contracts\Core\Repository\Events\Trash\RecoverEvent;
use Ibexa\Contracts\Core\Repository\Events\Trash\TrashEvent;
use Ibexa\Core\Repository\SiteAccessAware\ContentService;
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
            $this->indexDocument->index(
                $content
            );
        } catch (\Exception $e) {
            $this->logger->error($e);
            dump($e);
        }
    }
}
