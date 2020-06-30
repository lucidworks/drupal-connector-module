<?php

namespace Drupal\fusion_connector\JsonApiResource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiSpec;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Revisions\VersionByRel;
use Drupal\jsonapi\JsonApiResource\ResourceObject as JsonApiResourceObject;
use Drupal\jsonapi\Routing\Routes;
use \Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\fusion_connector\Routing\FusionRoutes;
use Drupal\jsonapi\JsonApiResource\Link;

/**
 * Represents a JSON:API resource object.
 *
 * This value object wraps a Drupal entity so that it can carry a JSON:API
 * resource type object alongside it. It also helps abstract away differences
 * between config and content entities within the JSON:API codebase.
 *
 * @internal JSON:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/jsonapi/issues/3032787
 * @see jsonapi.api.php
 */
class ResourceObject extends JsonApiResourceObject {

  /**
   * Builds a LinkCollection for the given entity.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type of the given entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to build links.
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection $links
   *   (optional) Any extra links for the resource object, if a `self` link is
   *   not provided, one will be automatically added if the resource is
   *   locatable and is not an internal entity.
   *
   * @return \Drupal\jsonapi\JsonApiResource\LinkCollection
   *   The built links.
   */
  protected static function buildLinksFromEntity(
    ResourceType $resource_type,
    EntityInterface $entity,
    LinkCollection $links
  ) {
    if ($resource_type->isLocatable() && !$resource_type->isInternal()) {
      $container = \Drupal::getContainer();
      if (substr_count(
        \Drupal::request()->getRequestUri(),
        $container->getParameter('fusion_connector.base_path')
      )) {
        $self_url = Url::fromRoute(
          FusionRoutes::getRouteName($resource_type, 'individual'),
          ['entity' => $entity->uuid()]
        );
      }
      else {
        $self_url = Url::fromRoute(
          Routes::getRouteName($resource_type, 'individual'),
          ['entity' => $entity->uuid()]
        );
      }

      if ($resource_type->isVersionable()) {
        assert($entity instanceof RevisionableInterface);
        if (!$links->hasLinkWithKey('self')) {
          // If the resource is versionable, the `self` link should be the exact
          // link for the represented version. This helps a client track
          // revision changes and to disambiguate resource objects with the same
          // `type` and `id` in a `version-history` collection.
          $self_with_version_url = $self_url->setOption(
            'query',
            [
              JsonApiSpec::VERSION_QUERY_PARAMETER => 'id:' . $entity->getRevisionId(
                )
            ]
          );
          $links = $links->withLink(
            'self',
            new Link(new CacheableMetadata(), $self_with_version_url, 'self')
          );

          $html_url = $entity->toUrl();
          $links = $links->withLink(
            'html',
            new Link(new CacheableMetadata(), $html_url, 'self')
          );
        }
        if (!$entity->isDefaultRevision()) {
          $latest_version_url = $self_url->setOption(
            'query',
            [JsonApiSpec::VERSION_QUERY_PARAMETER => 'rel:' . VersionByRel::LATEST_VERSION]
          );
          $links = $links->withLink(
            VersionByRel::LATEST_VERSION,
            new Link(
              new CacheableMetadata(),
              $latest_version_url,
              VersionByRel::LATEST_VERSION
            )
          );
        }
        if (!$entity->isLatestRevision()) {
          $working_copy_url = $self_url->setOption(
            'query',
            [JsonApiSpec::VERSION_QUERY_PARAMETER => 'rel:' . VersionByRel::WORKING_COPY]
          );
          $links = $links->withLink(
            VersionByRel::WORKING_COPY,
            new Link(
              new CacheableMetadata(),
              $working_copy_url,
              VersionByRel::WORKING_COPY
            )
          );
        }
      }
      if (!$links->hasLinkWithKey('self')) {
        $links = $links->withLink(
          'self',
          new Link(new CacheableMetadata(), $self_url, 'self')
        );
      }
    }
    return $links;
  }

}
