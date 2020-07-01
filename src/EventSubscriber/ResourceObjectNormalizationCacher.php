<?php

namespace Drupal\fusion_connector\EventSubscriber;

use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\EventSubscriber\ResourceObjectNormalizationCacher as JsonApiResourceObjectNormalizationCacher;

/**
 * Caches entity normalizations after the response has been sent.
 *
 * @internal
 * @see \Drupal\jsonapi\Normalizer\ResourceObjectNormalizer::getNormalization()
 * @todo Refactor once https://www.drupal.org/node/2551419 lands.
 */
class ResourceObjectNormalizationCacher extends JsonApiResourceObjectNormalizationCacher {

  /**
   * Adds a normalization to be cached after the response has been sent.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $object
   *   The resource object for which to generate a cache item.
   * @param array $normalization_parts
   *   The normalization parts to cache.
   */
  public function saveOnTerminate(
    ResourceObject $object,
    array $normalization_parts
  ) {
    $container = \Drupal::getContainer();
    assert(
      array_keys($normalization_parts) === [
        static::RESOURCE_CACHE_SUBSET_BASE,
        static::RESOURCE_CACHE_SUBSET_FIELDS,
      ]
    );
    $resource_type = $object->getResourceType();
    $key = $resource_type->getTypeName() . ':' . $object->getId();
    if (substr_count(
      \Drupal::request()->getRequestUri(),
      $container->getParameter('fusion_connector.base_path')
    )) {
      $key .= ':fusion';
    }
    $this->toCache[$key] = [$object, $normalization_parts];
  }

  /**
   * Generates a lookup render array for a normalization.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $object
   *   The resource object for which to generate a cache item.
   *
   * @return array
   *   A render array for use with the RenderCache service.
   *
   * @see \Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber::$dynamicPageCacheRedirectRenderArray
   */
  protected static function generateLookupRenderArray(ResourceObject $object) {
    $container = \Drupal::getContainer();
    $lookupRenderArray = [
      '#cache' => [
        'keys' => [$object->getResourceType()->getTypeName(), $object->getId()],
        'bin'  => 'jsonapi_normalizations',
      ],
    ];
    if (substr_count(
      \Drupal::request()->getRequestUri(),
      $container->getParameter('fusion_connector.base_path')
    )) {
      $lookupRenderArray['#cache']['keys'] = ['fusion'];
    }

    return $lookupRenderArray;
  }

}
