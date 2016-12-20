<?php

namespace Drupal\api_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Entity\Menu;

/**
 * Configure example settings for this site.
 */
class ApiSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'example_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'api_settings.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('api_settings.config');

    $all_menus = Menu::loadMultiple();

    $menus = array();
    foreach ($all_menus as $id => $menu) {
      $menus[$id] = $menu->label();
    }
    asort($menus);

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      foreach (['main', 'footer', 'overlay', 'disclaimer'] as $menu) {
        $form["menu_" . $language->getId() . "_$menu"] = array(
          '#type' => 'select',
          '#options' => $menus,
          '#title' => 'Menu ' . $language->getName() . ' ' . $menu,
          '#default_value' => $config->get("menu." . $language->getId() . ".$menu"),
        );
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('api_settings.config');

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      foreach (array('main', 'footer', 'overlay', 'disclaimer') as $menu) {
        $config->set("menu." . $language->getId() . ".$menu", $form_state->getValue("menu_" . $language->getId() . "_$menu"));
      }
    }

    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
