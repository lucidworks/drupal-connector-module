<?php

namespace Drupal\fusion_connector;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions of the taxonomy module.
 *
 * @see taxonomy.permissions.yml
 */
class FusionConnectorPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;
  use DeprecatedServicePropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected $deprecatedProperties = ['entityManager' => 'entity.manager'];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle information service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The bundle information service.
   *
   * @var \Drupal\fusion_connector\ResourceType\FusionConnectorResourceTypeRepository
   */
  protected $resource_type;


  /**
   * Constructs a TaxonomyPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   Bundle information service.
   * @param \Drupal\fusion_connector\ResourceType\FusionConnectorResourceTypeRepository $resource_type
   *   Bundle information service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $bundle_info,
    $resource_type
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfo = $bundle_info;
    $this->resource_type = $resource_type;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('jsonapi.resource_type.repository')
    );
  }

  /**
   * Get taxonomy permissions.
   *
   * @return array
   *   Permissions array.
   */
  public function permissions() {

   $types = \Drupal::service('fusion_connector.repository')->getAllAvailableResourceTypesNoFilters();
    $permissions = [];

    foreach ($types as $bundle => $entities) {
      if (count($entities)) {
        foreach ($entities as $entity_type_id => $label) {
          $resource_config_id = sprintf('%s--%s', $bundle, $entity_type_id);
          $permissions += [
            "view fusion_connector $resource_config_id" => [
              'title' => $this->t(
                'View %label',
                ['%label' => $label['label']]
              ),
            ],
          ];
        }
      }
    }
    return $permissions;
  }
}
