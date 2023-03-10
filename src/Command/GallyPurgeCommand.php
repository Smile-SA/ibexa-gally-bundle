<?php

namespace Smile\Ibexa\Gally\Command;

use Smile\Ibexa\Gally\Service\GallyStructure\GallyStructureManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'gally:structure:purge', description: 'Purge Gally Index, Metadata and Source fields.')]
class GallyPurgeCommand extends Command
{
    public function __construct(private readonly GallyStructureManager $gallyStructureManager)
    {
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $output->writeln('This command will purge every metadata, sourcefield, catalog, localized catalog.');
        $question = new ConfirmationQuestion(
            'Are you sure you want to continue [y/n] : ',
            false,
            '/^(y)/i'
        );

        if ($helper->ask($input, $output, $question)) {
            try {
                $this->gallyStructureManager->purge(
                    fn ($message) => $output->writeln($message, OutputInterface::VERBOSITY_VERBOSE)
                );
            } catch (\Exception $e) {
                dump($e);
                $output->writeln('Failure', OutputInterface::VERBOSITY_VERBOSE);

                return Command::FAILURE;
            }
            $output->writeln('Success', OutputInterface::VERBOSITY_VERBOSE);
        } else {
            $output->writeln('Abort', OutputInterface::VERBOSITY_VERBOSE);
        }

        return Command::SUCCESS;
    }
}
