<?php

namespace Drupal\pvm_settings\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class PvmQASettingsForm extends ConfigFormBase {

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
      'pvm.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('pvm.settings');

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $languageId = $language->getId();
      $form['q_' . $languageId] = array(
        '#type' => 'textfield',
        '#title' => 'Question label for ' . $language->getName(),
        '#default_value' => $config->get('QA.q.' . $languageId),
      );
      $form['a_' . $language->getId()] = array(
        '#type' => 'textfield',
        '#title' => 'Answer label for ' . $language->getName(),
        '#default_value' => $config->get('QA.a.' . $languageId),
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('pvm.settings');

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $languageId = $language->getId();
      $config->set('QA.q.' . $languageId, $form_state->getValue('q_' . $languageId));
      $config->set('QA.a.' . $languageId, $form_state->getValue('a_' . $languageId));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
