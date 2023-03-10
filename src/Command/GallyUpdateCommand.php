<?php

namespace Smile\Ibexa\Gally\Command;

use Smile\Ibexa\Gally\Service\GallyStructure\GallyStructureManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'gally:structure:update', description: 'Update content type in Gally with Metadata, Source fields and Index.')]
class GallyUpdateCommand extends Command
{
    public function __construct(private readonly GallyStructureManager $gallyStructureManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->gallyStructureManager->update(
                fn ($message) => $output->writeln($message, OutputInterface::VERBOSITY_VERBOSE)
            );
        } catch (\Exception $e) {
            dump($e);
            $output->writeln('Failure', OutputInterface::VERBOSITY_VERBOSE);

            return Command::FAILURE;
        }
        $output->writeln('Success', OutputInterface::VERBOSITY_VERBOSE);

        return Command::SUCCESS;
    }
}
