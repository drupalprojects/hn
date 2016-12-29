<?php

namespace Drupal\pvm_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class PvmSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pvm_settings_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'pvm.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('pvm.settings');

    $form['show_share_buttons'] = [
      '#type' => 'checkbox',
      '#title' => 'Show share buttons',
      '#default_value' => $config->get('general.show_share_buttons'),
    ];

    $form['countries_link'] = [
      '#type' => 'textfield',
      '#title' => 'Countries link',
      '#default_value' => $config->get('general.countries_link'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('pvm.settings');

    $config->set('general.show_share_buttons', $form_state->getValue('show_share_buttons'));
    $config->set('general.countries_link', $form_state->getValue('countries_link'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
