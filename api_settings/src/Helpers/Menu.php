<?php

namespace Drupal\api_settings\Helpers;

use Drupal\Core\Menu\MenuTreeParameters;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait Menu
{
  public static function getMenuById($menuName = NULL) {
    if($menuName) {

      // Get the menu Tree
      $menuTree = \Drupal::menuTree();

      // Set the parameters.
      $parameters = new MenuTreeParameters();
      $parameters->onlyEnabledLinks();

      // Load the tree based on this set of parameters.
      $tree = $menuTree->load($menuName, $parameters);
      // Transform the tree using the manipulators you want.
      $manipulators = array(
        // Only show links that are accessible for the current user.
        array('callable' => 'menu.default_tree_manipulators:checkAccess'),
        // Use the default sorting of menu links.
        array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
      );
      $tree = $menuTree->transform($tree, $manipulators);

      // Finally, build a renderable array from the transformed tree.
      $menu = $menuTree->build($tree);

      $menuItems = [];

      Menu::getMenuItems($menu['#items'], $menuItems);

      if(!empty($menuItems)) {
        return $menuItems;
      }
      throw new NotFoundHttpException(t('Menu items for menu name @menu were not found', array('@menu' => $menuName)));
    }
    throw new HttpException(t("Entity wasn't provided"));
  }

  private static function getMenuItems(array $tree, array &$items = array()) {
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
        Menu::getMenuItems($item_value['below'], $items[$item_name]['below']);
      }
    }
  }
}