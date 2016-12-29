<?php

namespace Drupal\api_settings\Helpers;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Menu trait.
 */
trait Menu {

  /**
   * List of menu's that are available in each language.
   *
   * @var availableMenus
   */
  public static $availableMenus = array(
    'main',
    'footer',
    'overlay',
    'disclaimer',
  );

  /**
   * Get all menu trees.
   */
  public static function get() {
    return array_map(function (LanguageInterface $language) {
      $menus = [];
      foreach (Menu::$availableMenus as $menu) {
        $menu_machine_name = \Drupal::config('pvm.settings')->get('menu.' . $language->getId() . '.' . $menu);
        $menus[$menu] = Menu::getMenuById($menu_machine_name, $language);
      }

      return $menus;
    }, \Drupal::languageManager()->getLanguages());
  }

  /**
   * Get full menu tree by menu id.
   *
   * @param string|null $menuName
   *   Menuitem ID.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   LanguageInterface.
   *
   * @return array|\Symfony\Component\HttpKernel\Exception\HttpException|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Returns all menuitems with their children
   */
  public static function getMenuById($menuName = NULL, LanguageInterface $language = NULL) {
    if ($menuName && $language) {

      // Get the menu Tree.
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

      if ($menu['#items']) {
        Menu::getMenuItems($menu['#items'], $menuItems, $language);
      }

      if (!empty($menuItems)) {
        return $menuItems;
      }

      return new NotFoundHttpException(t('Menu items for menu name @menu were not found', array('@menu' => $menuName)));
    }
    return new HttpException(t("Entity wasn't provided"));
  }

  /**
   * Recursive function to get all links in menu tree.
   *
   * @param array $tree
   *   The menu item tree.
   * @param array $items
   *   All items from a menu.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   LanguageInterface.
   */
  private static function getMenuItems(array $tree, array &$items, LanguageInterface $language) {
    foreach ($tree as $item_value) {
      /* @var $org_link \Drupal\Core\Menu\MenuLinkDefault */
      $org_link = $item_value['original_link'];
      $item_name = $org_link->getDerivativeId();
      if (empty($item_name)) {
        $item_name = $org_link->getBaseId();
      }

      /* @var $url \Drupal\Core\Url */
      $url = $item_value['url'];

      $prefix = '';

      $language_negotiation = \Drupal::config('language.negotiation')->get('url');

      if ($language_negotiation['source'] == LanguageNegotiationUrl::CONFIG_PATH_PREFIX) {
        $prefix = $language_negotiation['prefixes'][$language->getId()];
      }

      $external = $url->isExternal();
      $uri = NULL;
      if ($external) {
        $uri = $url->getUri();
      }
      if (!$external) {
        if (!empty($url->getInternalPath())) {
          $uri = $prefix . \Drupal::service('path.alias_manager')->getAliasByPath('/' . $url->getInternalPath(), $language->getId());
        }
        if (empty($url->getInternalPath())) {
          $uri = $prefix . $url->getInternalPath();
        }
      }

      $items[] = array(
        'key' => $item_name,
        'title' => $org_link->getTitle(),
        'uri' => $uri,
        'external' => $external,
      );

      if (!empty($item_value['below'])) {
        $items[count($items) - 1]['below'] = array();
        Menu::getMenuItems($item_value['below'], $items[count($items) - 1]['below'], $language);
      }
    }
  }

}
