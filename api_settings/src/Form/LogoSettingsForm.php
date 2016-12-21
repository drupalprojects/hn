<?php

namespace Drupal\api_settings\Form;

use Drupal\file\Entity\File;
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
      $fid = $config->get('logo.' . $language->getId());
      $form['logo_' . $language->getId()] = [
        '#type' => 'managed_file',
        '#title' => 'Logo for ' . $language->getName(),
        '#default_value' => $fid ? [$fid] : NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [1024 * 1024],
        ],
        '#upload_location' => 'public://',
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
      $fid = NULL;
      $upload = $form_state->getValue('logo_' . $language->getId());
      if (!empty($upload[0])) {
        $fid = $upload[0];
        $file = File::load($fid);
        $file->setPermanent();
        $file->save();
      }

      $config->set('logo.' . $language->getId(), $fid);
    }

    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
