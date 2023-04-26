<?php

namespace Drupal\instag\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the instagram_post entity edit forms.
 */
class InstagramPostForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    $entity = $this->entity;
    if ($status == SAVED_UPDATED) {
      $this->messenger()
        ->addMessage($this->t('The Instagram post %post has been updated.', ['%post' => $entity->toLink()->toString()]));
    } else {
      $this->messenger()
        ->addMessage($this->t('The Instagram %post has been added.', ['%post' => $entity->toLink()->toString()]));
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }
}
