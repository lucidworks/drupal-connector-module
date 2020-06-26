<?php

namespace Drupal\Tests\fusion_connector\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Fusion Connector configuration forms
 *
 * @group block
 */
class FusionConnectorConfigFormsTest extends BrowserTestBase {

  /**
   * An administrative user to configure the test environment.
   */
  protected $adminUser;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['fusion_connector', 'node','language',
    'content_translation',];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp() {
    parent::setUp();

    $permissions = [
      'administer content types',
      'administer site configuration',
    ];

    $this->adminUser = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test the entities form configuration
   */
  public function testEntitiesConfiguration() {
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page'])->save();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article'])->save();

    // Check if the fusion connector settings page is available.
    $this->drupalGet('admin/config/services/fusion_connector');

    $this->assertField('fusion_connector_types[node--page]', 'Basic page');
    $this->assertField('fusion_connector_types[node--article]', 'Article');

    // Disable the page entity from indexing.
    $edit = [
      'fusion_connector_types[node--page]' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/fusion_connector' , $edit, t('Save configuration'));
    $this->assertText('The configuration options have been saved.');
    $this->assertFieldChecked('fusion_connector_types[node--page]');
    $this->assertNoFieldChecked('fusion_connector_types[node--article]');

    $config = $this->config('fusion_connector.settings');
    $disabledEntities = $config->get('disabled_entities');
    $this->assertContains('node--page', $disabledEntities);
  }

  /**
   * Test fields filters form for an entity
   */
  public function testFilterFieldsForm() {
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page'])->save();

    // Check if the visibility setting is available.
    $this->drupalGet('admin/config/services/fusion_connector/access/node/page');


    $this->assertField('fusion_connector_fieldsaccess[nid][0]', 'nid');
    $this->assertField('fusion_connector_fieldsaccess[uid][0]', 'uid');

    // Disable the page entity fields from indexing.
    $edit = [
      'fusion_connector_fieldsaccess[nid][0]' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/fusion_connector/access/node/page' , $edit, t('Save configuration'));
    $this->assertText('The configuration options have been saved.');
    $this->assertFieldChecked('fusion_connector_fieldsaccess[nid][0]');
    $this->assertNoFieldChecked('fusion_connector_fieldsaccess[uid][0]');

    $config = $this->config('fusion_connector.settings');
    $disabledFields = $config->get('disabled_fields');
    $this->assertContains('nid', $disabledFields['node--page']);
  }

  /**
   * Test disable language for an entity
   */
  public function testDisableLanguageEntityForm() {
    $language = ConfigurableLanguage::createFromLangcode('ca');
    $language->save();

    // In order to reflect the changes for a multilingual site in the container
    // we have to rebuild it.
    $this->rebuildContainer();

    \Drupal::configFactory()->getEditable('language.negotiation')
      ->set('url.prefixes.ca', 'ca')
      ->save();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page'])->save();

    // Check if the page language disable form is available.
    $this->drupalGet('admin/config/services/fusion_connector/type_language_access/node/page');


    $this->assertField('fusion_connector_entity_type_languages[ca][checked]', 'Catalan');

    // Disable the page entity from indexing on the ca language.
    $edit = [
      'fusion_connector_entity_type_languages[ca][checked]' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/fusion_connector/type_language_access/node/page' , $edit, t('Save configuration'));

    $config = $this->config('fusion_connector.settings');
    $disabledLanguages = $config->get('disabled_entity_type_languages');
    $this->assertContains('ca', $disabledLanguages['node--page']);
    $this->assertNotContains('en', $disabledLanguages['node--page']);
  }


  /**
   * Test disable language fro indexing
   */
  public function testDisableLanguageForm() {
    $language = ConfigurableLanguage::createFromLangcode('ca');
    $language->save();

    // In order to reflect the changes for a multilingual site in the container
    // we have to rebuild it.
    $this->rebuildContainer();

    \Drupal::configFactory()->getEditable('language.negotiation')
      ->set('url.prefixes.ca', 'ca')
      ->save();

    // Check if the language setting is available.
    $this->drupalGet('admin/config/services/fusion_connector/languages');


    $this->assertField('fusion_connector_languages[ca][checked]', 'Catalan');

    // Disable a language from being indexed.
    $edit = [
      'fusion_connector_languages[ca][checked]' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/fusion_connector/languages' , $edit, t('Save configuration'));

    $config = $this->config('fusion_connector.settings');
    $disabledLanguages = $config->get('disabled_languages');
    $this->assertContains('ca', $disabledLanguages);
    $this->assertNotContains('en', $disabledLanguages);
  }

}
