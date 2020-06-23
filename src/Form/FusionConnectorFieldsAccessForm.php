<?php

namespace Drupal\fusion_connector\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\EntityFieldManager;

/**
 * Class FusionController
 *
 * @package Drupal\fusion_connector\Controller
 */
class FusionConnectorFieldsAccessForm extends ConfigFormBase {

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected $routerBuilder;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface          $config_factory
   * @param \Drupal\Core\ProxyClass\Routing\RouteBuilder        $router_builder
   * @param \Symfony\Component\HttpFoundation\Request           $request
   * @param \Drupal\Core\Entity\EntityFieldManager              $entityFieldManager
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RouteBuilder $router_builder,
    Request $request,
    EntityFieldManager $entityFieldManager,
    ResourceTypeRepository $resource_type_repository
  ) {
    parent::__construct($config_factory);
    $this->routerBuilder = $router_builder;
    $this->request = $request;
    $this->entityFieldManager = $entityFieldManager;
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
      $container->get('entity_field.manager'),
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
    return 'fussion_connector_fieldsaccess_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->request->get('entity_type_id');
    $bundle = $this->request->get('bundle');

    $resource_type = $this->resourceTypeRepository->get(
      $bundle,
      $entity_type_id
    );

    if (!$entity_type_id || !$bundle) {
      // We can't build the form without an entity type and bundle.
      throw new \InvalidArgumentException(
        'Unable to load entity type or bundle.'
      );
    }

    $config = $this->config('fusion_connector.settings');
    $disabledFields = $config->get('disabled_fields');
    $resource_config_id = sprintf('%s--%s', $bundle, $entity_type_id);

    /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
    $entity_type = \Drupal::entityTypeManager()->getDefinition($bundle);
    $bundle = $resource_type->getBundle();

    $fieldsDefinition = $this->getAllFields($entity_type, $bundle);

    $form['fusion_connector_fieldsaccess'] = [
      '#type'    => 'table',
      '#header'  => [$this->t('Field name'), $this->t('Disabled')],
      '#caption' => $this->t(
        'You are editing the fields filtering for %item.',
        ['%item' => $entity_type_id]
      ),
    ];
    $form['id'] = ['#type' => 'hidden', '#value' => $resource_config_id];

    if (count($fieldsDefinition)) {
      foreach ($fieldsDefinition as $field) {
        $form['fusion_connector_fieldsaccess'][$field] = [
          'label' => ['#plain_text' => $field],
          [
            '#type'          => 'checkbox',
            '#default_value' => in_array(
              $field,
              $disabledFields[$resource_config_id]
            ) ? 1 : 0,

          ],

        ];
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fusionConnectorSettings = $this->config('fusion_connector.settings');
    $disabledFieldsSettings = $fusionConnectorSettings->get('disabled_fields');
    $disabledFields = array_filter(
      $form_state->getValue('fusion_connector_fieldsaccess')
    );

    $disabledFieldsSettings[$form['id']['#value']] = [];
    if (count($disabledFields)) {
      foreach ($disabledFields as $key => $value) {
        if ($value[0] == 1) {
          $disabledFieldsSettings[$form['id']['#value']][] = $key;
        }
      }
    }
    $this->config('fusion_connector.settings')
      ->set('disabled_fields', $disabledFieldsSettings)
      ->save();

    parent::submitForm($form, $form_state);

    $form_state->setRedirect(
      'fusion_connector.settings'
    );
  }

  /**
   * Gets all field names for a given entity type and bundle.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param string                                  $bundle
   *
   * @return string[]
   */
  protected function getAllFields(EntityTypeInterface $entity_type, $bundle) {

    if (is_a($entity_type->getClass(), FieldableEntityInterface::class, TRUE)) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions(
        $entity_type->id(),
        $bundle
      );
      return array_keys($field_definitions);
    }
    elseif (is_a(
      $entity_type->getClass(),
      ConfigEntityInterface::class,
      TRUE
    )) {
      $export_properties = $entity_type->getPropertiesToExport();
      if ($export_properties !== NULL) {
        return array_keys($export_properties);
      }
      else {
        return ['id', 'type', 'uuid', '_core'];
      }
    }
    return [];
  }
}
