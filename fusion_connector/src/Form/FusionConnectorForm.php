<?php

namespace Drupal\fusion_connector\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\user\RoleStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Class FusionController
 *
 * @package Drupal\fusion_connector\Controller
 */
class FusionConnectorForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected $routerBuilder;

  /**
   * The role storage.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected $roleStorage;

  protected $languageManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface   $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\ProxyClass\Routing\RouteBuilder $router_builder
   *   The router builder to rebuild menus after saving config entity.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    RouteBuilder $router_builder,
    RoleStorageInterface $role_storage,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($config_factory);
    $this->routerBuilder = $router_builder;
    $this->roleStorage = $role_storage;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('router.builder'),
      $container->get('entity_type.manager')->getStorage('user_role'),
      $container->get('language_manager'),
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
    return 'jsonapi_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $multiLanguage = count($this->languageManager->getLanguages()) > 1;

    $header = [
      'enabled_entities' => t('Entity type you want to be exposed'),
    ];
    $userRoles = $this->roleStorage->loadMultiple();
    if (count($userRoles)) {
      foreach ($userRoles as $userRoleName => $userRole) {
        $header[$userRoleName] =
          [
            'data'  => $userRole->label(),
            'class' => ['checkbox'],
          ];
      }
    }

    $header['operations'] = $this->t('Operations');

    if ($multiLanguage) {
      $header['language_access'] = $this->t('Language Access');
    }

    $form['fusion_connector_types'] = [
      '#type'   => 'tableselect',
      '#header' => $header,
      '#sticky' => TRUE,
    ];

    $config = $this->config('fusion_connector.settings');

    $types['node'] = \Drupal::service("entity_type.bundle.info")->getBundleInfo(
      'node'
    );
    $types['taxonomy_term'] = \Drupal::service("entity_type.bundle.info")
      ->getBundleInfo('taxonomy_term');
    $types['taxonomy_vocabulary'] = \Drupal::service("entity_type.bundle.info")
      ->getBundleInfo('taxonomy_vocabulary');
    $user_role_access = $config->get('user_role_access');
    $authenticated_role = $this->roleStorage->load(
      RoleInterface::AUTHENTICATED_ID
    );

    foreach ($types as $bundle => $entities) {
      if (count($entities)) {
        foreach ($entities as $type => $label) {
          $row['enabled_entities'] = $label['label'];
          if (count($userRoles)) {
            foreach ($userRoles as $userRoleName => $userRole) {
              //if the user roles have no "access content" permission or it's admin, disable the checkbox

              //else {
              $row[$userRoleName] = [
                'data'  => [
                  '#title'              => $userRoleName,
                  '#name'               => 'fusion_connector_user_roles[' . $type . '][' . $userRoleName . ']',
                  '#title_display'      => 'invisible',
                  '#wrapper_attributes' => [
                    'class' => ['checkbox'],
                  ],
                  '#type'               => 'checkbox',
                  '#checked'            => in_array(
                    $type,
                    $user_role_access[$userRoleName]
                  ) ? 1 : 0,
                  '#default_value'      => in_array(
                    $type,
                    $user_role_access[$userRoleName]
                  ) ? 1 : 0,
                  '#states'             => [
                    'visible' => [
                      ':input[name="fusion_connector_types[' . $type . ']"]' => [
                        'checked' => TRUE,
                      ],
                    ],
                  ],
                ],
                'class' => ['checkbox'],
              ];
              //if the user role is admin or have no access content acces, disable the checkbox
              // if the user role is admin, set the checkbox as checked and the value 1
              if ($userRole->isAdmin() || !$userRole->hasPermission(
                  'access content'
                )) {
                $row[$userRoleName]['data']['#attributes'] = ['disabled' => 'true'];
              }
              if ($userRole->isAdmin() || ($authenticated_role->hasPermission(
                  'access content'
                ))) {
                if ($userRole->isAdmin()) {
                  $row[$userRoleName]['data']['#checked'] = 1;
                  $row[$userRoleName]['data']['#default_value'] = 1;
                }
                else {
                  if ($userRole->getOriginalId() != 'anonymous') {


                    if ($userRole->getOriginalId(
                      ) != $authenticated_role->getOriginalId()) {

                      if (!$userRole->hasPermission(
                        'access content'
                      )) {
                        $row[$userRoleName]['data']['#states'] = [
                          'visible' => [
                            ':input[name="fusion_connector_user_roles[' . $type . '][' . $authenticated_role->getOriginalId(
                            ) . ']"]' => [
                              'checked' => TRUE,
                            ],
                          ],
                        ];
                        $row[$userRoleName]['data']['#checked'] = 1;
                        $row[$userRoleName]['data']['#default_value'] = 1;
                      }
                      else {
                        $row[$userRoleName]['data']['#states'] = [
                          'disabled' => [
                            ':input[name="fusion_connector_user_roles[' . $type . '][' . $authenticated_role->getOriginalId(
                            ) . ']"]' => [
                              'checked' => TRUE,
                            ],
                          ],
                        ];
                        if (!in_array(
                          $type,
                          $user_role_access[$userRoleName]
                        )) {
                          $row[$userRoleName]['data']['#states']['checked'] = [
                            ':input[name="fusion_connector_user_roles[' . $type . '][' . $authenticated_role->getOriginalId(
                            ) . ']"]' => [
                              'checked' => TRUE,
                            ],
                          ];
                        }
                      }
                    }
                  }
                }
              }
            }
          }

          $row['operations']['data'] = [
            '#type'  => 'operations',
            '#links' => [
              'edit' => [
                'title'  => t('Filter fields'),
                'weight' => -10,
                'url'    => Url::fromRoute(
                  'fusion_connector.settings.edit_fieldsaccess_form',
                  [
                    'entity_type_id' => $type,
                    'bundle'         => $bundle,
                  ]
                ),
              ],
            ],
          ];

          if ($multiLanguage) {
            $row['language_access']['data'] = [
              '#type'  => 'operations',
              '#links' => [
                'edit' => [
                  'title'  => t('Language Access'),
                  'weight' => -11,
                  'url'    => Url::fromRoute(
                    'fusion_connector.settings.edit_languagetypeaccess_form',
                    [
                      'entity_type_id' => $type,
                      'bundle'         => $bundle,
                    ]
                  ),
                ],
              ],
            ];
          }
          $form['fusion_connector_types']['#options'][$type] = $row;
        }
      }
    }

    $config = $this->config('fusion_connector.settings');
    $enabledEntities = $config->get('enabled_entities');
    $defaultValues = [];
    if (count($enabledEntities)) {
      foreach ($enabledEntities as $enabledEntity) {
        $defaultValues[$enabledEntity] = TRUE;
      }
    }
    $form['fusion_connector_types']['#default_value'] = $defaultValues;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $enabledEntities = array_filter(
      $form_state->getValue('fusion_connector_types')
    );
    $enabledEntitiesArray = [];
    if (count($enabledEntities)) {
      foreach ($enabledEntities as $entityTypeId) {
        $enabledEntitiesArray[] = $entityTypeId;
      }
    }
    $userRoleAccess = [];
    $fusionConnectorUserRoles = $form_state->getUserInput(
    )['fusion_connector_user_roles'];
    foreach ($enabledEntitiesArray as $enabledEntity) {
      if (is_array($fusionConnectorUserRoles[$enabledEntity])) {
        foreach ($fusionConnectorUserRoles[$enabledEntity] as $userRole => $userRoleValue) {
          if ($userRoleValue == 1) {
            $userRoleAccess[$userRole][] = $enabledEntity;
          }
        }
      }
    }

    //add manually all the needed access to the entities for the admin roles, because they are disabled
    $userRoles = $this->roleStorage->loadMultiple();
    $authenticated_role = $this->roleStorage->load(
      RoleInterface::AUTHENTICATED_ID
    );
    if (count($userRoles)) {
      foreach ($userRoles as $userRoleName => $userRole) {
        if ($userRole->isAdmin()) {
          foreach ($enabledEntitiesArray as $enabledEntity) {
            $userRoleAccess[$userRoleName][] = $enabledEntity;
          }
        }

        //if the authenticated user has acess content permission and the other user roles (which include the authenticated role) doesn't,
        //then include all the permissions from the authenticated user role
        if (!$userRole->hasPermission(
            'access content'
          ) && $authenticated_role->hasPermission(
            'access content'
          ) && $userRole->getOriginalId() != 'anonymous') {
          $userRoleAccess[$userRoleName] = $userRoleAccess[$authenticated_role->getOriginalId(
          )];
        }
      }
    }
    $this->config('fusion_connector.settings')
      ->set('enabled_entities', $enabledEntitiesArray)
      ->set('user_role_access', $userRoleAccess)
      ->save();
    $this->routerBuilder->setRebuildNeeded();
    parent::submitForm($form, $form_state);
  }
}
