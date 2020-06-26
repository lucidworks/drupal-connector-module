<?php

namespace Drupal\Tests\fusion_connector\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\jsonapi\Functional\JsonApiFunctionalTestBase;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group fusion_connector
 */
class MultilangualTest extends JsonApiFunctionalTestBase
{

  protected $defaultTheme = 'stable';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'fusion_connector',
    'language',
    'content_translation',
  ];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp()
  {
    parent::setUp();
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $language = ConfigurableLanguage::createFromLangcode('ca');
    $language->save();
    ConfigurableLanguage::createFromLangcode('ca-fr')->save();

    // In order to reflect the changes for a multilingual site in the container
    // we have to rebuild it.
    $this->rebuildContainer();

    \Drupal::configFactory()->getEditable('language.negotiation')
      ->set('url.prefixes.ca', 'ca')
      ->set('url.prefixes.ca-fr', 'ca-fr')
      ->save();

    ContentLanguageSettings::create(
      [
        'target_entity_type_id' => 'node',
        'target_bundle' => 'article',
      ]
    )
      ->setThirdPartySetting('content_translation', 'enabled', true)
      ->save();

    ContentLanguageSettings::create(
      [
        'target_entity_type_id' => 'node',
        'target_bundle' => 'page',
      ]
    )
      ->setThirdPartySetting('content_translation', 'enabled', true)
      ->save();

    $this->createDefaultContent(5, 5, true, true, static::IS_MULTILINGUAL, false);


    $this->user = $this->drupalCreateUser(['view fusion_connector node--article', 'view fusion_connector node--page'], 'testUser', true);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that a disabled entity will not load and return a 404 reponse
   */
  public function testReadMultilang()
  {
    $output = Json::decode($this->drupalGet('/ca/fusion/node/article/' . $this->nodes[0]->uuid()));
    $this->assertEquals($this->nodes[0]->getTranslation('ca')->getTitle(), $output['data']['attributes']['title']);
  }

  /**
   * Tests that a disabled language will not load and return a 403 reponse
   */
  public function testReadMultilangDisabled()
  {
    $disabledLanguages = \Drupal::configFactory()->getEditable('fusion_connector.settings');
    $disabledLanguages->set('disabled_languages', ['ca']);
    $disabledLanguages->save();
    $this->rebuildAll();

    Json::decode($this->drupalGet('/ca/fusion/node/article/' . $this->nodes[0]->uuid()));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that when disabling a language for an entity, the response will be a 403
   */
  public function testReadMultilangDisabledEntity()
  {
    $page = $this->drupalCreateNode([
      'title' => 'Hello World'
    ]);
    $page->addTranslation('ca', [
      'title' => 'Hello World (ca)'
    ]);
    $page->save();

    Json::decode($this->drupalGet('/ca/fusion/node/article/' . $this->nodes[0]->uuid()));
    $this->assertSession()->statusCodeEquals(200);

    $disabledLanguages = \Drupal::configFactory()->getEditable('fusion_connector.settings');
    $disabledLanguages->set('disabled_entity_type_languages', ['node--article' => ['ca']]);
    $disabledLanguages->save();
    $this->rebuildAll();

    Json::decode($this->drupalGet('/ca/fusion/node/article/' . $this->nodes[0]->uuid()));
    $this->assertSession()->statusCodeEquals(403);

    $output = Json::decode($this->drupalGet('/ca/fusion/node/article/'));
    $this->assertCount(0, $output['data']);

    Json::decode($this->drupalGet('/ca/fusion/node/page/'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
