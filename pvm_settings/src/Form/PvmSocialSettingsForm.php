<?php

namespace Drupal\pvm_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class PvmSocialSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pvm_social_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'pvm_settings.config_social',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('pvm_settings.config_social');

    $description = $this->t('Enter one value per line, in the format key|label.');

    $form['social_links'] = array(
      '#type' => 'textarea',
      '#title' => 'Social links',
      '#description' => $description,
      '#default_value' => $config->get('social_links'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $social_links_value = $values['social_links'];
    $this->config('pvm_settings.config_social ')
      ->set('social_links', $social_links_value)
      ->save();

    return parent::submitForm($form, $form_state);
  }

}
