<?php

namespace Drupal\fusion_connector\JsonApiResource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiSpec;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\Relationship as JsonApiRelationship;
use Drupal\fusion_connector\Routing\FusionRoutes as Routes;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\Link;

/**
 * Represents references from one resource object to other resource object(s).
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/jsonapi/issues/3032787
 * @see jsonapi.api.php
 */
class Relationship extends JsonApiRelationship {

  /**
   * Builds a LinkCollection for the given entity reference field.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $context
   *   The context resource object of the relationship object.
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field
   *   The entity reference field from which to create the links.
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection $links
   *   Any extra links for the Relationship, if a `self` link is not provided,
   *   one will be automatically added if the context resource is locatable and
   *   is not internal.
   *
   * @return \Drupal\jsonapi\JsonApiResource\LinkCollection
   *   The built links.
   */
  protected static function buildLinkCollectionFromEntityReferenceField(ResourceObject $context, EntityReferenceFieldItemListInterface $field, LinkCollection $links) {
    $context_resource_type = $context->getResourceType();
    $public_field_name = $context_resource_type->getPublicName($field->getName());
    if ($context_resource_type->isLocatable() && !$context_resource_type->isInternal()) {
      $context_is_versionable = $context_resource_type->isVersionable();
      if (!$links->hasLinkWithKey('self')) {
        $route_name = Routes::getRouteName($context_resource_type, "$public_field_name.relationship.get");
        $self_link = Url::fromRoute($route_name, ['entity' => $context->getId()]);
        if ($context_is_versionable) {
          $self_link->setOption('query', [JsonApiSpec::VERSION_QUERY_PARAMETER => $context->getVersionIdentifier()]);
        }
        $links = $links->withLink('self', new Link(new CacheableMetadata(), $self_link, 'self'));
      }
      $has_non_internal_resource_type = array_reduce($context_resource_type->getRelatableResourceTypesByField($public_field_name), function ($carry, ResourceType $target) {
        return $carry ?: !$target->isInternal();
      }, FALSE);
      // If a `related` link was not provided, automatically generate one from
      // the relationship object to the collection resource with all of the
      // resources targeted by this relationship. However, that link should
      // *not* be generated if all of the relatable resources are internal.
      // That's because, in that case, a route will not exist for it.
      if (!$links->hasLinkWithKey('related') && $has_non_internal_resource_type) {
        $route_name = Routes::getRouteName($context_resource_type, "$public_field_name.related");
        $related_link = Url::fromRoute($route_name, ['entity' => $context->getId()]);
        if ($context_is_versionable) {
          $related_link->setOption('query', [JsonApiSpec::VERSION_QUERY_PARAMETER => $context->getVersionIdentifier()]);
        }
        $links = $links->withLink('related', new Link(new CacheableMetadata(), $related_link, 'related'));
      }
    }
    return $links;
  }

}
