<?php

namespace Drupal\fusion_connector\EventSubscriber;

use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\EventSubscriber\ResourceResponseSubscriber as JsonApiResourceResponseSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Response subscriber that serializes and removes ResourceResponses' data.
 *
 * @internal JSON:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/jsonapi/issues/3032787
 * @see jsonapi.api.php
 *
 * This is 99% identical to:
 *
 * \Drupal\rest\EventSubscriber\ResourceResponseSubscriber
 *
 * but with a few differences:
 * 1. It has the @jsonapi.serializer service injected instead of @serializer
 * 2. It has the @current_route_match service no longer injected
 * 3. It hardcodes the format to 'api_json'
 * 4. It adds the CacheableNormalization object returned by JSON:API
 *    normalization to the response object.
 * 5. It flattens only to a cacheable response if the HTTP method is cacheable.
 *
 * @see \Drupal\rest\EventSubscriber\ResourceResponseSubscriber
 */
class ResourceResponseSubscriber extends JsonApiResourceResponseSubscriber {

  /**
   * Serializes ResourceResponse responses' data, and removes that data.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function onResponse(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof ResourceResponse) {
      return;
    }

    $request = $event->getRequest();
    $format = 'api_json';
    $this->renderResponseBody($request, $response, $this->serializer, $format);
    $event->setResponse($this->flattenResponse($response, $request));
  }

  /**
   * Renders a resource response body.
   *
   * Serialization can invoke rendering (e.g., generating URLs), but the
   * serialization API does not provide a mechanism to collect the
   * bubbleable metadata associated with that (e.g., language and other
   * contexts), so instead, allow those to "leak" and collect them here in
   * a render context.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\jsonapi\ResourceResponse $response
   *   The response from the JSON:API resource.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer to use.
   * @param string|null $format
   *   The response format, or NULL in case the response does not need a format,
   *   for example for the response to a DELETE request.
   *
   * @todo Add test coverage for language negotiation contexts in
   *   https://www.drupal.org/node/2135829.
   */
  protected function renderResponseBody(Request $request, ResourceResponse $response, SerializerInterface $serializer, $format) {
    $data = $response->getResponseData();

    // If there is data to send, serialize and set it as the response body.
    if ($data !== NULL) {
      // First normalize the data. Note that error responses do not need a
      // normalization context, since there are no entities to normalize.
      // @see \Drupal\jsonapi\EventSubscriber\DefaultExceptionSubscriber::isJsonApiExceptionEvent()
      $context = !$response->isSuccessful() ? [] : static::generateContext($request);
      $jsonapi_doc_object = $serializer->normalize($data, $format, $context);
      // Having just normalized the data, we can associate its cacheability with
      // the response object.
      assert($jsonapi_doc_object instanceof CacheableNormalization);
      $response->addCacheableDependency($jsonapi_doc_object);
      // Finally, encode the normalized data (JSON:API's encoder rasterizes it
      // automatically).
      $response->setContent($serializer->encode($jsonapi_doc_object->getNormalization(), $format));
      $response->headers->set('Content-Type', $request->getMimeType($format));
    }
  }

  /**
   * Generates a top-level JSON:API normalization context.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request from which the context can be derived.
   *
   * @return array
   *   The generated context.
   */
  protected static function generateContext(Request $request) {
    // Build the expanded context.
    $context = [
      'account' => NULL,
      'sparse_fieldset' => NULL,
      'is_fusion_path' => NULL,
    ];
    if ($request->query->get('fields')) {
      $context['sparse_fieldset'] = array_map(function ($item) {
        return explode(',', $item);
      }, $request->query->get('fields'));
    }
    $container = \Drupal::getContainer();
    if (substr_count(
      \Drupal::request()->getRequestUri(),
      $container->getParameter('fusion_connector.base_path')
    )) {
      $context['is_fusion_path'] = TRUE;
    }

    return $context;

  }

}
