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

      $form['user'] = [
        '#type' => 'details',
        '#title' => $this->t('Import user')
      ];

      $form['user']['username'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#description' => $this->t('The username of the user to import'),
      ];

      $form['user']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Import user'),
      ];

      $form['tag'] = [
        '#type' => 'details',
        '#title' => $this->t('Import tag')
      ];

      $form['tag']['hashtag'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Hashtag'),
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
      // Define batch.
      $batch = [
        'title' => t('Importing Instagram posts'),
        'operations' => [],
        'finished' => ['\Drupal\instag\Form\BatchImportForm', 'batchFinished'],
      ];

      /** @var \Drupal\instag\InstagramImporterInterface $importer */
      $importer = \Drupal::service('instag.importer');

      // Get posts.
      try {
        $values = $form_state->getValues();
        $posts = [];
        if ($values['submit'] == $this->t('Import user')) {
          $posts = $importer->getPosts($values['username']);
        }
        else {
          $posts = $importer->getPostsByTag($values['hashtag']);
        }

        // Add posts to batch.
        foreach ($posts as $post) {
          $batch['operations'][] = [['\Drupal\instag\Form\BatchImportForm', 'batchProcess'], [$post]];
        }

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
    public static function batchProcess(Media $post, &$context): void {
      /** @var \Drupal\instag\InstagramImporterInterface $importer */
      $importer = \Drupal::service('instag.importer');
      try {
        $importer->import($post);
        $context['message'] = 'Importing post ' . $post->getId();
      }
      catch (\Throwable $e) {
        $context['message'] = 'Error importing post ' . $post->getId();
        \Drupal::messenger()->addError(t('Error importing post @id: @message', ['@id' => $post->getId(), '@message' => $e->getMessage()]));
      }
    }

    /**
     * Batch finished callback.
     */
    public static function batchFinished($success, $results, $operations, $elapsed): void {
      if ($success) {
        \Drupal::messenger()->addStatus(t('The import has completed successfully.'));
      }
      else {
        \Drupal::messenger()->addError(t('There was an error with the batch import.'));
      }
    }
}
