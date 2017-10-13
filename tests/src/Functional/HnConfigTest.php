<?php

namespace Drupal\Tests\hn\Functional;

use Drupal\node\Entity\Node;

/**
 * Provides some basic tests with permissions of the HN module.
 *
 * @group hn_config
 */
class HnConfigTest extends HnFunctionalTestBase {

  public static $modules = [
    'hn_config',
    'hn_test_menu',
  ];

  /**
   * The internal node url.
   *
   * @var string
   */
  private $nodeUrl;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->makeEverythingReady();

    $test_node = Node::create([
      'type' => 'hn_test_basic_page',
      'title' => 'Test node',
    ]);

    $test_node->save();

    // We get the internal path to exclude the subdirectory the Drupal is
    // installed in.
    $this->nodeUrl = $test_node->toUrl()->getInternalPath();
  }

  /**
   * Assure no data is added when all menus are disabled.
   */
  public function testWithEverythingDisabled() {
    $config = \Drupal::configFactory()->getEditable('hn_config.settings');
    $config->set('menus', []);
    $config->save();

    $response = $this->getHnResponse($this->nodeUrl);
    $response = json_decode($response, TRUE);

    $this->assertTrue(!empty($response['paths'][$this->nodeUrl]));
    $this->assertTrue(empty($response['data']['config__menus']));
  }

  /**
   * Assure menu data is correctly served when enabled.
   */
  public function testWithCustomMenuLinks() {
    $config = \Drupal::configFactory()->getEditable('hn_config.settings');
    $config->set('menus', ['main', 'tools']);
    $config->save();

    $response = $this->getHnResponse($this->nodeUrl);
    $response = json_decode($response, TRUE);

    $response_menus = $response['data']['config__menus'];

    // Nothing was added to the tools menu, so it should be empty.
    $this->assertEquals($response_menus['tools'], []);

    // Check if all the data from the root link is correct.
    $this->assertTrue(!empty($response_menus['main'][0]['key']));
    $this->assertEquals($response_menus['main'][0]['title'], 'Custom menu link');
    $this->assertEquals($response_menus['main'][0]['url'], '/internal-node-link');

    // Check if all the data from the nested link is correct.
    $this->assertTrue(!empty($response_menus['main'][0]['below'][0]['key']));
    $this->assertEquals($response_menus['main'][0]['below'][0]['title'], 'Nested menu link');
    $this->assertEquals($response_menus['main'][0]['below'][0]['url'], 'http://external.link');
  }

}
