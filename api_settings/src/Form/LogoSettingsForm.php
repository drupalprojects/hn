<?php

namespace Drupal\api_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for this site.
 */
class LogoSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_settings_logo';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'api_settings.logo',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('api_settings.logo');

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $form['logo_' . $language->getId()] = [
        '#type' => 'managed_file',
        '#title' => 'Logo for ' . $language->getName(),
        '#default_value' => $config->get('logo.' . $language->getId()),
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [1024 * 1024],
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('api_settings.logo');

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $config->set('logo.' . $language->getId(), $form_state->getValue('logo_' . $language->getId()));
    }

    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
