<?php

namespace Drupal\pvm_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Configure example settings for this site.
 */
class PvmSocialSettingsForm extends ConfigFormBase {

  protected $socialChannels = array(
    'facebook' => 'Facebook',
    'twitter' => 'Twitter',
    'youtube' => 'Youtube',
    'pinterest' => 'Pinterest',
    'skype' => 'Skype',
  );

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
      'pvm.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('pvm.settings');

    $value = $config->get('social.social_token');
    $form['social_token'] = array(
      '#type' => 'textfield',
      '#default_value' => ($value) ? $value : '',
      '#title' => $this->t('Social token'),
      '#description' => $this->t('This is the token used for the coosto api.'),
      '#open' => FALSE,
    );

    $value = $config->get('social.social_suffix');
    $form['social_suffix'] = array(
      '#type' => 'textfield',
      '#default_value' => ($value) ? $value : '',
      '#title' => $this->t('Social suffix'),
      '#description' => $this->t('This is the suffix used for the coosto api.'),
      '#open' => FALSE,
    );

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $lang = $language->getId();

      // Generate a details element per language.
      $form[$lang] = array(
        '#type' => 'details',
        '#title' => $language->getName(),
        '#open' => TRUE,
      );

      // Print each social.
      foreach ($this->socialChannels as $key => $channel) {

        // Container for social channel.
        $form[$lang][$key . '_container'] = array(
          '#type' => 'details',
          '#title' => $channel,
          '#open' => FALSE,
        );

        // Url for a social channel.
        $value = $config->get('social_channels.' . $key . "_url.$lang");
        $form[$lang][$key . '_container'][$key . '_url_' . $lang] = array(
          '#type' => 'url',
          '#title' => "$channel url",
          '#description' => $this->t('Enter a valid url'),
          '#default_value' => ($value) ? $value : '',
          '#open' => FALSE,
        );

        // Icon class for social share channel.
        $value = $config->get('social_channels.' . $key . "_icon.$lang");
        $form[$lang][$key . '_container'][$key . '_icon_' . $lang] = array(
          '#type' => 'textfield',
          '#title' => "$channel icon class",
          '#description' => $this->t("Enter a valid icon class"),
          '#default_value' => ($value) ? $value : '',
          '#open' => FALSE,
        );

        // Text that should be shown for a share button.
        $value = $config->get('social_channels.' . $key . '_button_text.' . $lang);
        $form[$lang][$key . '_container'][$key . '_button_text_' . $lang] = array(
          '#type' => 'textfield',
          '#title' => 'Button text',
          '#description' => $this->t('Enter the text to show on the button'),
          '#default_value' => ($value) ? $value : '',
          '#open' => FALSE,
        );

        // Image that should be shown for a share image.
        $value = $config->get('social_channels.' . $key . '_image.' . $lang);
        $form[$lang][$key . '_container'][$key . '_image_' . $lang] = [
          '#type' => 'managed_file',
          '#title' => 'Social page image',
          '#default_value' => $value ? [$value] : NULL,
          '#upload_validators' => [
            'file_validate_extensions' => ['gif png jpg jpeg'],
            'file_validate_size' => [1024 * 1024],
          ],
          '#upload_location' => 'public://',
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get pvm.setings config.
    $config = $this->config('pvm.settings');

    // Get the values from the form_state.
    $values = $form_state->getValues();

    $config->set('social.social_token', $values['social_token']);
    $config->set('social.social_suffix', $values['social_suffix']);
    $config->save();

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $lang = $language->getId();
      foreach (array_keys($this->socialChannels) as $key) {
        // Set the social channel url.
        $config->set('social_channels.' . $key . "_url.$lang", $values[$key . "_url_$lang"]);

        // Set the social channel button text.
        $config->set('social_channels.' . $key . "_button_text.$lang", $values[$key . "_button_text_$lang"]);

        // Set the social channel icon class.
        $config->set('social_channels.' . $key . "_icon.$lang", $values[$key . "_icon_$lang"]);

        // Set the uploaded image.
        $upload = $values[$key . "_image_$lang"];
        if (!empty($upload[0])) {
          $fid = $upload[0];
          $file = File::load($fid);
          $file->setPermanent();
          $file->save();
          $config->set('social_channels.' . $key . "_image.$lang", $fid);
        }
      }
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
