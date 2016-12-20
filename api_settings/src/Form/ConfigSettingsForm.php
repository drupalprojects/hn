<?php

namespace Drupal\api_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Entity\Menu;

/**
 * Configure example settings for this site.
 */
class ConfigSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_settings_config';
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

    $form['show_share_buttons'] = [
      '#type' => 'checkbox',
      '#title' => 'Show share buttons',
      '#default_value' => $config->get('show_share_buttons'),
    ];

    $form['countries_link'] = [
      '#type' => 'textfield',
      '#title' => 'Countries link',
      '#default_value' => $config->get('countries_link'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('api_settings.config');

    $config->set('show_share_buttons', $form_state->getValue('show_share_buttons'));
    $config->set('countries_link', $form_state->getValue('countries_link'));
    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
