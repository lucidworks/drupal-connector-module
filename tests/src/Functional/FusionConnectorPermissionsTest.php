<?php

namespace Drupal\Tests\fusion_connector\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\jsonapi\Functional\JsonApiFunctionalTestBase;
use Drupal\user\Entity\Role;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group fusion_connector
 */
class FusionConnectorPermissionsTest extends JsonApiFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['fusion_connector'];

  /**
   * Test that the /fusion is not available for anonymous user without access.
   */
  public function testUserNoAccessContentPermission() {

    // Revoke access content permission for anonymous user.
    $role = Role::load(Role::ANONYMOUS_ID);
    $role->revokePermission('access content')->save();

    Json::decode($this->drupalGet('/fusion'));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test that /fusion is not available.
   */
  public function testUserNoAccessToEntities() {
    $disabledEntities = \Drupal::configFactory()->getEditable(
      'fusion_connector.settings'
    );
    $disabledEntities->set(
      'disabled_entities',
      ['node--article', 'taxonomy_term--tags']
    );
    $disabledEntities->save();
    $this->rebuildAll();

    $this->drupalLogin($this->user);
    $response = Json::decode($this->drupalGet('/fusion'));

    $this->assertNotNull($response);
    $this->assertIsArray($response['data']);
    $this->assertEqual(count($response['data']), 0);
  }

  /**
   * Tests that the entity page loads with a 200 response and valid content.
   */
  public function testAccessPermissions() {
    // Generate content.
    $this->createDefaultContent(
      3,
      1,
      FALSE,
      FALSE,
      static::IS_NOT_MULTILINGUAL
    );

    // Create a user only with view fusion_connector taxonomy_term--tags permission
    $user = $this->drupalCreateUser(
      ['view fusion_connector taxonomy_term--tags', 'view fusion_connector node--article'],
      'testUserTagAccess');
    $this->drupalLogin($user);

    // Get the taxonomy tags list.
    $response = Json::decode($this->drupalGet('/fusion/taxonomy_term/tags'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertNotNull($response);
    $this->assertIsArray($response['data']);
    // Check that we have one element in array.
    $this->assertEqual(count($response['data']), 1);
    $this->assertEqual(
      $response['data'][0]['attributes']['name'],
      $this->tags[0]->getName()
    );

    // Get the available articles.
    $response = Json::decode($this->drupalGet('/fusion/node/article'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertNotNull($response);
    $this->assertIsArray($response['data']);
    // Check that we have response,
    $this->assertEqual(count($response['data']), 3);
    $this->assertArrayNotHasKey(
      'field_sort1',
      $response['data'][0]['attributes']
    );
    $this->assertArrayHasKey('title', $response['data'][0]['attributes']);
    $this->drupalLogout();

    // Create a user only with view fusion_connector node--article permission.
    $user = $this->drupalCreateUser(
      ['view fusion_connector node--article'],
      'testUserArticleAccess'
    );
    $this->drupalLogin($user);

    // Get the articles list.
    $response = Json::decode($this->drupalGet('/fusion/node/article'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertNotNull($response);
    $this->assertIsArray($response['data']);
    // Check that we have one element in array.
    $this->assertEqual(count($response['data']), 3);
    $this->assertArrayHasKey('field_sort1', $response['data'][0]['attributes']);
    $this->assertArrayHasKey('title', $response['data'][0]['attributes']);
    $this->assertEqual(
      $response['data'][0]['attributes']['title'],
      $this->nodes[0]->getTitle()
    );

    // Get the available tags.
    $response = Json::decode($this->drupalGet('/fusion/taxonomy_term/tags'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertNotNull($response);
    $this->assertIsArray($response['data']);
    // Check that we have no response,
    $this->assertEqual(count($response['data']), 0);
    $this->drupalLogout();
  }

}
