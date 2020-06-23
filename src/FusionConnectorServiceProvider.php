<?php

namespace Drupal\fusion_connector;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\fusion_connector\EventSubscriber\ResourceObjectNormalizationCacher;
use Drupal\fusion_connector\Controller\EntityResource;
use Drupal\fusion_connector\Access\EntityAccessChecker;

/**
 * Replace the resource type repository for our own configurable version.
 */
class FusionConnectorServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    /** @var \Symfony\Component\DependencyInjection\Definition $definition */
    $container_namespaces = $container->getParameter('container.namespaces');
    $container_modules = $container->getParameter('container.modules');
    $jsonapi_impostor_path = dirname(
        $container_modules['fusion_connector']['pathname']
      ) . '/src-impostor-normalizers';
    $container_namespaces['Drupal\jsonapi\Normalizer\ImpostorFrom\fusion_connector'][] = $jsonapi_impostor_path;
    $container->getDefinition('jsonapi.normalization_cacher')
      ->setClass(
        ResourceObjectNormalizationCacher::class
      );
    $container->getDefinition('jsonapi.entity_resource')
      ->setClass(EntityResource::class);
    $container->getDefinition('jsonapi.entity_access_checker')->setClass(
      EntityAccessChecker::class
    );
    $container->setParameter('container.namespaces', $container_namespaces);
  }

}
