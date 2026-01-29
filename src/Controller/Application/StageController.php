<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\Entity\AssessmentStream;
use App\Entity\Assignment;
use App\Entity\Evaluation;
use App\Entity\Project;
use App\Entity\Stage;
use App\Entity\User;
use App\Enum\Role;
use App\Repository\AssessmentStreamRepository;
use App\Repository\UserRepository;
use App\Service\AssessmentStreamService;
use App\Service\AssignmentService;
use App\Service\ProjectService;
use App\Service\StageService;
use App\Service\UserService;
use App\Util\RepositoryParameters;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stage', name: 'stage_')]
class StageController extends AbstractController
{
    #[Route('/ajaxSetAssignedTo/{assessmentStream}', name: 'ajaxSetAssignedTo', requirements: ['assessmentStream' => "\d+"], methods: ['POST'])]
    #[IsGranted('ASSIGN_STREAM_USER', 'assessmentStream')]
    public function ajaxSetAssignedTo(
        Request $request,
        AssessmentStream $assessmentStream,
        StageService $stageService,
        UserRepository $userRepository,
        AssignmentService $assignmentService
    ): JsonResponse {
        $currentUser = $this->getUser();

        if ($assessmentStream->getCurrentStage() === null) {
            $evaluation = new Evaluation();
            $stageService->addNewStage($assessmentStream, $evaluation);
        }
        $stage = $assessmentStream->getCurrentStage();
        $name = $request->request->get('name');
        $assignedUserId = $request->get('value');

        if ($name !== 'assignedTo') {
            return new JsonResponse();
        }

        $assignedUser = $userRepository->findOneBy(['id' => $assignedUserId]);
        $currentAssignment = $assignmentService->getStageAssignment($stage);
        $status = $this->setStageAssignedTo($stage, $assignedUser, $currentUser, $currentAssignment, $assignmentService);

        return new JsonResponse($status);
    }

    private function setStageAssignedTo(
        $stage,
        $assignedUser,
        $user,
        $currentAssignment,
        AssignmentService $assignmentService
    ): ?array {
        $status = null;
        if ($assignedUser instanceof User &&
            ($this->isGranted('ROLE_MANAGER') || ($user->getId() === $assignedUser->getId() && $currentAssignment?->getUser() === null)) &&
            ($currentAssignment?->getUser()->getId() !== $assignedUser->getId())) {
            $assignmentService->deleteStageAssignments($stage);
            $assignmentService->addAssignment(new Assignment(), $stage, $assignedUser, $user);
            $status = ['status' => 'ok'];
        } elseif($assignedUser === null) {
            $this->deleteAssignment($stage, $assignmentService);
            $status = ['status' => 'ok'];
        }

        return $status;
    }

    #[Route('/deleteAssignment/{stage}', name: 'deleteAssignment', requirements: ['assignment' => "\d+"], methods: ['POST'])]
    #[IsGranted(new Expression("is_granted('ASSESSMENT_STREAM_ACCESS', subject.stage.assessmentStream) && is_granted('ROLE_MANAGER')"), 'assignment')]
    public function deleteAssignment(
        Stage $stage,
        AssignmentService $assignmentService
    ): JsonResponse {
        $assignmentService->deleteStageAssignments($stage);

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/ajaxindexForAssignment/{project}/{assessmentStream}/{short}', name: 'ajaxindexForAssignment', requirements: [
        'project' => "\d+",
        'assessmentStream' => "\d+",
        'short' => 'true|false|1|0',
    ], defaults: ['short' => false], methods: ['GET'])]
    #[IsGranted('PROJECT_ACCESS', 'project')]
    public function ajaxindexForAssignment(
        Request $request,
        Project $project,
        AssessmentStream $assessmentStream,
        UserService $userService,
        bool $short = false
    ): JsonResponse {
        $currentUser = $this->getUser();

        $repositoryParameters = new RepositoryParameters();
        $repositoryParameters->setFilter($request->query->get('term', ''));
        $repositoryParameters->setOrderBy([['_user.id', 'ASC']]);

        $neededRole = match ($assessmentStream->getStatus()) {
            \App\Enum\AssessmentStatus::NEW, \App\Enum\AssessmentStatus::IN_EVALUATION => Role::EVALUATOR->string(),
            \App\Enum\AssessmentStatus::IN_VALIDATION => Role::VALIDATOR->string(),
            \App\Enum\AssessmentStatus::VALIDATED, \App\Enum\AssessmentStatus::IN_IMPROVEMENT, \App\Enum\AssessmentStatus::COMPLETE => Role::IMPROVER->string(),
            default => Role::USER->string()
        };

        $results[] = ['value' => -1, 'text' => " ____ "];

        if ($this->isGranted('ROLE_MANAGER')) {
            $usersWithAccess = $userService->getUsersWithProjectAccess($project, $neededRole);
        } else {
            // You can only assign yourself if you're not manager
            $usersWithAccess = (in_array($neededRole, $currentUser->getRoles(), true)) ? [$currentUser] : [];
        }

        foreach ($usersWithAccess as $user) {
            $results[] = ['value' => $user->getId(), 'text' => $short ? $user->getShortName() : "{$user->getName()} {$user->getSurname()}"];
        }

        usort($results, fn($a, $b) => strcmp($a['text'], $b['text']));

        return new JsonResponse($results);
    }
}
