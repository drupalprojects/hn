<?php

namespace Drupal\pvm_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Entity\Menu;

/**
 * Configure example settings for this site.
 */
class PvmMenuSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_settings_menu';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'api_settings.menu',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('api_settings.menu');

    $all_menus = Menu::loadMultiple();

    $menus = array();
    foreach ($all_menus as $id => $menu) {
      $menus[$id] = $menu->label();
    }
    asort($menus);

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $languageId = $language->getId();
      foreach (['main', 'footer', 'overlay', 'disclaimer'] as $menu) {
        $form["menu_{$languageId}_{$menu}"] = array(
          '#type' => 'select',
          '#options' => $menus,
          '#title' => 'Menu ' . $language->getName() . ' ' . $menu,
          '#default_value' => $config->get("menu.{$languageId}.{$menu}"),
        );
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('api_settings.menu');

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $languageId = $language->getId();
      foreach (array('main', 'footer', 'overlay', 'disclaimer') as $menu) {
        $config->set("menu.{$languageId}.{$menu}", $form_state->getValue("menu_{$languageId}_{$menu}"));
      }
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
