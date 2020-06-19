<?php

namespace Drupal\fusion_connector\ResourceType;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;

/**
 * Provides a repository of JSON:API configurable resource types.
 */
class FusionConnectorResourceTypeRepository extends ResourceTypeRepository {

  const  BundleTypes = ['node', 'taxonomy_term', 'taxonomy_vocabulary'];

  /**
   * Get all the available resource types, after the filtering is applied
   * {@inheritdoc}
   */
  public function getAllAvailableResourceTypes() {

    $user = \Drupal::currentUser();
    $config = \Drupal::config('fusion_connector.settings');
    $disabledLanguages = $config->get('disabled_languages');
    $disabled_entities = $config->get('disabled_entities');
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId(
    );

    $resources = [];
    if (!in_array($currentLanguage, $disabledLanguages)) {
      $allResources = $this->all();
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
            self::BundleTypes
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
   * Get all the available resource types, no filtering is applied
   * {@inheritdoc}
   */
  public function getAllAvailableResourceTypesNoFilters() {

    $resources = [];
    foreach (self::BundleTypes as $value) {
      $resources[$value] = $this->entityTypeBundleInfo->getBundleInfo(
        $value
      );
    }

    return $resources;
  }
}
