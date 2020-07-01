<?php

namespace Drupal\fusion_connector\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LanguageAccessLocalTask.
 */
class LanguageAccessLocalTask extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Language manager interface.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * Constrcuts a LanguageAccessLocalTask object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager interface.
   */
  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    $base_plugin_id
  ) {
    return new static($container->get('language_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (count($this->languageManager->getLanguages()) <= 1) {
      return [];
    }
    // Implement dynamic logic to provide values
    // for the same keys as in example.links.task.yml.
    $this->derivatives['fusion_connector.settings.languages'] = $base_plugin_definition;
    $this->derivatives['fusion_connector.settings.languages']['route_name'] = 'fusion_connector.settings.languages';
    $this->derivatives['fusion_connector.settings.languages']['base_route'] = "fusion_connector.settings";
    $this->derivatives['fusion_connector.settings.languages']['title'] = "Language Access";

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
