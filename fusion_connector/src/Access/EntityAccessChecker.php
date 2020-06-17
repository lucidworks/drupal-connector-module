<?php

namespace Drupal\fusion_connector\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\fusion_connector\JsonApiResource\LabelOnlyResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\Access\EntityAccessChecker as JsonApiEntityAccessChecker;
use Drupal\jsonapi\JsonApiSpec;

/**
 * Checks access to entities.
 *
 * JSON:API needs to check access to every single entity type. Some entity types
 * have non-standard access checking logic. This class centralizes entity access
 * checking logic.
 *
 * @see      https://www.drupal.org/project/jsonapi/issues/3032787
 * @see      jsonapi.api.php
 * @internal JSON:API maintains no PHP API. The API is the HTTP API. This class
 *           may change at any time and could break any dependencies on it.
 *
 */
class EntityAccessChecker extends JsonApiEntityAccessChecker {

  /**
   * Get the object to normalize and the access based on the provided entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface   $entity
   *   The entity to test access for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The account with which access should be checked. Defaults to
   *   the current user.
   *
   * @return \Drupal\jsonapi\JsonApiResource\ResourceObject|\Drupal\jsonapi\JsonApiResource\LabelOnlyResourceObject|\Drupal\jsonapi\Exception\EntityAccessDeniedHttpException
   *   The ResourceObject, a LabelOnlyResourceObject or an
   *   EntityAccessDeniedHttpException object if neither is accessible. All
   *   three possible return values carry the access result cacheability.
   */
  public function getAccessCheckedResourceObject(
    EntityInterface $entity,
    AccountInterface $account = NULL
  ) {
    $config = \Drupal::config('fusion_connector.settings');
    $resource_config_id = sprintf('%s--%s', $entity->getEntityTypeId(), $entity->bundle());
    $disabledEntityTypeLanguages = $config->get('disabled_entity_type_languages')[$resource_config_id] ?? [];
    $disabledLanguages = array_unique(array_merge($config->get('disabled_languages') ?? [], $disabledEntityTypeLanguages)) ;

    $account = $account ? : $this->currentUser;
    $resource_type = $this->resourceTypeRepository->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );
    $entity = $this->entityRepository->getTranslationFromContext(
      $entity,
      NULL,
      ['operation' => 'entity_upcast']
    );
    $access = $this->checkEntityAccess($entity, 'view', $account);
    $access = AccessResult::neutral()
      ->addCacheContexts([
        'languages:'.LanguageInterface::TYPE_CONTENT,
        'url'
      ])
      ->orIf($access);

    $entity->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      // If this is the default revision or the entity is not revisionable, then
      // check access to the entity label. Revision support is all or nothing.
      if (!$entity->getEntityType()->isRevisionable(
        ) || $entity->isDefaultRevision()) {
        $label_access = $entity->access('view label', NULL, TRUE);
        $entity->addCacheableDependency($label_access);
        if ($label_access->isAllowed()) {
          return LabelOnlyResourceObject::createFromEntity(
            $resource_type,
            $entity
          );
        }
        $access = $access->orIf($label_access);
      }
      return new EntityAccessDeniedHttpException(
        $entity,
        $access,
        '/data',
        'The current user is not allowed to GET the selected resource.'
      );
    }
    $container = \Drupal::getContainer();
    if (substr_count(
      \Drupal::request()->getRequestUri(),
      $container->getParameter('fusion_connector.base_path')
    )) {
      if (in_array($entity->language()->getId(), $disabledLanguages)) {
        $access = $access->andIf(new AccessResultForbidden());
        $entity->addCacheableDependency($access);

        return new EntityAccessDeniedHttpException(
          $entity,
          $access,
          '/data',
          'The current user is not allowed to GET the selected resource.'
        );
      }

      return \Drupal\fusion_connector\JsonApiResource\ResourceObject::createFromEntity(
        $resource_type,
        $entity
      );
    }
    else {
      return ResourceObject::createFromEntity($resource_type, $entity);
    }
  }

  /**
   * Checks access to the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface   $entity
   *   The entity for which access should be evaluated.
   * @param string                                $operation
   *   The entity operation for which access should be evaluated.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The account with which access should be checked. Defaults to
   *   the current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|\Drupal\Core\Access\AccessResultReasonInterface
   *   The access check result.
   */
  public function checkEntityAccess(
    EntityInterface $entity,
    $operation,
    AccountInterface $account
  ) {
    $access = $entity->access($operation, $account, TRUE);

    if ($entity->getEntityType()->isRevisionable()) {
      $access = AccessResult::neutral()->addCacheContexts(
        ['url.query_args:' . JsonApiSpec::VERSION_QUERY_PARAMETER]
      )->orIf($access);
      if (!$entity->isDefaultRevision()) {
        assert(
          $operation === 'view',
          'JSON:API does not yet support mutable operations on revisions.'
        );
        $revision_access = $this->checkRevisionViewAccess($entity, $account);
        $access = $access->andIf($revision_access);
        // The revision access reason should trump the primary access reason.
        if (!$access->isAllowed()) {
          $reason = $access instanceof AccessResultReasonInterface ? $access->getReason(
          ) : '';
          $access->setReason(
            trim(
              'The user does not have access to the requested version. ' . $reason
            )
          );
        }
      }
    }

    //check fusion access
    $config = \Drupal::config('fusion_connector.settings');
    $fusionUserRoleAccess = $config->get('user_role_access');

    $userHasAccess = FALSE;
    $userRoles = $account->getRoles();
    foreach ($userRoles as $userRole) {
      if (in_array($entity->bundle(), $fusionUserRoleAccess[$userRole])) {
        $userHasAccess = TRUE;
      }
    }

    //if no access found, return forbidden
    if (!$userHasAccess) {
      return AccessResult::forbidden(
        'In fusion connector have no access to this resource!'
      );
    }

    return $access;
  }
}
