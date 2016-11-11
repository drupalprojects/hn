<?php

namespace Drupal\api_menu\Plugin\rest\resource;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "menu_rest_resource",
 *   label = @Translation("Menu rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/menu/{id}",
 *   }
 * )
 */
class MenuRestResource extends ResourceBase
{

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * A list of menu items.
   *
   * @var array
   */
  protected $menuItems = array();

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('api_menu'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($menu_name = NULL) {
    if($menu_name) {
      $permission = 'View published content';
      if(!$this->currentUser->hasPermission($permission)) {
        throw new AccessDeniedHttpException();
      }

      $menu_tree = \Drupal::menuTree();

      // Set the parameters.
      $parameters = new MenuTreeParameters();
      $parameters->onlyEnabledLinks();

      // Load the tree based on this set of parameters.
      $tree = $menu_tree->load($menu_name, $parameters);
      // Transform the tree using the manipulators you want.
      $manipulators = array(
        // Only show links that are accessible for the current user.
        array('callable' => 'menu.default_tree_manipulators:checkAccess'),
        // Use the default sorting of menu links.
        array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
      );
      $tree = $menu_tree->transform($tree, $manipulators);

      // Finally, build a renderable array from the transformed tree.
      $menu = $menu_tree->build($tree);

      $this->getMenuItems($menu['#items'], $this->menuItems);

      if(!empty($this->menuItems)) {
        $response = new ResourceResponse($this->menuItems);
        $response->addCacheableDependency($this->menuItems);
        return $response;
      }
      throw new NotFoundHttpException(t('Menu items for menu name @menu were not found', array('@menu' => $menu_name)));
    }
    throw new HttpException(t("Entity wasn't provided"));
  }

  /**
   * Generate the menu tree we can use in JSON.
   *
   * @param array $tree
   *   The menu tree.
   * @param array $items
   *   The already created items.
   */
  private function getMenuItems(array $tree, array &$items = array()) {
    foreach ($tree as $item_value) {
      /* @var $org_link \Drupal\Core\Menu\MenuLinkDefault */
      $org_link = $item_value['original_link'];
      $item_name = $org_link->getDerivativeId();
      if(empty($item_name)) {
        $item_name = $org_link->getBaseId();
      }

      /* @var $url \Drupal\Core\Url */
      $url = $item_value['url'];

      $external = FALSE;
      if($url->isExternal()) {
        $uri = $url->getUri();
        $external = TRUE;
      } else {
        $uri = $url->getInternalPath();
      }

      $items[$item_name] = array(
        'key' => $item_name,
        'title' => $org_link->getTitle(),
        'uri' => $uri,
        'external' => $external,
      );

      if(!empty($item_value['below'])) {
        $items[$item_name]['below'] = array();
        $this->getMenuItems($item_value['below'], $items[$item_name]['below']);
      }
    }
  }
}
