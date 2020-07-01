<?php

namespace Drupal\fusion_connector\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class FussionConnectorLanguageAccessForm.
 *
 * @package Drupal\fusion_connector\Controller
 */
class FusionConnectorLanguageTypeAccessForm extends ConfigFormBase {

  /**
   * The current route match.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository
   */
  protected $resourceTypeRepository;

  /**
   * {@inheritdoc}
   */
  protected $routerBuilder;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\ProxyClass\Routing\RouteBuilder $router_builder
   *   The router builder to rebuild menus after saving config entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that contains query params.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository
   *   The service that provides information about all the entity types.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RouteBuilder $router_builder,
    Request $request,
    ResourceTypeRepository $resource_type_repository
  ) {
    parent::__construct($config_factory);
    $this->routerBuilder = $router_builder;
    $this->request = $request;
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('router.builder'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('jsonapi.resource_type.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['fusion_connector.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fussion_connector_language_type_access';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('fusion_connector.settings');
    $disabledLanguagesEntityType = $config->get(
      'disabled_entity_type_languages'
    ) ? $config->get('disabled_entity_type_languages') : [];
    $disabledLanguages = $config->get('disabled_languages') ? $config->get(
      'disabled_languages'
    ) : [];

    $entity_type_id = $this->request->get('entity_type_id');
    $bundle = $this->request->get('bundle');

    $resource_type = $this->resourceTypeRepository->get(
      $bundle,
      $entity_type_id
    );

    $resource_config_id = sprintf('%s--%s', $bundle, $entity_type_id);

    if (!$resource_type) {
      // We can't build the form without an entity type and bundle.
      throw new \InvalidArgumentException(
        'Unable to load entity type or bundle.'
      );
    }

    $header = [
      t('Language'),
      [
        'data'  => t('Disable indexing'),
        'class' => ['checkbox'],
      ],
    ];
    $form['fusion_connector_entity_type_languages'] = [
      '#type'    => 'table',
      '#header'  => $header,
      '#caption' => $this->t(
        'Choose what languages to disable for %resource_type:',
        ['%resource_type' => $resource_type->getTypeName()]
      ),
      '#sticky'  => TRUE,
    ];

    $form['id'] = ['#type' => 'hidden', '#value' => $resource_config_id];

    $languages = \Drupal::service('language_manager')->getLanguages();

    if (count($languages)) {
      foreach ($languages as $value => $language) {
        $form['fusion_connector_entity_type_languages'][$value]['label'] = [
          '#plain_text' => $language->getName(),
        ];
        $form['fusion_connector_entity_type_languages'][$value]['checked'] = [
          '#type'               => 'checkbox',
          '#default_value'      => array_key_exists(
            $resource_config_id,
            $disabledLanguagesEntityType
          ) ? (in_array(
            $value,
            $disabledLanguagesEntityType[$resource_config_id]
          ) ? 1 : 0) : 0,
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
        ];
        if (in_array($value, $disabledLanguages)) {
          $form['fusion_connector_entity_type_languages'][$value]['checked']['#disabled'] = TRUE;
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $disabledLanguagesEntityType = $form_state->getValue(
      'fusion_connector_entity_type_languages'
    );

    $checkedValues[$form['id']['#value']] = [];
    if (count($disabledLanguagesEntityType)) {
      foreach ($disabledLanguagesEntityType as $key => $value) {
        if ($value['checked'] == 1) {
          $checkedValues[$form['id']['#value']][] = $key;
        }
      }
    }

    $this->config('fusion_connector.settings')
      ->set('disabled_entity_type_languages', $checkedValues)
      ->save();

    $this->routerBuilder->setRebuildNeeded();

    parent::submitForm($form, $form_state);

  }

}
