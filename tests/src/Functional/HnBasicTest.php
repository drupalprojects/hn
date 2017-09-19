<?php

namespace Drupal\Tests\hn\Functional;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Provides some basic tests with permissions of the HN module.
 *
 * @group hn
 */
class HnBasicTest extends HnFunctionalTestBase {

  public static $modules = [
    'hn_test',
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
   * Tests the response if the user doesn't have the 'restful' permission.
   *
   * If the 'restful get hn_rest_resource' permission isn't enabled, the
   * endpoint should return a 403 status.
   */
  public function testWithoutRestPermission() {
    /** @var \Drupal\user\Entity\Role $anonymous */
    $anonymous = Role::load(RoleInterface::ANONYMOUS_ID);
    $anonymous->revokePermission('access content');
    $anonymous->revokePermission('restful get hn_rest_resource');
    $anonymous->trustData()->save();
    $this->getHnResponse($this->nodeUrl);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests the response if the user doesn't have the 'access' permission.
   *
   * If the user has access to the hn_rest_resource but not to 'access content',
   * the status should be 200 but the response should contain the 403 page.
   */
  public function testWithoutContentPermission() {
    /** @var \Drupal\user\Entity\Role $anonymous */
    $anonymous = Role::load(RoleInterface::ANONYMOUS_ID);
    $anonymous->grantPermission('restful get hn_rest_resource');
    $anonymous->revokePermission('access content');
    $anonymous->trustData()->save();
    $response = json_decode($this->getHnResponse($this->nodeUrl), TRUE);

    // @todo: Add 403 and 404 fallback page to site.settings config
    // $this->assertSession()->statusCodeEquals(200);
    // $this->assertEquals($response['data'][
    // $response['paths'][$this->nodeUrl]
    // ]['__hn']['status'], 403);
    $this->assertEquals($response['message'], 'Can\'t find suitable entity and no 404 is defined. Please enter a 404 url in the site.system settings.');
  }

  /**
   * Checks the response if the user has all needed permissions.
   *
   * If the user has access to the hn_rest_resource AND to 'access content',
   * the status should be 200 and the status of the entity also 200.
   */
  public function testWithPermissions() {
    /** @var \Drupal\user\Entity\Role $anonymous */
    $anonymous = Role::load(RoleInterface::ANONYMOUS_ID);
    $anonymous->grantPermission('restful get hn_rest_resource');
    $anonymous->grantPermission('access content');
    $anonymous->trustData()->save();
    $response = $this->getHnResponse($this->nodeUrl);
    $response = json_decode($response, TRUE);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertEquals($response['data'][$response['paths'][$this->nodeUrl]]['__hn']['status'], 200);
  }

}
