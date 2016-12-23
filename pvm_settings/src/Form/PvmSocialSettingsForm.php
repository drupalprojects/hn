<?php

namespace Drupal\pvm_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

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
      'pvm_settings.social',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('pvm_settings.social');
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $lang = $language->getId();

      // Generate a details element per language.
      $form[$lang] = array(
        '#type' => 'details',
        '#title' => $language->getName(),
        '#open' => TRUE,
      );

      // Facebook.
      $form[$lang]['facebook_container'] = array(
        '#type' => 'details',
        '#title' => 'Facebook',
        '#open' => FALSE,
      );

      $value = $config->get('facebook_url.' . $lang);
      $form[$lang]['facebook_container']['facebook_url_' . $lang] = array(
        '#type' => 'url',
        '#title' => 'Facebook URL',
        '#description' => $this->t('Enter a valid Facebook URL e.g. https://www.facebook.com/username'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );

      $value = $config->get('facebook_button_text.' . $lang);
      $form[$lang]['facebook_container']['facebook_button_text_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'Button text',
        '#description' => $this->t('Enter the text to show on the button'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );

      $value = $config->get('facebook_image.' . $lang);
      $form[$lang]['facebook_container']['facebook_image_' . $lang] = [
        '#type' => 'managed_file',
        '#title' => 'Social page image',
        '#default_value' => $value ? [$value] : NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [1024 * 1024],
        ],
        '#upload_location' => 'public://',
      ];

      // Twitter.
      $form[$lang]['twitter_container'] = array(
        '#type' => 'details',
        '#title' => 'Twitter',
        '#open' => FALSE,
      );

      $value = $config->get('twitter_url.' . $lang);
      $form[$lang]['twitter_container']['twitter_url_' . $lang] = array(
        '#type' => 'url',
        '#title' => 'Twitter URL',
        '#description' => $this->t('Enter a valid Twitter URL e.g. https://www.twitter.com/username'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );

      $value = $config->get('twitter_button_text.' . $lang);
      $form[$lang]['twitter_container']['twitter_button_text_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'Button text',
        '#description' => $this->t('Enter the text to show on the button'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );

      $fid = $config->get('twitter_image.' . $lang);
      $form[$lang]['twitter_container']['twitter_image_' . $lang] = [
        '#type' => 'managed_file',
        '#title' => 'Social page image',
        '#default_value' => $fid ? [$fid] : NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [1024 * 1024],
        ],
        '#upload_location' => 'public://',
      ];

      // Pinterest.
      $form[$lang]['pinterest_container'] = array(
        '#type' => 'details',
        '#title' => 'Pinterest',
        '#open' => FALSE,
      );
      $value = $config->get('pinterest_url.' . $lang);
      $form[$lang]['pinterest_container']['pinterest_url_' . $lang] = array(
        '#type' => 'url',
        '#title' => 'Pinterest URL',
        '#description' => $this->t('Enter a valid Pinterest URL e.g. https://www.pinterest.com/username'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );
      $value = $config->get('pintereset_button_text.' . $lang);
      $form[$lang]['pinterest_container']['pinterest_button_text_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'Button text',
        '#description' => $this->t('Enter the text to show on the button'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );
      $fid = $config->get('pinterest_image.' . $lang);
      $form[$lang]['pinterest_container']['pinterest_image_' . $lang] = [
        '#type' => 'managed_file',
        '#title' => 'Social page image',
        '#default_value' => $fid ? [$fid] : NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [1024 * 1024],
        ],
        '#upload_location' => 'public://',
      ];

      // Youtube.
      $form[$lang]['youtube_container'] = array(
        '#type' => 'details',
        '#title' => 'YouTube',
        '#open' => FALSE,
      );
      $value = $config->get('youtube_username.' . $lang);
      $form[$lang]['youtube_container']['youtube_username_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'Youtube Username of Channel',
        '#description' => $this->t('Enter a valid Youtube username e.g. Username or channel e.g. channel/channelid'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );
      $value = $config->get('youtube_button_text.' . $lang);
      $form[$lang]['youtube_container']['youtube_button_text_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'Button text',
        '#description' => $this->t('Enter the text to show on the button'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );
      $fid = $config->get('youtube_image.' . $lang);
      $form[$lang]['youtube_container']['youtube_image_' . $lang] = [
        '#type' => 'managed_file',
        '#title' => 'Social page image',
        '#default_value' => $fid ? [$fid] : NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [1024 * 1024],
        ],
        '#upload_location' => 'public://',
      ];

      // VK.
      $form[$lang]['vk_container'] = array(
        '#type' => 'details',
        '#title' => 'VK',
        '#open' => FALSE,
      );
      $value = $config->get('vk_username.' . $lang);
      $form[$lang]['vk_container']['vk_username_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'VK Username of Channel',
        '#description' => $this->t('Enter a valid VK url e.g. https://vk.com/username'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );
      $value = $config->get('vk_button_text.' . $lang);
      $form[$lang]['vk_container']['vk_button_text_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'Button text',
        '#description' => $this->t('Enter the text to show on the button'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );
      $fid = $config->get('vk_image.' . $lang);
      $form[$lang]['vk_container']['vk_image_' . $lang] = [
        '#type' => 'managed_file',
        '#title' => 'Social page image',
        '#default_value' => $fid ? [$fid] : NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [1024 * 1024],
        ],
        '#upload_location' => 'public://',
      ];

      // Skype.
      $form[$lang]['skype_container'] = array(
        '#type' => 'details',
        '#title' => 'Skype',
        '#open' => FALSE,
      );
      $value = $config->get('skype_url.' . $lang);
      $form[$lang]['skype_container']['skype_url_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'Skype Username of Channel',
        '#description' => $this->t('Enter a valid Skype username e.g. social_us'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );
      $value = $config->get('skype_button_text.' . $lang);
      $form[$lang]['skype_container']['skype_button_text_' . $lang] = array(
        '#type' => 'textfield',
        '#title' => 'Button text',
        '#description' => $this->t('Enter the text to show on the button'),
        '#default_value' => ($value) ? $value : '',
        '#open' => FALSE,
      );
      $fid = $config->get('skype_image.' . $lang);
      $form[$lang]['skype_container']['skype_image_' . $lang] = [
        '#type' => 'managed_file',
        '#title' => 'Social page image',
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
    $config = $this->config('pvm_settings.social');

    $values = $form_state->getValues();

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $lang = $language->getId();

      // Facebook.
      $config->set("facebook_url.$lang", $values['facebook_url_' . $lang]);
      $config->set("facebook_button_text.$lang", $values['facebook_button_text_' . $lang]);
      $upload = $values["facebook_image_$lang"];
      if (!empty($upload[0])) {
        $fid = $upload[0];
        $file = File::load($fid);
        $file->setPermanent();
        $file->save();
        $config->set("facebook_image.$lang", $fid);
      }

      // Twitter.
      $config->set("twitter_url.$lang", $values['twitter_url_' . $lang]);
      $config->set("twitter_button_text.$lang", $values['twitter_button_text_' . $lang]);
      $upload = $values["facebook_image_$lang"];
      if (!empty($upload[0])) {
        $fid = $upload[0];
        $file = File::load($fid);
        $file->setPermanent();
        $file->save();
        $config->set("twitter_image.$lang", $fid);
      }

      // Pinterest.
      $config->set("pinterest_url.$lang", $values['pinterest_url_' . $lang]);
      $config->set("pinterest_button_text.$lang", $values['pinterest_button_text_' . $lang]);
      $upload = $values["pinterest_image_$lang"];
      if (!empty($upload[0])) {
        $fid = $upload[0];
        $file = File::load($fid);
        $file->setPermanent();
        $file->save();
        $config->set("pinterest_image.$lang", $fid);
      }

      // Youtube.
      $config->set("youtube_username.$lang", $values['youtube_username_' . $lang]);
      $config->set("youtube_button_text.$lang", $values['youtube_button_text_' . $lang]);
      $upload = $values["vk_image_$lang"];
      if (!empty($upload[0])) {
        $fid = $upload[0];
        $file = File::load($fid);
        $file->setPermanent();
        $file->save();
        $config->set("youtube_image.$lang", $fid);
      }

      // VK.
      $config->set("vk_username.$lang", $values['vk_username_' . $lang]);
      $config->set("vk_button_text.$lang", $values['vk_button_text_' . $lang]);
      $upload = $values["vk_image_$lang"];
      if (!empty($upload[0])) {
        $fid = $upload[0];
        $file = File::load($fid);
        $file->setPermanent();
        $file->save();
        $config->set("vk_image.$lang", $fid);
      }

      // Skype.
      $config->set("skype_username.$lang", $values['skype_username_' . $lang]);
      $config->set("skype_button_text.$lang", $values['skype_button_text_' . $lang]);
      $upload = $values["skype_image_$lang"];
      if (!empty($upload[0])) {
        $fid = $upload[0];
        $file = File::load($fid);
        $file->setPermanent();
        $file->save();
        $config->set("skype_image.$lang", $fid);
      }

    }

    $config->save();

    return parent::submitForm($form, $form_state);

  }

}
