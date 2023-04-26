<?php

namespace Drupal\instag;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Provides a list controller for instagram_post entity.
 */
class InstagramPostListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('Instagram posts are fieldable entities. You can manage the fields on the <a href="@adminlink">settings page</a>.', array(
        '@adminlink' => Url::fromRoute('instag.settings', [], ['absolute' => 'true'])->toString(),
      )),
    ];

    $build += parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['title'] = $this->t('Title');
    $header['date'] = $this->t('Date');
    $header['created'] = $this->t('Created');
    $header['changed'] = $this->t('Changed');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\instag\Entity\InstagramPost */
    $row['id'] = $entity->id();
    $row['title'] = $entity->label();
    $row['date'] = $entity->date->value;
    $row['created'] = $entity->created->value;
    $row['changed'] = $entity->changed->value;
    return $row + parent::buildRow($entity);
  }

}
