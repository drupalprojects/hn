<?php

namespace Drupal\api_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class SocialSettingsForm extends ConfigFormBase {

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

    $form['social_token'] = [
      '#type' => 'textfield',
      '#title' => 'Social admin token',
      '#default_value' => $config->get('social.social_token'),
    ];

    $form['social_suffix'] = [
      '#type' => 'textfield',
      '#title' => 'Social suffix',
      '#default_value' => $config->get('social.social_suffix'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('api_settings.config');

    $config->set('social.social_token', $form_state->getValue('social_token'));
    $config->set('social.social_suffix', $form_state->getValue('social_suffix'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
