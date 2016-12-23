<?php

namespace Drupal\pvm_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Configure example settings for this site.
 */
class PvmSocialSharingSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pvm_social_sharing_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'pvm_settings.socialsharing',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('pvm_settings.socialsharing');
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $lang = $language->getId();

      // Generate a details element per language.
      $form[$lang] = array(
        '#type' => 'details',
        '#title' => $language->getName(),
        '#open' => TRUE,
      );

      $value = $config->get('label.' . $lang);
      $form[$lang]['label_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'Social sharing label text',
        '#description' => $this->t('Enter the text to show as label'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );

      $value = $config->get('channels.' . $lang);
      $options = array(
        'facebook' => 'Facebook',
        'twitter' => 'Twitter',
      );
      $form[$lang]['channels_' . $lang] = array(
        '#type' => 'checkboxes',
        '#title' => 'Social sharing options',
        '#options' => $options,
        '#default_value' => ($value) ? $value : array(),
      );


    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('pvm_settings.socialsharing');

    $values = $form_state->getValues();

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $lang = $language->getId();

      // Label.
      $config->set("label.$lang", $values['label_' . $lang]);

      // Channels.
      $config->set("channels.$lang", $values['channels_' . $lang]);

    }

    $config->save();

    return parent::submitForm($form, $form_state);

  }

}
