<?php

namespace Drupal\fusion_connector\ResourceType;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Provides a repository of JSON:API configurable resource types.
 */
class FusionConnectorResourceTypeRepository {

  const bundleTypes = ['node', 'taxonomy_term', 'taxonomy_vocabulary'];

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The bundle manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * ResourceTypeConverter constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface            $entity_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(
    ResourceTypeRepositoryInterface $resource_type_repository,
    EntityTypeBundleInfoInterface $entity_bundle_info
  ) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->entityTypeBundleInfo = $entity_bundle_info;
  }

  /**
   * Get all the available resource types, after the filtering is applied
   * {@inheritdoc}
   */
  public function getAllAvailableResourceTypes() {

    $user = \Drupal::currentUser();
    $config = \Drupal::config('fusion_connector.settings');
    $disabledLanguages = $config->get('disabled_languages') ? $config->get(
      'disabled_languages'
    ) : [];
    $disabled_entities = $config->get('disabled_entities') ? $config->get(
      'disabled_entities'
    ) : [];
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId(
    );

    $resources = [];
    if (!in_array($currentLanguage, $disabledLanguages)) {
      $allResources = $this->resourceTypeRepository->all();
      foreach ($allResources as $key => $resource) {
        $resource_config_id = sprintf(
          '%s--%s',
          $resource->getEntityTypeId(),
          $resource->getBundle()
        );
        $disabledLanguages = $config->get(
            'disabled_entity_type_languages'
          )[$resource_config_id] ?? [];
        if (!in_array(
            $currentLanguage,
            $disabledLanguages
          ) && !$resource->isInternal() && in_array(
            $resource->getEntityTypeId(),
            self::bundleTypes
          ) && $user->hasPermission(
            'view fusion_connector ' . $key
          ) && !in_array($key, $disabled_entities)) {
          $resources[$key] = $resource;
        }
      }
    }
    else {
      $resources = [];
    }

    return $resources;
  }

  /**
   * Get all the enabled resource types,
   * {@inheritdoc}
   */
  public function getAllEnabledResourceTypes() {
    $config = \Drupal::config('fusion_connector.settings');
    $disabled_entities = $config->get('disabled_entities') ? $config->get(
      'disabled_entities'
    ) : [];

    $resources = [];

    $allResources = $this->resourceTypeRepository->all();
    foreach ($allResources as $key => $resource) {
      if (!$resource->isInternal() && in_array(
          $resource->getEntityTypeId(),
          self::bundleTypes
        ) && !in_array($key, $disabled_entities)) {
        $resources[$key] = $resource;
      }
    }

    return $resources;
  }

  /**
   * Get all the available resource types, no filtering is applied
   * {@inheritdoc}
   */
  public function getAllAvailableResourceTypesNoFilters() {
    $config = \Drupal::config('fusion_connector.settings');
    $disabled_entities = $config->get('disabled_entities') ? $config->get(
      'disabled_entities'
    ) : [];
    $resources = [];

    foreach (self::bundleTypes as $value) {
      $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo(
        $value
      );
      if (count($bundleInfo)) {
        foreach ($bundleInfo as $bundle => $entitiesArray) {
          foreach ($entitiesArray as $key => $label) {
            $resource_config_id = sprintf(
              '%s--%s',
              $value,
              $bundle
            );
            //hide config for disabled entities
            if (!in_array($resource_config_id, $disabled_entities)) {
              $resources[$value][$bundle] = $entitiesArray;
            }
          }
        }
      }
    }

    return $resources;
  }
}
