<?php

namespace Drupal\fusion_connector\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\jsonapi\JsonApiResource\Relationship;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\jsonapi\Normalizer\Value\CacheableOmission;
use Drupal\jsonapi\Normalizer\ResourceObjectNormalizer as JsonApiResourceObjectNormalizer;
use Drupal\jsonapi\EventSubscriber\ResourceObjectNormalizationCacher;
use Drupal\fusion_connector\JsonApiResource\Relationship as FusionConnectorRelationship;

/**
 * Converts the JSON:API module ResourceObject into a JSON:API array structure.
 *
 * @see https://www.drupal.org/project/jsonapi/issues/3032787
 * @see jsonapi.api.php
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *           class may change at any time and this will break any dependencies
 *           on it.
 */
class ResourceObjectNormalizer extends JsonApiResourceObjectNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = ResourceObject::class;

  /**
   * The entity normalization cacher.
   *
   * @var \Drupal\jsonapi\EventSubscriber\ResourceObjectNormalizationCacher
   */
  protected $cacher;

  /**
   * Constructs a ResourceObjectNormalizer object.
   *
   * @param \Drupal\jsonapi\EventSubscriber\ResourceObjectNormalizationCacher $cacher
   *   The entity normalization cacher.
   */
  public function __construct(ResourceObjectNormalizationCacher $cacher) {
    $this->cacher = $cacher;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof ResourceObject);
    // If the fields to use were specified, only output those field values.
    $context['resource_object'] = $object;
    $resource_type = $object->getResourceType();
    $resource_type_name = $resource_type->getTypeName();
    $fields = $object->getFields();

    // Get the bundle ID of the requested resource. This is used to determine if
    // this is a bundle level resource or an entity level resource.
    if (!empty($context['sparse_fieldset'][$resource_type_name])) {
      $field_names = $context['sparse_fieldset'][$resource_type_name];
    }
    else {
      $field_names = array_keys($fields);
    }

    if ($context['is_fusion_path'] === TRUE) {
      // Remove filtered fields.
      $config = \Drupal::config('fusion_connector.settings');
      $disabledFields = $config->get('disabled_fields');
      if (count($disabledFields)) {
        $resource_config_id = sprintf(
          '%s--%s',
          $object->getResourceType()->getEntityTypeId(),
          $object->getResourceType()->getBundle()
        );
        if (in_array($resource_config_id, array_keys($disabledFields))) {
          foreach ($disabledFields[$resource_config_id] as $disableField) {
            foreach ($field_names as $key => $field) {
              if ($field == $disableField) {
                unset($field_names[$key]);
              }
            }
          }
        }
      }
    }

    $normalization_parts = $this->getNormalization(
      $field_names,
      $object,
      $format,
      $context
    );

    // Keep only the requested fields (the cached normalization gradually grows
    // to the complete set of fields).
    $fields = $normalization_parts[ResourceObjectNormalizationCacher::RESOURCE_CACHE_SUBSET_FIELDS];
    $field_normalizations = array_intersect_key(
      $fields,
      array_flip($field_names)
    );

    $relationship_field_names = array_keys(
      $resource_type->getRelatableResourceTypes()
    );
    $attributes = array_diff_key(
      $field_normalizations,
      array_flip($relationship_field_names)
    );
    $relationships = array_intersect_key(
      $field_normalizations,
      array_flip($relationship_field_names)
    );
    $entity_normalization = array_filter(
      $normalization_parts[ResourceObjectNormalizationCacher::RESOURCE_CACHE_SUBSET_BASE] + [
        'attributes'    => CacheableNormalization::aggregate($attributes)
          ->omitIfEmpty(),
        'relationships' => CacheableNormalization::aggregate($relationships)
          ->omitIfEmpty(),
      ]
    );
    return CacheableNormalization::aggregate($entity_normalization)
      ->withCacheableDependency($object);
  }

  /**
   * Serializes a given field.
   *
   * @param mixed $field
   *   The field to serialize.
   * @param array $context
   *   The normalization context.
   * @param string $format
   *   The serialization format.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\CacheableNormalization
   *   The normalized value.
   */
  protected function serializeField($field, array $context, $format) {
    // Only content entities contain FieldItemListInterface fields. Since config
    // entities do not have "real" fields and therefore do not have field access
    // restrictions.
    $container = \Drupal::getContainer();

    if ($field instanceof FieldItemListInterface) {
      $field_access_result = $field->access('view', $context['account'], TRUE);
      if (!$field_access_result->isAllowed()) {
        return new CacheableOmission(
          CacheableMetadata::createFromObject($field_access_result)
        );
      }
      if ($field instanceof EntityReferenceFieldItemListInterface) {
        // Build the relationship object based on the entity reference and
        // normalize that object instead.
        assert(
          !empty($context['resource_object']) && $context['resource_object'] instanceof ResourceObject
        );
        $resource_object = $context['resource_object'];
        if (substr_count(
          \Drupal::request()->getRequestUri(),
          $container->getParameter('fusion_connector.base_path')
        )) {
          $relationship = FusionConnectorRelationship::createFromEntityReferenceField(
            $resource_object,
            $field
          );
        }
        else {
          $relationship = Relationship::createFromEntityReferenceField(
            $resource_object,
            $field
          );
        }

        $normalized_field = $this->serializer->normalize(
          $relationship,
          $format,
          $context
        );
      }
      else {
        $normalized_field = $this->serializer->normalize(
          $field,
          $format,
          $context
        );
      }
      assert($normalized_field instanceof CacheableNormalization);
      return $normalized_field->withCacheableDependency(
        CacheableMetadata::createFromObject($field_access_result)
      );
    }
    else {
      // @todo Replace this workaround after https://www.drupal.org/node/3043245
      //   or remove the need for this in https://www.drupal.org/node/2942975.
      //   See \Drupal\layout_builder\Normalizer\LayoutEntityDisplayNormalizer.
      if ($context['resource_object']->getResourceType()->getDeserializationTargetClass() === 'Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay' &&
        $context['resource_object']->getField('third_party_settings') === $field) {
        unset($field['layout_builder']['sections']);
      }

      // Config "fields" in this case are arrays or primitives and do not need
      // to be normalized.
      return CacheableNormalization::permanent($field);
    }
  }

}
