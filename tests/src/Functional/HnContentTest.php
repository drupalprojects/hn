<?php

namespace Drupal\Tests\hn\Functional;

/**
 * Provides some basic tests with permissions of the HN module.
 *
 * @group hn
 */
class HnContentTest extends HnFunctionalTestBase {

  public static $modules = [
    'hn_test',
  ];

  /**
   * Tests a normal node without any references and only the default view mode.
   */
  public function testBasicNode() {
    $response = $this->getHnJsonResponse('/node/1');
    $r = $response['data'][$response['paths']['/node/1']];
    $defaults = hn_test_node_base(1);

    // Assure all data is as expected.
    $this->assertEquals($r['__hn'], [
      'view_modes' => ['default'],
      'entity' => [
        'type' => 'node',
        'bundle' => 'hn_test_basic_page',
      ],
      // TODO: Remove base_path from urls, see issue #2916393.
      'url' => base_path() . 'node/1',
      'status' => 200,
    ]);
    $this->assertEquals($r['title'], $defaults['title']);
    $this->assertEquals($r['body'], [
      'value' => $defaults['body'],
      'format' => '',
      'summary' => '',
    ]);
    $this->assertEquals($r['field_link'], [
      'uri' => 'https://www.google.com',
      'title' => '',
      'options' => [],
    ]);
    $this->assertEquals($r['field_reference'], []);
    $this->assertEquals($r['field_reference_teaser'], []);

    // Make sure teaser fields are not available in the default view mode.
    $this->assertFalse(isset($r['field_teaser_body']));
  }

}
