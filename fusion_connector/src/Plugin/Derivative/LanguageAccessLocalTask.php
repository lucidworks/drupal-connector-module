<?php

namespace Drupal\fusion_connector\Plugin\Derivative;


use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LanguageAccessLocalTask extends DeriverBase implements ContainerDeriverInterface
{
  /** @var LanguageManagerInterface */
  private $languageManager;

  /**
   * FusionConnectorRouteSubscriber constructor.
   */
  public function __construct(LanguageManagerInterface $languageManager)
  {
    $this->languageManager = $languageManager;
  }

  static public function create(ContainerInterface $container, $base_plugin_id) {
    return new static($container->get('language_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition)
  {
    if (count($this->languageManager->getLanguages()) <= 1) {
      return [];
    }
    // Implement dynamic logic to provide values for the same keys as in example.links.task.yml.
    $this->derivatives['fusion_connector.settings.languages'] = $base_plugin_definition;
    $this->derivatives['fusion_connector.settings.languages']['route_name'] = 'fusion_connector.settings.languages';
    $this->derivatives['fusion_connector.settings.languages']['base_route'] = "fusion_connector.settings";
    $this->derivatives['fusion_connector.settings.languages']['title'] = "Language Access";

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }
}
