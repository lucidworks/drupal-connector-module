<?php

namespace Drupal\Tests\fusion_connector\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiFunctionalTestBase;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group fusion_connector
 */
class FusionConnectorTest extends JsonApiFunctionalTestBase {

  protected $defaultTheme = 'stable';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['fusion_connector'];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->user = $this->drupalCreateUser(['view fusion_connector node--article'], 'testUser', true);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that the home page loads with a 200 response.
   */
  public function testLoadIndex()
  {
    $response =  Json::decode($this->drupalGet('/fusion/'));

    $this->assertSession()->statusCodeEquals(200);
    $this->assertNotNull($response);
  }

  /**
   * Tests that the home page loads with a 200 response.
   */
  public function testLoadNodeType()
  {
    $response =  Json::decode($this->drupalGet('/fusion/node/article'));

    $this->assertSession()->statusCodeEquals(200);
    $this->assertNotNull($response);
  }

}
