<?php

declare(strict_types=1);

namespace App\EventSubscriber\Application;

use App\Entity\User;
use App\Event\Admin\Create\ProjectCreatedEvent;
use App\Event\Admin\Delete\ProjectDeletedEvent;
use App\Repository\AssessmentRepository;
use App\Repository\GroupProjectRepository;
use App\Service\ProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProjectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AssessmentRepository $assessmentRepository,
        private readonly ProjectService $projectService,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly GroupProjectRepository $groupProjectRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProjectCreatedEvent::class => [
                'onProjectCreated',
            ],
            ProjectDeletedEvent::class => [
                'onProjectDeleted',
            ],
            KernelEvents::REQUEST => [
                ['onRequest'],
            ],
        ];
    }

    /**
     * @return void
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    #[NoReturn]
    public function onProjectCreated(ProjectCreatedEvent $event)
    {
        $project = $event->getProject();

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \Exception('user is not instance of User');
        }
        if ($this->projectService->getCurrentProject() === null) {
            // no need for access control here as users who can create projects can certainly access them
            $this->projectService->setCurrentProject($project);
        }
        $this->entityManager->flush();

    }

    /**
     * @throws ORMException
     * @throws \Exception
     */
    #[NoReturn]
    public function onProjectDeleted(ProjectDeletedEvent $event)
    {
        $project = $event->getProject();
        $assessment = $project->getAssessment();
        $this->assessmentRepository->trash($assessment);

        $groupProjects = $this->groupProjectRepository->findBy(['project' => $project->getId()]);
        foreach ($groupProjects as $groupProject) {
            $this->groupProjectRepository->trash($groupProject);
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \Exception('user is not instance of User');
        }
        $noActiveProject = $this->projectService->getCurrentProject() === null;
        if ($noActiveProject) {
            $availableProjects = $this->projectService->getAvailableProjectsForUser($user);
            if (sizeof($availableProjects) > 0) {
                $this->projectService->setCurrentProject($availableProjects[array_key_first($availableProjects)]);
            }
        }
    }

    /**
     * @return void|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    #[NoReturn]
    public function onRequest(RequestEvent $event)
    {
        if ($event->isMainRequest()) {
            $user = $this->security->getUser();
            if ($user instanceof User && !$user->isAdmin()) {
                $disallowedRoutes = ['app_assessment_info'];
                $request = $event->getRequest();
                $currentRoute = $request->get('_route');
                if (in_array($currentRoute, $disallowedRoutes, true)) {
                    $noActiveProject = $this->projectService->getCurrentProject() === null;
                    if ($noActiveProject) {
                        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_dashboard_index')));
                    }
                }
            }
        }
    }
}
