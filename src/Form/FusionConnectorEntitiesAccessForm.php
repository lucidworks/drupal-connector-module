<?php

namespace Drupal\fusion_connector\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Class FusionController.
 *
 * @package Drupal\fusion_connector\Form
 */
class FusionConnectorEntitiesAccessForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
  protected $routerBuilder;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\ProxyClass\Routing\RouteBuilder $router_builder
   *   The router builder to rebuild menus after saving config entity.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RouteBuilder $router_builder,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($config_factory);
    $this->routerBuilder = $router_builder;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('config.factory'),
      $container->get('router.builder'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['fusion_connector.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'fusion_connector_entities_access';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $multiLanguage = count($this->languageManager->getLanguages()) > 1;

    $header = [
      'enabled_entities' => t('Enable indexing'),
    ];

    $header['operations'] = $this->t('Operations');

    if ($multiLanguage) {
      $header['language_access'] = $this->t('Language Access');
    }

    $form['fusion_connector_types'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#sticky' => true,
    ];

    $types = $this->getEntityTypes();

    $config = $this->config('fusion_connector.settings');
    $disabledEntities = $config->get('disabled_entities');
    $defaultValues = [];

    foreach ($types as $bundle => $entities) {
      if (count($entities)) {
        foreach ($entities as $type => $label) {
          $resource_config_id = sprintf('%s--%s', $bundle, $type);
          $row['enabled_entities'] = $label['label'];

          $row['operations']['data'] = [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => t('Filter fields'),
                'weight' => -10,
                'url' => Url::fromRoute(
                  'fusion_connector.settings.edit_fieldsaccess_form',
                  [
                    'entity_type_id' => $type,
                    'bundle' => $bundle,
                  ]
                ),
              ],
            ],
          ];

          if ($multiLanguage) {
            $row['language_access']['data'] = [
              '#type' => 'operations',
              '#links' => [
                'edit' => [
                  'title' => t('Language Access'),
                  'weight' => -11,
                  'url' => Url::fromRoute(
                    'fusion_connector.settings.edit_languagetypeaccess_form',
                    [
                      'entity_type_id' => $type,
                      'bundle' => $bundle,
                    ]
                  ),
                ],
              ],
            ];
          }
          $form['fusion_connector_types']['#options'][$resource_config_id] = $row;

          if (!in_array($resource_config_id, $disabledEntities)) {
            $defaultValues[$resource_config_id] = true;
          }
        }
      }
    }

    $form['fusion_connector_types']['#default_value'] = $defaultValues;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $disabledEntitiesArray = [];
    $submitedEntities = $form_state->getValue('fusion_connector_types');

    foreach ($submitedEntities as $resource_config_id => $entity) {
      if ($entity === 0) {
        $disabledEntitiesArray[] = $resource_config_id;
      }
    }

    $this->config('fusion_connector.settings')
      ->set('disabled_entities', $disabledEntitiesArray)
      ->save();
    $this->routerBuilder->setRebuildNeeded();

    parent::submitForm($form, $form_state);

  }

  /**
   * @param $types
   * @return mixed
   */
  private
  function getEntityTypes()
  {
    $types['node'] = \Drupal::service("entity_type.bundle.info")->getBundleInfo(
      'node'
    );
    $types['taxonomy_term'] = \Drupal::service("entity_type.bundle.info")
      ->getBundleInfo('taxonomy_term');
    $types['taxonomy_vocabulary'] = \Drupal::service("entity_type.bundle.info")
      ->getBundleInfo('taxonomy_vocabulary');

    return $types;
  }
}
