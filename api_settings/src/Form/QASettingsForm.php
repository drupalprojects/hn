<?php

/**
 * @file
 * Contains \Drupal\api_settings\Form\ApiSettingsForm
 */
namespace Drupal\api_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class QASettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_settings_qa';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'api_settings.qa',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('api_settings.qa');

    foreach (\Drupal::languageManager()->getLanguages() as $language){
      $form['q_' . $language->getId()] = array(
        '#type' => 'textfield',
        '#title' => 'Question label for ' . $language->getName(),
        '#default_value' => $config->get('q.' . $language->getId()),
      );
      $form['a_' . $language->getId()] = array(
        '#type' => 'textfield',
        '#title' => 'Answer label for ' . $language->getName(),
        '#default_value' => $config->get('a.' . $language->getId()),
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('api_settings.qa');

    foreach (\Drupal::languageManager()->getLanguages() as $language){
      $config->set('q.' . $language->getId(), $form_state->getValue('q_' . $language->getId()));
      $config->set('a.' . $language->getId(), $form_state->getValue('a_' . $language->getId()));
    }

    $config->save();

    return parent::submitForm($form, $form_state);
  }
}
