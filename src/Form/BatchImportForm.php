<?php

declare(strict_types=1);

namespace Drupal\instag\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Instagram\Model\Media;

class BatchImportForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string {
      return 'instag_batch_import_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array {
      $store = \Drupal::keyValue('instag');

      $form['limit'] = [
        '#type' => 'number',
        '#title' => $this->t('Limit'),
        '#min' => 1,
        '#max' => 100,
        '#default_value' => $store->get('limit', 50),
        '#description' => $this->t('Maximum number of posts to import.'),
      ];

      $form['user'] = [
        '#type' => 'details',
        '#title' => $this->t('Import user'),
        '#open' => TRUE,
      ];

      $form['user']['username'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#default_value' => $store->get('username'),
        '#description' => $this->t('The username of the user to import.'),
      ];

      $form['user']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Import user'),
      ];

      $form['tag'] = [
        '#type' => 'details',
        '#title' => $this->t('Import tag'),
        '#open' => TRUE,
      ];

      $form['tag']['hashtag'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Hashtag'),
        '#default_value' => $store->get('hashtag'),
        '#description' => $this->t('The hashtag to import, without the <em>#</em> symbol.'),
      ];

      $form['tag']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Import tag'),
      ];

      return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void {
      // Save entered values for next time.
      $store = \Drupal::keyValue('instag');
      $store->set('username', $form_state->getValue('username'));
      $store->set('hashtag', $form_state->getValue('hashtag'));

      // Define batch.
      $batch = [
        'title' => t('Importing Instagram posts'),
        'operations' => [],
        'finished' => ['\Drupal\instag\Form\BatchImportForm', 'batchFinished'],
      ];

      // Get posts.
      try {
        $values = $form_state->getValues();
        $trigger = $form_state->getTriggeringElement()['#id'];
        if ($trigger == 'edit-submit') {
          $method = 'getPosts';
          $id = $values['username'];
        }
        elseif ($trigger == 'edit-submit--2') {
          $method = 'getPostsByTag';
          $id = $values['hashtag'];
        }
        else {
          return;
        }

        $batch['operations'][] = [['\Drupal\instag\Form\BatchImportForm', 'batchProcess'], [$method, $id, $values['limit']]];

        // Set batch.
        batch_set($batch);
      }
      catch (\Throwable $e) {
        $this->messenger()->addError($this->t('Error fetching posts: @message', ['@message' => $e->getMessage()]));
      }
    }

    /**
     * Batch process callback.
     */
    public static function batchProcess(string $method, string $id, int $limit, &$context): void {
      /** @var \Drupal\instag\InstagramImporterInterface $importer */
      $importer = \Drupal::service('instag.importer');

      try {
        $cid = 'instag_' . $method . '_' . $id;
        $posts = NULL;
        if ($cache = \Drupal::cache()->get($cid)) {
          $posts = $cache->data;
        }
        else {
          $posts = $importer->$method($id);
          \Drupal::cache()->set($cid, $posts, strtotime('+1 hour'));
        }
      }
      catch (\Throwable $e) {
        \Drupal::messenger()->addError(t('Error fetching posts: @message', ['@message' => $e->getMessage()]));
        return;
      }

      if (!isset($context['sandbox']['progress'])) {
        if (count($posts) < $limit) {
          $limit = count($posts);
        }
        $context['sandbox']['progress'] = 0;
        $context['sandbox']['current_id'] = 0;
        $context['sandbox']['max'] = $limit;
      }

      $batch_size = 1;
      $posts = array_slice($posts, $context['sandbox']['progress'], $batch_size);

      foreach ($posts as $post) {
        try {
          $entity = $importer->import($post);
          $context['results'][] = $entity->label();
          $context['message'] = t('Imported post @title', ['@title' => $entity->label()]);
        }
        catch (\Throwable $e) {
          $context['message'] = t('Error importing post @id', ['@id' => $post->getId()]);
          \Drupal::messenger()->addError(t('Error importing post @id: @message', ['@id' => $post->getId(), '@message' => $e->getMessage()]));
        }

        $context['sandbox']['progress']++;
        $context['sandbox']['current_id'] = $post->getId();
      }

      if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
        $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
      }
    }

    /**
     * Batch finished callback.
     */
    public static function batchFinished($success, $results, $operations, $elapsed): void {
      if ($success) {
        \Drupal::messenger()->addStatus(t('The import was completed successfully.'));
      }
      else {
        \Drupal::messenger()->addError(t('Error occurred during import. Please try again later.'));
      }
    }
}
