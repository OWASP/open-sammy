<?php
declare(strict_types=1);

namespace App\Traits\UtilsBundle;

use App\Interface\EntityInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait CrudAjaxModifyTrait
{
    public function abstractAjaxModify(Request $request, EntityInterface $entity): JsonResponse
    {
        try {
            $value = '';
            if ($request->request->has('value')) {
                $value = $request->request->all()['value'];
            }
            $name = $request->request->get('name');
            $type = $request->request->get('type');
            if (in_array($name, $entity->getModifiableFields(), true)) {
                $value = $this->convertValue($name, $type, $value, $entity);
                $entity->{'set'.ucfirst($name)}($value); // @phpstan-ignore-line
                $validationErrors = $this->validator->validateProperty($entity, $name);
                if (sizeof($validationErrors) === 0) {
                    $em = $this->managerRegistry->getManager();
                    $em->persist($entity);
                    $em->flush();
                    $jsonResult = ['status' => 'ok'];
                    if (strtolower($entity->getEntityName(true)) !== 'user') {
                        $entityUpdatedEvent = '\\App\Event\\Admin\\Update\\'.ucfirst($entity->getEntityName(true)).'UpdatedEvent';
                        if (class_exists($entityUpdatedEvent)) {
                            $this->eventDispatcher->dispatch(new $entityUpdatedEvent($request, $entity));
                        }
                    }
                } else {
                    $jsonResult = ['status' => 'error'];
                    foreach ($validationErrors as $error) {
                        $jsonResult['msg'] = $error->getMessage();
                    }
                }

                return new JsonResponse($jsonResult);
            }

            return new JsonResponse(['status' => 'error', 'msg' => 'The field is readonly!'], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $t) {
            $this->logger->error('abstractAjaxModify failed', ['exception' => $t]);

            return new JsonResponse([
                'status' => 'error',
                'msg' => $this->translator->trans('admin.general.exception_message', ['message' => '']),
            ]);
        }
    }

    private function convertValue(string $name, ?string $type, string|array $value, EntityInterface $entity): mixed
    {
        if ($type === 'date' || $type === 'datetime' || $type === 'time') {
            if ($value === '') {
                $value = null;
            } else {
                $value = new \DateTime($value);
            }
        } elseif ($type === 'boolean') {
            $value = ($value === 'true');
        } elseif (class_exists('\\App\Entity\\'.ucfirst($name))) {
            $value = $this->managerRegistry->getManager()->find('\\App\Entity\\'.ucfirst($name), $value);
        } else {
            $method = new \ReflectionMethod(get_class($entity), 'get'.ucfirst($name));
            /** @var \ReflectionNamedType $returnType */
            $returnType = $method->getReturnType();
            $returnTypeName = $returnType->getName();
            if (enum_exists($returnTypeName)) {
                $enum = new \ReflectionEnum($returnTypeName);
                $value = $enum->getMethod('from')->invoke(null, $value);
            } elseif (class_exists($returnTypeName)) {
                $value = $this->managerRegistry->getManager()->find($returnTypeName, $value);
            } elseif (gettype($entity->{'get'.ucfirst($name)}()) !== 'NULL') { // @phpstan-ignore-line
                settype($value, gettype($entity->{'get'.ucfirst($name)}())); // @phpstan-ignore-line
            }
        }

        return $value;
    }
}
