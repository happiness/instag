<?php

namespace Drupal\instag;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for instagram_post entity.
 */
class InstagramPostListBuilder extends EntityListBuilder {

  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_type, $storage);

    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter')
    );
  }

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
    $header['type'] = $this->t('Type');
    $header['likes'] = $this->t('Likes');
    $header['date'] = $this->t('Date');
    $header['user'] = $this->t('User ID');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\instag\Entity\InstagramPost */
    $row['id'] = $entity->id();
    $row['title'] = $entity->toLink()->toString();
    $row['type'] = $entity->type->value;
    $row['likes'] = $entity->likes->value;
    $row['date'] = $this->dateFormatter->format(strtotime($entity->date->value), 'html_date');
    $row['user'] = $entity->user->value ?? t('Custom post');
    $row['status'] = $entity->status->value ? t('Published') : t('Unpublished');
    return $row + parent::buildRow($entity);
  }

  /**
   * Returns a query object for loading entity IDs from the storage.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   A query object used to load entity IDs.
   */
  protected function getEntityListQuery(): QueryInterface {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort('date', 'DESC');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query;
  }

}
