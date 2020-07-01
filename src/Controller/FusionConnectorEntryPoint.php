<?php

namespace Drupal\fusion_connector\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\jsonapi\Controller\EntryPoint;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\NullIncludedData;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\user\Entity\User;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Controller for the API entry point.
 *
 * @see https://www.drupal.org/project/jsonapi/issues/3032787
 * @see jsonapi.api.php
 * @internal JSON:API maintains no PHP API. The API is the HTTP API. This class
 *           may change at any time and could break any dependencies on it.
 */
class FusionConnectorEntryPoint extends EntryPoint {

  /**
   * Controller to list all the resources.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response object.
   */
  public function index() {

    // Filter the available entities for the current user.
    $resources = \Drupal::service('fusion_connector.repository')->getAllAvailableResourceTypes();

    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user.roles:authenticated'])
      ->addCacheTags(['fusion_resource_types']);

    // Only build URLs for exposed resources.
    $self_link = new Link(
      new CacheableMetadata(),
      Url::fromRoute('fusion.resource_list'),
      'self'
    );
    $urls = array_reduce(
      $resources,
      function (LinkCollection $carry, ResourceType $resource_type) {
        if ($resource_type->isLocatable() || $resource_type->isMutable()) {
          $route_suffix = $resource_type->isLocatable(
          ) ? 'collection' : 'collection.post';
          $url = Url::fromRoute(
            sprintf(
              'fusion.%s.%s',
              $resource_type->getTypeName(),
              $route_suffix
            )
          )->setAbsolute();
          // Using a resource type name in place of a link relation type is not
          // technically valid. However, since it matches the link key, it will
          // not actually be serialized since the rel is omitted if it matches
          // the link key; because of that no client can rely on it. Once an
          // extension relation type is implemented for links to a collection,
          // that should be used instead. Unfortunately, the `collection` link r
          // elation type would not be semantically correct since it would imply
          // that the entrypoint is a *member* of the link target.
          // @todo: implement an extension relation type to signal that this is a primary collection resource.
          $link_relation_type = $resource_type->getTypeName();
          return $carry->withLink(
            $resource_type->getTypeName(),
            new Link(new CacheableMetadata(), $url, $link_relation_type)
          );
        }
        return $carry;
      },
      new LinkCollection(['self' => $self_link])
    );

    $meta = [];
    if ($this->user->isAuthenticated()) {
      $current_user_uuid = User::load($this->user->id())->uuid();
      $meta['links']['me'] = ['meta' => ['id' => $current_user_uuid]];
      $cacheability->addCacheContexts(['user']);
      try {
        $me_url = Url::fromRoute(
          'fusion.user--user.individual',
          ['entity' => $current_user_uuid]
        )
          ->setAbsolute()
          ->toString(TRUE);
        $meta['links']['me']['href'] = $me_url->getGeneratedUrl();
        // The cacheability of the `me` URL is the cacheability of that URL
        // itself and the currently authenticated user.
        $cacheability = $cacheability->merge($me_url);
      }
      catch (RouteNotFoundException $e) {

        // Do not add the link if the route is disabled or marked as internal.
      }

    }

    $response = new ResourceResponse(
      new JsonApiDocumentTopLevel(
        new ResourceObjectData([]),
        new NullIncludedData(),
        $urls,
        $meta
      )
    );
    return $response->addCacheableDependency($cacheability);

  }

}
