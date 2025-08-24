<?php

declare(strict_types=1);

namespace App\Service\Processing;

use App\Entity\Abstraction\AbstractEntity;
use App\Entity\Activity;
use App\Entity\Answer;
use App\Entity\AnswerSet;
use App\Entity\BusinessFunction;
use App\Entity\MaturityLevel;
use App\Entity\Metamodel;
use App\Entity\Practice;
use App\Entity\PracticeLevel;
use App\Entity\Question;
use App\Entity\Stream;
use App\Enum\ModelEntitySyncEnum;
use App\Repository\Abstraction\AbstractRepository;
use App\Repository\ActivityRepository;
use App\Repository\AnswerRepository;
use App\Repository\AnswerSetRepository;
use App\Repository\BusinessFunctionRepository;
use App\Repository\MaturityLevelRepository;
use App\Repository\PracticeLevelRepository;
use App\Repository\PracticeRepository;
use App\Repository\QuestionRepository;
use App\Repository\StreamRepository;
use Doctrine\ORM\EntityManagerInterface;

class ModelToDbSyncer
{
    protected Metamodel $metamodel;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BusinessFunctionRepository $businessFunctionRepository,
        private readonly PracticeRepository $practiceRepository,
        private readonly StreamRepository $streamRepository,
        private readonly MaturityLevelRepository $maturityLevelRepository,
        private readonly PracticeLevelRepository $practiceLevelRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly AnswerSetRepository $answerSetRepository,
        private readonly AnswerRepository $answerRepository,
        private readonly QuestionRepository $questionRepository,
    ) {
    }

    public function syncBusinessFunction(
        string $externalId,
        string $name,
        string $description,
        int $order,
    ): ModelEntitySyncEnum {
        $isModified = false;

        /** @var BusinessFunction $businessFunctionEntity */
        $businessFunctionEntity = $this->createEntityIfNotExists(BusinessFunction::class, $externalId, $this->businessFunctionRepository);

        if ($this->metamodel->getId() !== null && $this->arePropertiesDifferent($businessFunctionEntity->getMetamodel()?->getId(), $this->metamodel->getId())) {
            $businessFunctionEntity->setMetamodel($this->metamodel);
            $isModified = true;
        }

        $oldName = $businessFunctionEntity->getName();
        $newName = $this->replaceCharacters($name);
        if ($this->arePropertiesDifferent($oldName, $newName)) {
            $businessFunctionEntity->setName($newName);
            $isModified = true;
        }

        $oldDescription = $businessFunctionEntity->getDescription();
        $newDescription = $this->replaceCharacters($description);
        if ($this->arePropertiesDifferent($oldDescription, $newDescription)) {
            $businessFunctionEntity->setDescription($newDescription);
            $isModified = true;
        }

        $oldOrder = $businessFunctionEntity->getOrder();
        $newOrder = $order;
        if ($this->arePropertiesDifferent($oldOrder, $newOrder)) {
            $businessFunctionEntity->setOrder($newOrder);
            $isModified = true;
        }

        $entityCreatedAt = $businessFunctionEntity->getCreatedAt();
        $this->entityManager->persist($businessFunctionEntity);

        if ($entityCreatedAt === null) {
            return ModelEntitySyncEnum::ADDED;
        } elseif ($isModified) {
            return ModelEntitySyncEnum::MODIFIED;
        }

        return ModelEntitySyncEnum::UNTOUCHED;
    }

    /**
     * @throws \Exception
     */
    public function syncSecurityPractice(
        string $externalId,
        string $businessFunctionExternalId,
        string $name,
        string $shortName,
        string $description,
        string $longDescription,
        int $order
    ): ModelEntitySyncEnum {
        $isModified = false;

        /** @var Practice $practiceEntity */
        $practiceEntity = $this->createEntityIfNotExists(Practice::class, $externalId, $this->practiceRepository);

        $oldBusinessFunctionId = $practiceEntity->getBusinessFunction()?->getExternalId() ?? '';
        $newBusinessFunctionId = $businessFunctionExternalId;
        if ($this->arePropertiesDifferent($oldBusinessFunctionId, $newBusinessFunctionId)) {
            $oldBusinessFunctionEntity = $practiceEntity->getBusinessFunction();
            $practiceEntity->setBusinessFunction($this->businessFunctionRepository->findOneBy(criteria: ['externalId' => $newBusinessFunctionId], metamodel: $this->metamodel));
            $isModified = $oldBusinessFunctionEntity !== $practiceEntity->getBusinessFunction();
        }

        $oldName = $practiceEntity->getName();
        $newName = $this->replaceCharacters($name);
        if ($this->arePropertiesDifferent($oldName, $newName)) {
            $practiceEntity->setName($newName);
            $isModified = true;
        }

        $oldShortName = $practiceEntity->getShortName();
        $newShortName = $this->replaceCharacters($shortName);
        if ($this->arePropertiesDifferent($oldShortName, $newShortName)) {
            $practiceEntity->setShortName($newShortName);
            $isModified = true;
        }

        $oldShortDesc = $practiceEntity->getShortDescription();
        $newShortDesc = $this->replaceCharacters($description);
        if ($this->arePropertiesDifferent($oldShortDesc, $newShortDesc)) {
            $practiceEntity->setShortDescription($newShortDesc);
            $isModified = true;
        }

        $oldLongDesc = $practiceEntity->getLongDescription();
        $newLongDesc = $this->replaceCharacters($longDescription);
        if ($this->arePropertiesDifferent($oldLongDesc, $newLongDesc)) {
            $practiceEntity->setLongDescription($newLongDesc);
            $isModified = true;
        }

        $oldOrder = $practiceEntity->getOrder();
        $newOrder = $order;
        if ($this->arePropertiesDifferent($oldOrder, $newOrder)) {
            $practiceEntity->setOrder($newOrder);
            $isModified = true;
        }

        $entityCreatedAt = $practiceEntity->getCreatedAt();
        $this->entityManager->persist($practiceEntity);

        if ($entityCreatedAt === null) {
            return ModelEntitySyncEnum::ADDED;
        } elseif ($isModified) {
            return ModelEntitySyncEnum::MODIFIED;
        }

        return ModelEntitySyncEnum::UNTOUCHED;
    }

    public function syncStream(string $externalId, string $practiceExternalId, string $name, string $description, int $order): ModelEntitySyncEnum
    {
        $isModified = false;

        /** @var Stream $streamEntity */
        $streamEntity = $this->createEntityIfNotExists(Stream::class, $externalId, $this->streamRepository);

        $oldPracticeId = $streamEntity->getPractice()?->getExternalId() ?? '';
        $newPracticeId = $practiceExternalId;
        if ($this->arePropertiesDifferent($oldPracticeId, $newPracticeId)) {
            $oldPracticeEntity = $streamEntity->getPractice();
            $streamEntity->setPractice($this->practiceRepository->findOneBy(['externalId' => $newPracticeId], null, true));
            $isModified = $oldPracticeEntity !== $streamEntity->getPractice();
        }

        $oldName = $streamEntity->getName();
        $newName = $this->replaceCharacters($name);
        if ($this->arePropertiesDifferent($oldName, $newName)) {
            $streamEntity->setName($newName);
            $isModified = true;
        }

        $oldDescription = $streamEntity->getDescription();
        $newDescription = $this->replaceCharacters($description);
        if ($this->arePropertiesDifferent($oldDescription, $newDescription)) {
            $streamEntity->setDescription($newDescription);
            $isModified = true;
        }

        $oldOrder = $streamEntity->getOrder();
        $newOrder = $order;
        if ($this->arePropertiesDifferent($oldOrder, $newOrder)) {
            $streamEntity->setOrder($newOrder);
            $isModified = true;
        }

        $entityCreatedAt = $streamEntity->getCreatedAt();
        $this->entityManager->persist($streamEntity);

        if ($entityCreatedAt === null) {
            return ModelEntitySyncEnum::ADDED;
        } elseif ($isModified) {
            return ModelEntitySyncEnum::MODIFIED;
        }

        return ModelEntitySyncEnum::UNTOUCHED;
    }

    public function syncMaturityLevel(string $externalId, int $levelNumber, string $description): ModelEntitySyncEnum
    {
        $isModified = false;

        /** @var MaturityLevel $maturityLevelEntity */
        $maturityLevelEntity = $this->createEntityIfNotExists(MaturityLevel::class, $externalId, $this->maturityLevelRepository);

        $oldLevel = $maturityLevelEntity->getLevel();
        $newLevel = $levelNumber;
        if ($this->arePropertiesDifferent($oldLevel, $newLevel)) {
            $maturityLevelEntity->setLevel($newLevel);
            $isModified = true;
        }

        $oldDescription = $maturityLevelEntity->getDescription();
        $newDescription = $this->replaceCharacters($description);
        if ($this->arePropertiesDifferent($oldDescription, $newDescription)) {
            $maturityLevelEntity->setDescription($newDescription);
            $isModified = true;
        }

        $entityCreatedAt = $maturityLevelEntity->getCreatedAt();
        $this->entityManager->persist($maturityLevelEntity);

        if ($entityCreatedAt === null) {
            return ModelEntitySyncEnum::ADDED;
        } elseif ($isModified) {
            return ModelEntitySyncEnum::MODIFIED;
        }

        return ModelEntitySyncEnum::UNTOUCHED;
    }

    public function syncPracticeLevel(string $externalId, string $practiceId, string $maturityLevelId, string $objective): ModelEntitySyncEnum
    {
        $isModified = false;

        /** @var PracticeLevel $practiceLevelEntity */
        $practiceLevelEntity = $this->createEntityIfNotExists(PracticeLevel::class, $externalId, $this->practiceLevelRepository);

        $oldPracticeId = $practiceLevelEntity->getPractice()?->getExternalId() ?? '';
        $newPracticeId = $practiceId;
        if ($this->arePropertiesDifferent($oldPracticeId, $newPracticeId)) {
            $oldPractice = $practiceLevelEntity->getPractice();
            $practiceLevelEntity->setPractice($this->practiceRepository->findOneBy(['externalId' => $newPracticeId], null, true));
            $isModified = $oldPractice !== $practiceLevelEntity->getPractice();
        }

        $oldMaturityLevelId = $practiceLevelEntity->getMaturityLevel()?->getExternalId() ?? '';
        $newMaturityLevelId = $maturityLevelId;
        if ($this->arePropertiesDifferent($oldMaturityLevelId, $newMaturityLevelId)) {
            $oldMaturityLevelEntity = $practiceLevelEntity->getMaturityLevel();
            $practiceLevelEntity->setMaturityLevel($this->maturityLevelRepository->findOneBy(['externalId' => $newMaturityLevelId]));
            $isModified = $oldMaturityLevelEntity !== $practiceLevelEntity->getMaturityLevel();
        }

        $oldObjective = $practiceLevelEntity->getObjective();
        $newObjective = $this->replaceCharacters($objective);
        if ($this->arePropertiesDifferent($oldObjective, $newObjective)) {
            $practiceLevelEntity->setObjective($newObjective);
            $isModified = true;
        }

        $entityCreatedAt = $practiceLevelEntity->getCreatedAt();
        $this->entityManager->persist($practiceLevelEntity);

        if ($entityCreatedAt === null) {
            return ModelEntitySyncEnum::ADDED;
        } elseif ($isModified) {
            return ModelEntitySyncEnum::MODIFIED;
        }

        return ModelEntitySyncEnum::UNTOUCHED;
    }

    public function syncActivity(
        string $externalId,
        string $streamId,
        string $title,
        string $benefit,
        string $shortDescription,
        string $longDescription,
        ?string $practiceLevelId = null,
        ?string $notes = null
    ): ModelEntitySyncEnum {
        $isModified = false;
        /** @var Activity $activityEntity */
        $activityEntity = $this->createEntityIfNotExists(Activity::class, $externalId, $this->activityRepository);

        $oldStreamId = $activityEntity->getStream()?->getExternalId() ?? '';
        $newStreamId = $streamId;
        if ($this->arePropertiesDifferent($oldStreamId, $newStreamId)) {
            $oldStreamEntity = $activityEntity->getStream();
            $activityEntity->setStream($this->streamRepository->findOneBy(['externalId' => $newStreamId], null, true));
            $isModified = $oldStreamEntity !== $activityEntity->getStream();
        }

        if ($practiceLevelId !== null) {
            $oldPracticeLevelId = $activityEntity->getPracticeLevel()?->getExternalId() ?? '';
            $newPracticeLevelId = $practiceLevelId;
            if ($this->arePropertiesDifferent($oldPracticeLevelId, $newPracticeLevelId)) {
                $oldPracticeLevelEntity = $activityEntity->getPracticeLevel();
                $activityEntity->setPracticeLevel($this->practiceLevelRepository->findOneBy(['externalId' => $newPracticeLevelId]));
                $isModified = $oldPracticeLevelEntity !== $activityEntity->getPracticeLevel();
            }
        }

        $oldTitle = $activityEntity->getTitle();
        $newTitle = $this->replaceCharacters($title);
        if ($this->arePropertiesDifferent($oldTitle, $newTitle)) {
            $activityEntity->setTitle($newTitle);
            $isModified = true;
        }

        $oldBenefit = $activityEntity->getBenefit();
        $newBenefit = $this->replaceCharacters($benefit);
        if ($this->arePropertiesDifferent($oldBenefit, $newBenefit)) {
            $activityEntity->setBenefit($newBenefit);
            $isModified = true;
        }

        $oldShortDesc = $activityEntity->getShortDescription();
        $newShortDesc = $this->replaceCharacters($shortDescription);
        if ($this->arePropertiesDifferent($oldShortDesc, $newShortDesc)) {
            $activityEntity->setShortDescription($newShortDesc);
            $isModified = true;
        }

        $oldLongDesc = $activityEntity->getLongDescription();
        $newLongDesc = $this->replaceCharacters($longDescription);
        if ($this->arePropertiesDifferent($oldLongDesc, $newLongDesc)) {
            $activityEntity->setLongDescription($newLongDesc);
            $isModified = true;
        }

        if ($notes !== null) {
            $oldNotes = $activityEntity->getNotes();
            $newNotes = $this->replaceCharacters($notes);
            if ($this->arePropertiesDifferent($oldNotes, $newNotes)) {
                $activityEntity->setNotes($newNotes);
                $isModified = true;
            }
        }

        $entityCreatedAt = $activityEntity->getCreatedAt();
        $this->entityManager->persist($activityEntity);

        if ($entityCreatedAt === null) {
            return ModelEntitySyncEnum::ADDED;
        } elseif ($isModified) {
            return ModelEntitySyncEnum::MODIFIED;
        }

        return ModelEntitySyncEnum::UNTOUCHED;
    }

    public function syncAnswerSet(string $externalId): ModelEntitySyncEnum
    {
        /** @var AnswerSet $answerSetEntity */
        $answerSetEntity = $this->createEntityIfNotExists(AnswerSet::class, $externalId, $this->answerSetRepository);

        $entityCreatedAt = $answerSetEntity->getCreatedAt();
        $this->entityManager->persist($answerSetEntity);

        if ($entityCreatedAt === null) {
            return ModelEntitySyncEnum::ADDED;
        }

        return ModelEntitySyncEnum::UNTOUCHED;
    }

    public function syncAnswer(AnswerSet $answerSet, int $order, string $text, float $value, ?float $weight = null): ModelEntitySyncEnum
    {
        $isModified = false;

        /** @var Answer $answerEntity */
        $answerEntity = $this->createAnswerIfNotExist($answerSet, $order, $this->answerRepository);
        $answerEntity->setAnswerSet($answerSet);

        $oldOrder = $answerEntity->getOrder();
        $newOrder = $this->replaceCharacters((string) $order);
        if ($this->arePropertiesDifferent($oldOrder, $newOrder)) {
            $answerEntity->setOrder((int) $newOrder);
            $isModified = true;
        }

        $oldText = $answerEntity->getText();
        $newText = $this->replaceCharacters($text);
        if ($this->arePropertiesDifferent($oldText, $newText)) {
            $answerEntity->setText($newText);
            $isModified = true;
        }

        $oldValue = $answerEntity->getValue();
        $newValue = $value;
        if ($this->arePropertiesDifferent($oldValue, $newValue)) {
            $answerEntity->setValue($newValue);
            $isModified = true;
        }

        if ($weight !== null) {
            $oldWeight = $answerEntity->getWeight();
            $newWeight = $weight;
            if ($this->arePropertiesDifferent($oldWeight, $newWeight)) {
                $answerEntity->setWeight($newWeight);
                $isModified = true;
            }
        }

        $entityCreatedAt = $answerEntity->getCreatedAt();
        $this->entityManager->persist($answerEntity);

        if ($entityCreatedAt === null) {
            return ModelEntitySyncEnum::ADDED;
        } elseif ($isModified) {
            return ModelEntitySyncEnum::MODIFIED;
        }

        return ModelEntitySyncEnum::UNTOUCHED;
    }

    public function syncQuestion(string $externalId, string $activityId, string $text, array $quality, string $answerSetId, ?int $order = null): ModelEntitySyncEnum
    {
        $isModified = false;

        /** @var Question $questionEntity */
        $questionEntity = $this->createEntityIfNotExists(Question::class, $externalId, $this->questionRepository);

        $oldActivityId = $questionEntity->getActivity()?->getExternalId() ?? '';
        $newActivityId = $activityId;
        if ($this->arePropertiesDifferent($oldActivityId, $newActivityId)) {
            $oldActivityEntity = $questionEntity->getActivity();
            $questionEntity->setActivity($this->activityRepository->findOneBy(['externalId' => $newActivityId]));
            $isModified = $oldActivityEntity !== $questionEntity->getActivity();
        }

        $oldAnswerSetId = $questionEntity->getAnswerSet()?->getExternalId() ?? '';
        $newAnswerSetId = $answerSetId;
        if ($this->arePropertiesDifferent($oldAnswerSetId, $newAnswerSetId)) {
            $oldAnswerSetEntity = $questionEntity->getAnswerSet();
            $questionEntity->setAnswerSet($this->answerSetRepository->findOneBy(['externalId' => $newAnswerSetId]));
            $isModified = $oldAnswerSetEntity !== $questionEntity->getAnswerSet();
        }

        $oldText = $questionEntity->getText();
        $newText = $this->replaceCharacters($text);
        if ($this->arePropertiesDifferent($oldText, $newText)) {
            $questionEntity->setText($newText);
            $isModified = true;
        }

        if ($order !== null) {
            $oldOrder = $questionEntity->getOrder();
            $newOrder = $order;
            if ($this->arePropertiesDifferent($oldOrder, $newOrder)) {
                $questionEntity->setOrder($newOrder);
                $isModified = true;
            }
        }

        $oldQuality = $questionEntity->getQuality();
        $newQuality = $this->replaceCharacters(implode("\n", $quality));
        if ($this->arePropertiesDifferent($oldQuality, $newQuality)) {
            $questionEntity->setQuality($newQuality);
            $isModified = true;
        }

        $entityCreatedAt = $questionEntity->getCreatedAt();
        $this->entityManager->persist($questionEntity);

        if ($entityCreatedAt === null) {
            return ModelEntitySyncEnum::ADDED;
        } elseif ($isModified) {
            return ModelEntitySyncEnum::MODIFIED;
        }

        return ModelEntitySyncEnum::UNTOUCHED;
    }

    private function createAnswerIfNotExist(AnswerSet $answerSet, int $order, AbstractRepository $repository, string $className = Answer::class): AbstractEntity
    {
        $entity = $repository->findOneBy(['answerSet' => $answerSet, 'order' => $order]);
        /** @var Answer|null $entity */
        if ($entity === null) {
            $entity = new $className();
            $entity->setOrder($order);
        }

        return $entity;
    }

    /**
     * @throws \Exception
     */
    private function createEntityIfNotExists(string $className, string $id, AbstractRepository $repository): AbstractEntity
    {
        if ($repository instanceof StreamRepository || $repository instanceof PracticeRepository) {
            $entity = $repository->findOneBy(['externalId' => $id], null, true);
        } elseif ($repository instanceof BusinessFunctionRepository) {
            $entity = $repository->findOneBy(criteria: ['externalId' => $id], metamodel: $this->metamodel);
        } else {
            $entity = $repository->findOneBy(['externalId' => $id]);
        }
        if ($entity === null) {
            $entity = new $className();
            $entity->setExternalId($id);
        }

        return $entity;
    }

    private function replaceCharacters(?string $text): string|null
    {
        return $text === null ? $text : str_replace('&', 'and', $text);
    }

    private function arePropertiesDifferent(mixed $oldProp, mixed $newProp): bool
    {
        return (string) $oldProp !== (string) $newProp;
    }

    public function setMetamodel(Metamodel $metamodel): void
    {
        $this->metamodel = $metamodel;
    }

    protected function deleteEntities(array $entitiesForRemoval): int
    {
        $deleted = 0;
        /** @var AbstractEntity $entity */
        foreach ($entitiesForRemoval as $entity) {
            $this->entityManager->remove($entity);
            ++$deleted;
        }

        return $deleted;
    }
}
