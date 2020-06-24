<?php

namespace Drupal\fusion_connector\Routing;

use Drupal;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

class FusionRoutes extends Routes {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository'),
      $container->getParameter('authentication_providers'),
      $container->getParameter('fusion_connector.base_path')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = new RouteCollection();
    $upload_routes = new RouteCollection();

    // JSON:API's routes: entry point + routes for every resource type.
    $resources = \Drupal::service('fusion_connector.repository')->getAllEnabledResourceTypes();

    //get all the relatable resource types for the selected entities
    $selectedResourcesTypeNames = [];
    foreach ($resources as $resource) {
      $this->getRecursiveRelatableResourceTypes(
        $resource,
        $selectedResourcesTypeNames,
        $resources
      );
    }

    foreach ($resources as $resource_type) {
      $routes->addCollection(
        static::getRoutesForResourceType($resource_type, $this->jsonApiBasePath)
      );
      $upload_routes->addCollection(
        static::getFileUploadRoutesForResourceType(
          $resource_type,
          $this->jsonApiBasePath
        )
      );
    }
    $routes->add(
      'fusion.resource_list',
      static::getEntryPointRoute($this->jsonApiBasePath)
    );

    // Require the JSON:API media type header on every route, except on file
    // upload routes, where we require `application/octet-stream`.
    $routes->addRequirements(['_content_type_format' => 'api_json']);
    $upload_routes->addRequirements(['_content_type_format' => 'bin']);

    $routes->addCollection($upload_routes);

    // Enable all available authentication providers.
    $routes->addOptions(['_auth' => $this->providerIds]);

    // Flag every route as belonging to the JSON:API module.
    $routes->addDefaults([static::JSON_API_ROUTE_FLAG_KEY => TRUE]);

    // All routes serve only the JSON:API media type.
    $routes->addRequirements(['_format' => 'api_json']);

    return $routes;
  }

  /**
   * Get all the relatable resource types for the selected entities
   *
   * @param ResourceType $resource
   * @param array        $selectedResourcesTypeNames
   * @param array        $resources
   */
  public function getRecursiveRelatableResourceTypes(
    $resource,
    &$selectedResourcesTypeNames,
    &$resources
  ) {
    $includeRelatableResourceTypes = $resource->getRelatableResourceTypes();
    if (count($includeRelatableResourceTypes)) {
      foreach ($includeRelatableResourceTypes as $includeRelatableResourceTypeArray) {
        foreach ($includeRelatableResourceTypeArray as $includeRelatableResourceType) {
          if (!in_array(
            $includeRelatableResourceType->getTypeName(),
            $selectedResourcesTypeNames
          )) {
            $selectedResourcesTypeNames[] = $includeRelatableResourceType->getTypeName(
            );
            $resources[$includeRelatableResourceType->getTypeName(
            )] = $includeRelatableResourceType;
            $this->getRecursiveRelatableResourceTypes(
              $includeRelatableResourceType,
              $selectedResourcesTypeNames,
              $resources
            );
          }
        }
      }
    }
  }

  /**
   * Get a unique route name for the JSON:API resource type and route type.
   *
   * @param ResourceType $resource_type
   *   The resource type for which the route collection should be created.
   * @param string       $route_type
   *   The route type. E.g. 'individual' or 'collection'.
   *
   * @return string
   *   The generated route name.
   */
  public static function getRouteName(
    ResourceType $resource_type,
    $route_type
  ) {
    return sprintf('fusion.%s.%s', $resource_type->getTypeName(), $route_type);
  }

  /**
   * Get a unique route name for the file upload resource type and route type.
   *
   * @param ResourceType $resource_type
   *   The resource type for which the route collection should be created.
   * @param string       $route_type
   *   The route type. E.g. 'individual' or 'collection'.
   *
   * @return string
   *   The generated route name.
   */
  protected static function getFileUploadRouteName(
    ResourceType $resource_type,
    $route_type
  ) {
    return sprintf(
      'fusion.%s.%s.%s',
      $resource_type->getTypeName(),
      'file_upload',
      $route_type
    );
  }
}
