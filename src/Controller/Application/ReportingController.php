<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\Entity\Project;
use App\Repository\BusinessFunctionRepository;
use App\Exception\SammExportValidationException;
use App\Service\Processing\AssessmentExporterService;
use App\Service\ProjectService;
use App\Service\ReportingService;
use App\Service\SammExportService;
use App\Service\ScoreService;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ReportingController.
 */
#[Route('/reporting', name: 'reporting_')]
class ReportingController extends AbstractController
{
    /**
     * @throws NonUniqueResultException
     */
    #[Route('', name: 'index')]
    public function index(
        Request $request,
        ScoreService $scoreService,
        ProjectService $projectService,
        ReportingService $reportingService,
        BusinessFunctionRepository $businessFunctionRepository
    ): Response {
        $currentProject = $projectService->getCurrentProject();
        if ($currentProject !== null) {
            $this->denyAccessUnlessGranted('PROJECT_ACCESS', $currentProject);
        }

        $currentAssessment = $currentProject?->getAssessment();
        if ($currentAssessment === null) {
            return $this->render(
                'application/reporting/index.html.twig',
                [
                    'assessment' => null,
                    'businessFunctionScore' => [],
                    'securityPracticeScore' => [],
                ]
            );
        }

        $unvalidatedToggle = $request->cookies->get('unvalidated-score-toggle') === 'true';

        $hasTargetPosture = $currentAssessment->getProject()->getTemplateProject() !== null;

        $targetPostureScores = $hasTargetPosture ? $scoreService->getTargetPostureScoresByAssessment($currentProject->getAssessment()) : [];

        if ($unvalidatedToggle) {
            $firstScores = $scoreService->getNotValidatedScoresByAssessment($currentProject->getAssessment());
        } else {
            $firstScores = $scoreService->getScoresByAssessment($currentProject->getAssessment(), new \DateTime('now'), $currentProject->isTemplate() === false);
        }
        $secondScores = $scoreService->getProjectedScoresByAssessment($currentProject->getAssessment());


        $verifiedScores = $scoreService->getExternallyVerifiedScoreArray($currentProject->getAssessment());
        $totalVerifiedScore = ScoreService::calculateMeanScore($verifiedScores['businessFunction']);

        $projectPercentages = $reportingService->getPercentageOfTargetScopeForProjects(
            new \DateTime('now'),
            $this->getUser(),
            $currentProject,
            validated: !$unvalidatedToggle
        );


        // TODO Extract data to services
        return $this->render('application/reporting/index.html.twig', [
            'assessment' => $currentAssessment,
            'scores' => $firstScores,
            'verifiedScores' => $verifiedScores,
            'lineChartsData' => $scoreService->getScoreForAssessmentPerDates($currentAssessment, !$unvalidatedToggle),
            'firstScore' => ScoreService::calculateMeanScore($firstScores['businessFunction']),
            'secondScore' => ScoreService::calculateMeanScore($secondScores['businessFunction']),
            'targetScore' => $hasTargetPosture ? ScoreService::calculateMeanScore($targetPostureScores['businessFunction']) : null,
            'verifiedScore' => $totalVerifiedScore !== 0.0 ? $totalVerifiedScore : null,
            'projectsPercentage1' => $projectPercentages,
            'projectsPercentage2' => [],
            'targetPostureScores' => $targetPostureScores,
            'maxScore' => $currentProject->getMetamodel()->getMaxScore(),
            'businessFunctions' => $businessFunctionRepository->findBy([], ['order' => 'ASC'], metamodel: $currentAssessment->getProject()->getMetamodel()),
            'currentProjectName' => $currentAssessment->getProject()->getName(),
        ]);
    }

    /**
     * @throws NonUniqueResultException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/export-samm', name: 'export_samm', methods: ['POST'])]
    public function exportSamm(AssessmentExporterService $assessmentExporterService, ProjectService $projectService, Request $request): BinaryFileResponse|Response
    {
        if (!$this->isCsrfTokenValid('export_answers_auth', $request->request->get('token'))) {
            return $this->safeRedirect($request, 'app_index');
        }
        $project = $projectService->getCurrentProject();
        $this->denyAccessUnlessGranted('PROJECT_ACCESS', $project);
        $assessment = $project->getAssessment();

        $filePath = $assessmentExporterService->getToolbox($assessment, $project);

        return $this->file($filePath);
    }

    #[Route('/export-samm-json', name: 'export_samm_json', methods: ['POST'])]
    public function exportSammJson(SammExportService $sammExportService, ProjectService $projectService, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('export_answers_auth', $request->request->get('token'))) {
            return $this->safeRedirect($request, 'app_index');
        }

        $project = $projectService->getCurrentProject();
        $this->denyAccessUnlessGranted('PROJECT_ACCESS', $project);
        $assessment = $project->getAssessment();

        try {
            $json = $sammExportService->export($assessment);
        } catch (SammExportValidationException $e) {
            $this->addFlash('error', 'Export validation failed: '.implode(', ', $e->getValidationErrors()));

            return $this->safeRedirect($request, 'reporting_index');
        }

        $frameworkName = $project->getMetamodel()?->getName() ?? 'SAMM';
        $filename = sprintf('%s_%s_%s.samm.json', $project->getName(), $frameworkName, uniqid());
        $asciiFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        $response = new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($json),
        ]);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            $asciiFilename
        ));

        return $response;
    }

    #[Route('/overviewPartial/{id}', name: 'overviewPartial', requirements: ['id' => "\d+"], methods: ['GET'])]
    public function overviewPartial(Request $request, ScoreService $scoreService, ?Project $project): Response
    {
        return ($project !== null) ? $this->renderOverview($request, $scoreService, $project, false) : new Response(status: Response::HTTP_NOT_FOUND);
    }

    #[Route('/overview/{id}', name: 'overview', requirements: ['id' => "\d+"], methods: ['GET'])]
    public function overview(Request $request, ScoreService $scoreService, ?Project $project): Response
    {
        return ($project !== null) ? $this->renderOverview($request, $scoreService, $project) : new Response(status: Response::HTTP_NOT_FOUND);
    }

    private function renderOverview(Request $request, ScoreService $scoreService, Project $project, bool $fullView = true): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_ACCESS', $project);

        $assessment = $project->getAssessment();

        if ($request->cookies->get('unvalidated-score-toggle') === 'true') {
            $score = $scoreService->getNotValidatedScoresByAssessment($assessment);
        } else {
            $score = $scoreService->getScoresByAssessment($assessment, validated: $project->isTemplate() === false);
        }

        return $this->render(
            $fullView ? 'application/model/modals/_chart.html.twig' : 'application/project/partials/_overview_part.html.twig',
            [
                'project' => $project,
                'assessment' => $assessment,
                'businessFunctionScore' => $score['businessFunction'],
                'securityPracticeScore' => $score['securityPractice'],
            ]
        );
    }
}
