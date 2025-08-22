<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Activity;
use App\Entity\AnswerSet;
use App\Entity\BusinessFunction;
use App\Entity\MaturityLevel;
use App\Entity\Practice;
use App\Entity\PracticeLevel;
use App\Entity\Question;
use App\Entity\Stream;
use App\Repository\MetamodelRepository;
use App\Service\MetamodelService;
use App\Service\Processing\DsommYamlToDbRecordsSyncer;
use App\Utils\Constants;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:sync-from-dsomm')]
class SyncFromDsommYamlCommand extends Command
{
    protected static $defaultName = 'app:sync-from-dsomm';
    private const METAMODEL_COMMAND_PARAMETER_NAME = 'metamodel';
    private const SOURCE_YAML_COMMAND_PARAMETER_NAME = 'source';

    public function __construct(
        private readonly DsommYamlToDbRecordsSyncer $dbRecordsSyncer,
        private readonly EntityManagerInterface $entityManager,
        private readonly MetamodelRepository $metamodelRepository,
        private readonly MetamodelService $metamodelService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(self::SOURCE_YAML_COMMAND_PARAMETER_NAME, 's', InputOption::VALUE_OPTIONAL, 'Path to YAML source files');
        $this->addOption(self::METAMODEL_COMMAND_PARAMETER_NAME, 'm', InputOption::VALUE_OPTIONAL, 'To which metamodel should this model be linked?');

        $this->setDescription('Sync DSOMM model from YAML files to database');
    }

    /**
     * @throws \Throwable
     * @throws ConnectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourcePath = $input->getOption(self::SOURCE_YAML_COMMAND_PARAMETER_NAME);
        if ($sourcePath !== null) {
            $this->dbRecordsSyncer->loadYamlFile($sourcePath);
        }

        $metamodelId = $input->getOption(self::METAMODEL_COMMAND_PARAMETER_NAME);
        if ($metamodelId === null) {
            $metamodel = $this->metamodelService->createDSOMM();
        } else {
            $metamodel = $this->metamodelRepository->find($metamodelId);
        }

        $this->dbRecordsSyncer->setMetamodel($metamodel);

        $this->entityManager->getConnection()->beginTransaction();
        try {
            [$addedBusinessFuncs, $modifiedBusinessFuncs] = $this->dbRecordsSyncer->syncBusinessFunctions();
            [$addedSecurityPractices, $modifiedSecurityPractices] = $this->dbRecordsSyncer->syncSecurityPractices();
            [$addedMaturityLevels, $modifiedMaturityLevels] = $this->dbRecordsSyncer->syncMaturityLevels();
            [$addedStreams, $modifiedStreams] = $this->dbRecordsSyncer->syncStreams();
            [$addedPracticeLevels, $modifiedPracticeLevels] = $this->dbRecordsSyncer->syncPracticeLevels();
            [$addedActivities, $modifiedActivities] = $this->dbRecordsSyncer->syncActivities();
            [$addedAnswerSets, $modifiedAnswerSets] = $this->dbRecordsSyncer->syncAnswerSets();
            [$addedQuestions, $modifiedQuestions] = $this->dbRecordsSyncer->syncQuestions();

            $this->entityManager->getConnection()->commit();
        } catch (\Throwable $t) {
            $this->entityManager->getConnection()->rollBack();
            $this->entityManager->clear();
            $output->writeln('Failed');
            throw $t;
        }

        $table = new Table($output);
        $table
            ->setHeaders(['Name', 'Added', 'Updated', 'Deleted'])
            ->setRows([
                [BusinessFunction::class, $addedBusinessFuncs, $modifiedBusinessFuncs],
                [Practice::class, $addedSecurityPractices, $modifiedSecurityPractices],
                [MaturityLevel::class, $addedMaturityLevels, $modifiedMaturityLevels],
                [Stream::class, $addedStreams, $modifiedStreams],
                [PracticeLevel::class, $addedPracticeLevels, $modifiedPracticeLevels],
                [Activity::class, $addedActivities, $modifiedActivities],
                [AnswerSet::class, $addedAnswerSets, $modifiedAnswerSets],
                [Question::class, $addedQuestions, $modifiedQuestions],
            ])->render();

        return Command::SUCCESS;
    }
}
