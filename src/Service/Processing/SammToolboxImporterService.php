<?php

declare(strict_types=1);

namespace App\Service\Processing;

use App\Entity\Practice;
use App\Entity\Project;
use App\Entity\Question;
use App\Entity\User;
use App\Exception\AnswerNotFoundInDatabaseException;
use App\Exception\QuestionNotFoundInToolboxException;
use App\Exception\SammVersionNotFoundInToolboxException;
use App\Exception\ZeroSheetsProvidedForImportException;
use App\Repository\AssessmentStreamRepository;
use App\Repository\QuestionRepository;
use App\Service\AssessmentAnswersService;
use App\Service\AssessmentService;
use App\Service\MetamodelService;
use App\Service\ProjectService;
use App\Utils\Constants;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

class SammToolboxImporterService extends ExcelImporter
{
    private const VERSION_20_IMPORT_ANSWERS_ROW_START = 18;
    private const VERSION_20_IMPORT_ANSWERS_COL_START = 4;
    private const MAX_DIFFERENT_CHARACTERS_IN_QUESTION_TEXT = 1;
    private const VERSION_CELL_ADDRESS = [2, 3];
    private const ROADMAP_SHEET_NAME = "Roadmap";
    private const INTERVIEW_SHEET_NAME = "Interview";

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly MetamodelService $metamodelService,
        private readonly QuestionRepository $questionRepository,
        private readonly AssessmentService $assessmentService,
        private readonly AssessmentAnswersService $assessmentAnswersService,
        private readonly AssessmentStreamRepository $assessmentStreamRepository,
        private readonly ProjectService $projectService,
    ) {
    }

    public function import(UploadedFile $file, bool $autoValidate, User $submittedUser): Project
    {
        $spreadsheet = $this->loadPhpExcelObject($file->getPathname());

        $sheetCount = $spreadsheet->getSheetCount();
        if ($sheetCount === 0) {
            throw new ZeroSheetsProvidedForImportException($this->translator->trans('application.project.import_zero_sheets', [], 'application'));
        }
        $sheet = $spreadsheet->getSheet(0);

        $version = $sheet->getCell(self::VERSION_CELL_ADDRESS)->getValue();
        $this->entityManager->getConnection()->beginTransaction();
        try {

            $project = $this->importSamm(
                $spreadsheet,
                $autoValidate,
                $submittedUser,
                Constants::SAMM_ID,
                self::VERSION_20_IMPORT_ANSWERS_COL_START,
                self::VERSION_20_IMPORT_ANSWERS_ROW_START,
                $this->translator->trans('application.project.import_toolbox_project_name_20', [], 'application'),
            );
            $this->entityManager->getConnection()->commit();

            return $project;
        } catch (\Throwable $t) {
            $this->entityManager->getConnection()->rollBack();
            throw $t;
        }
    }

    private function isQuestionPresentOnAddress(int $col, int $row, Worksheet $sheet, Question $question): bool
    {
        $questionNameKey = $question->getNameKey();
        return $sheet->getCell([1, $row])->getCalculatedValue() === $questionNameKey || $sheet->getCell([1, $row])->getCalculatedValue() === substr($questionNameKey, 0, 5).strrev(substr($questionNameKey, 5, 3)).substr($questionNameKey, 8);
    }

    private function importSamm(Spreadsheet $spreadsheet, bool $autoValidate, User $submittedUser, int $metamodelId, int $col, int $row, string $projectTitle)
    {
        $project = $this->projectService->createProject(
            $projectTitle,
            date('Y-m-d H:i:s'),
            [],
            $metamodelId
        );

        $sheet = $spreadsheet->getSheetByName(self::ROADMAP_SHEET_NAME);
        $interviewSheet = $spreadsheet->getSheetByName(self::INTERVIEW_SHEET_NAME);
        $questions = $this->questionRepository->findByMetamodel($project->getMetamodel());
        $assessmentStreams = $this->assessmentStreamRepository->findAllStreamsForAssessment($project->getAssessment(), 'assessmentStream.stream');
        $answersForStream = [];
        $validationRemarksForStream = [];

        $interviewNotesCounter = 0;
        foreach ($questions as $loopCounter => $question) {
            //find the question row
            $i = 0;
            for (; $i < 5; $i++) {
                if (!$this->isQuestionPresentOnAddress($col, $row, $sheet, $question)) {
                    $row++;
                } else {
                    break;
                }
            }
            if ($i === 5) {
                throw new QuestionNotFoundInToolboxException(
                    'Row: '.$row.' Col: '.$col.' Question:'.$question->getText().' '.$this->translator->trans('application.project.question_not_found', [], 'application')
                );
            }

            $additionColDueToMissingCols = 2;
            $answerColNumber = $col + $additionColDueToMissingCols;
            $answerFromSheet = $sheet->getCell([$answerColNumber, $row])->getCalculatedValue();
            $streamId = $question->getActivity()->getStream()->getId();

            $interviewRemarksColumn = 9;
            $interviewRemarksRow = $row + $interviewNotesCounter++;
            //this is the magic to match the different layout in Interview
            if (($loopCounter + 1) % 6 === 0) {
                $interviewNotesCounter--;
            }
            if (!isset($validationRemarksForStream[$streamId])) {
                $validationRemarksForStream[$streamId] = "";
            }
            if ($interviewSheet->getCell([$interviewRemarksColumn, $interviewRemarksRow])->getValue() !== null) {
                $validationRemarksForStream[$streamId] .= $interviewSheet->getCell([$interviewRemarksColumn, $interviewRemarksRow])->getValue()."<br/>";
            }
            if ($answerFromSheet !== null && $answerFromSheet !== '' && $answerFromSheet !== 0) {
                $answerWasFound = false;
                foreach ($question->getAnswers() as $answer) {
                    if ($answer->getText() === $answerFromSheet) {
                        if (array_key_exists($streamId, $answersForStream)) {
                            ++$answersForStream[$streamId];
                        } else {
                            $answersForStream[$streamId] = 1;
                        }
                        $answerWasFound = true;
                        $this->assessmentAnswersService->saveAnswer(
                            $assessmentStreams[$streamId],
                            $question,
                            $answer,
                            $submittedUser
                        );
                    }
                }
                if (!$answerWasFound) {
                    throw new AnswerNotFoundInDatabaseException('Row: '.$row.' Col: '.$answerColNumber.' '.$this->translator->trans('application.project.answer_not_found', [], 'application'));
                }
            }
        }
        $allStreamsForMetamodel = $this->metamodelService->getStreams($project->getMetamodel(), 'id');
        foreach ($allStreamsForMetamodel as $streamForMetamodel) {
            $questionsForThisStream = 0;
            foreach ($streamForMetamodel->getStreamActivities() as $activity) {
                $questionsForThisStream += sizeof($activity->getActivityQuestions());
            }

            $evaluation = $assessmentStreams[$streamForMetamodel->getId()]->getLastEvaluationStage();
            if ($evaluation !== null) {
                $evaluation->setComment($validationRemarksForStream[$streamForMetamodel->getId()]);
                $this->entityManager->persist($evaluation);
            }
            if (array_key_exists($streamForMetamodel->getId(), $answersForStream) && $answersForStream[$streamForMetamodel->getId()] === $questionsForThisStream) {
                $this->assessmentService->submitStreamWithoutAutoValidateAttempt($assessmentStreams[$streamForMetamodel->getId()], $submittedUser);
                if ($autoValidate) {
                    $this->assessmentService->validateStream(
                        $assessmentStreams[$streamForMetamodel->getId()],
                        $submittedUser,
                        $validationRemarksForStream[$streamForMetamodel->getId()],
                        \App\Enum\ValidationStatus::AUTO_ACCEPTED
                    );
                }
            }
        }

        return $project;
    }
}
