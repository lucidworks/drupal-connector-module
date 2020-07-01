<?php

namespace Drupal\fusion_connector\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\Config;

/**
 * Class FussionConnectorLanguageAccessForm.
 *
 * @package Drupal\fusion_connector\Controller
 */
class FusionConnectorLanguageAccessForm extends ConfigFormBase {

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
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RouteBuilder $router_builder
  ) {
    parent::__construct($config_factory);
    $this->routerBuilder = $router_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('router.builder')
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
    return 'fussion_connector_language_access';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('fusion_connector.settings');

    $this->buildLanguagesFields($form, $config);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $allowed_languages = array_filter(
      $form_state->getValue('fusion_connector_languages')
    );
    $checked_values = [];
    if (count($allowed_languages)) {
      foreach ($allowed_languages as $key => $value) {
        if ($value['checked'] == 1) {
          $checked_values[] = $key;
        }
      }
    }

    $this->config('fusion_connector.settings')
      ->set('disabled_languages', $checked_values)
      ->save();

    $this->routerBuilder->setRebuildNeeded();

    parent::submitForm($form, $form_state);
  }

  /**
   * Build the languages fields form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Config\Config $config
   *   The form config.
   *
   * @return array
   *   Return the form.
   */
  private function buildLanguagesFields(array &$form, Config $config) {
    $header = [
      t('Language'),
      [
        'data'  => t('Disable indexing'),
        'class' => ['checkbox'],
      ],
    ];
    $form['fusion_connector_languages'] = [
      '#type'   => 'table',
      '#header' => $header,
      '#sticky' => TRUE,
    ];

    $languages = \Drupal::service('language_manager')->getLanguages();

    if (count($languages)) {
      foreach ($languages as $value => $language) {
        $form['fusion_connector_languages'][$value]['label'] = [
          '#plain_text' => $language->getName(),
        ];
        $form['fusion_connector_languages'][$value]['checked'] = [
          '#type'               => 'checkbox',
          '#default_value'      => in_array(
            $value,
            $config->get('disabled_languages')
          ) ? 1 : 0,
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
        ];
      }
    }

    return $form;
  }

}
