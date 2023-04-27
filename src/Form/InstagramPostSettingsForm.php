<?php

namespace Drupal\instag\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class InstagramPostSettingsForm.
 */
class InstagramPostSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'instag_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'instag.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('instag.settings');

    $form['help'] = [
      '#markup' => $this->t('Enter your Instagram credentials and configure the cache settings here. To import Instagram posts, run the following Drush command: <code>drush instag:import [username]</code>.'),
    ];

    $form['auth'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Authentication'),
      '#description' => $this->t('Enter your Instagram credentials.'),
    ];

    $form['auth']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
      '#default_value' => $config->get('username'),
    ];

    $form['auth']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('Enter your Instagram password. Once stored the password will not be displayed in this text field.'),
    ];

    $form['cache'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cache'),
    ];

    $form['cache']['cache_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the name of directory, without starting or trailing slash, to hold API cache data. The directory will be created in the public files directory.'),
      '#default_value' => $config->get('cache_dir'),
    ];

    $form['cache']['cache_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Lifetime'),
      '#required' => TRUE,
      '#default_value' => $config->get('cache_lifetime'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('instag.settings')
      ->set('username', $values['username'])
      ->set('cache_lifetime', $values['cache_lifetime']);

    // Make sure the cache directory exists and is writable.
    if (!empty($values['cache_dir'])) {
      $destination = 'public://' . $values['cache_dir'];
      $file_system = \Drupal::service('file_system');
      $file_system->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $this->config('instag.settings')
        ->set('cache_dir', $values['cache_dir']);
    }

    // Only save the password if it has been entered.
    if (!empty($values['password'])) {
      $this->config('instag.settings')
        ->set('password', $values['password']);
    }

    // Save settings.
    $this->config('instag.settings')->save();

    parent::submitForm($form, $form_state);
  }

}
