<?php

namespace Drupal\fusion_connector\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\jsonapi\Controller\EntityResource as JsonApiEntityResourse;
use Drupal\jsonapi\JsonApiResource\IncludedData;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\fusion_connector\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\TopLevelDataInterface;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpFoundation\Request;
use Drupal\fusion_connector\JsonApiResource\Relationship;

/**
 * Overrides jsonapi module EntityResource controller.
 */
class EntityResource extends JsonApiEntityResourse {

  /**
   * Gets the relationship of an entity.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The base JSON:API resource type for the request to be served.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The requested entity.
   * @param string $related
   *   The related field name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $response_code
   *   The response code. Defaults to 200.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function getRelationship(
    ResourceType $resource_type,
    FieldableEntityInterface $entity,
    $related,
    Request $request,
    $response_code = 200
  ) {
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
    $field_list = $entity->get($resource_type->getInternalName($related));
    //var_dump($request);die;
    // Access will have already been checked by the RelationshipFieldAccess
    // service, so we don't need to call ::getAccessCheckedResourceObject().
    $resource_object = ResourceObject::createFromEntity(
      $resource_type,
      $entity
    );
    $relationship = Relationship::createFromEntityReferenceField(
      $resource_object,
      $field_list
    );
    $response = $this->buildWrappedResponse(
      $relationship,
      $request,
      $this->getIncludes($request, $resource_object),
      $response_code
    );
    // Add the host entity as a cacheable dependency.
    $response->addCacheableDependency($entity);
    return $response;
  }

  /**
   * Builds a response with the appropriate wrapped document.
   *
   * @param \Drupal\jsonapi\JsonApiResource\TopLevelDataInterface $data
   *   The data to wrap.
   * @param \Symfony\Component\HttpFoundation\Request             $request
   *   The request object.
   * @param \Drupal\jsonapi\JsonApiResource\IncludedData          $includes
   *   The resources to be included in the document. Use NullData if
   *   there should be no included resources in the document.
   * @param int                                                   $response_code
   *   The response code.
   * @param array                                                 $headers
   *   An array of response headers.
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection        $links
   *   The URLs to which to link. A 'self' link is added automatically.
   * @param array                                                 $meta
   *   (optional) The top-level metadata.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  protected function buildWrappedResponse(
    TopLevelDataInterface $data,
    Request $request,
    IncludedData $includes,
    $response_code = 200,
    array $headers = [],
    LinkCollection $links = NULL,
    array $meta = []
  ) {
    $links = ($links ? : new LinkCollection([]));
    if (!$links->hasLinkWithKey('self')) {
      $self_link = new Link(
        new CacheableMetadata(),
        self::getRequestLink($request),
        'self'
      );
      $links = $links->withLink('self', $self_link);
    }
    $response = new ResourceResponse(
      new JsonApiDocumentTopLevel($data, $includes, $links, $meta),
      $response_code,
      $headers
    );
    $cacheability = (new CacheableMetadata())->addCacheContexts(
      [
        // Make sure that different sparse fieldsets are cached differently.
        'url.query_args:fields',
        // Make sure that different sets of includes are cached differently.
        'url.query_args:include',
      ]
    );
    $response->addCacheableDependency($cacheability);
    return $response;
  }

}
