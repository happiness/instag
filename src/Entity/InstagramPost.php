<?php
declare(strict_types=1);

namespace Drupal\instag\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Instagram post entity.
 *
 * @ContentEntityType(
 *   id = "instagram_post",
 *   label = @Translation("Instagram post"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\instag\InstagramPostListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\instag\Form\InstagramPostForm",
 *       "edit" = "Drupal\instag\Form\InstagramPostForm",
 *       "delete" = "Drupal\instag\Form\InstagramPostDeleteForm",
 *     }
 *   },
 *   base_table = "instagram_post",
 *   admin_permission = "administer instagram entity",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/instagram_post/{instagram_post}",
 *     "edit-form" = "/instagram_post/{instagram_post}/edit",
 *     "delete-form" = "/instagram_post/{instagram_post}/delete",
 *     "collection" = "/instagram_post/list"
 *   },
 *   field_ui_base_route = "instag.settings"
 * )
 */
class InstagramPost extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->title->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * @inheritdoc
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the entity.'))
      ->setReadOnly(TRUE);

    // Instagram ID, unique for each post.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The Instagram ID of the entity.'))
      ->setReadOnly(TRUE);

    // Shortcode, unique for each post.
    $fields['shortcode'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Shortcode'))
      ->setDescription(t('The shortcode of the entity.'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // User, the user this post was created by.
    $fields['user'] = BaseFieldDefinition::create('string')
      ->setLabel(t('User'))
      ->setDescription(t('The user this post was created by.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Title field, computed based on the caption.
    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the post.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Caption, the text of the post.
    $fields['caption'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Caption'))
      ->setDescription(t('The caption of the post.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string_textarea',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Type, the type of the post. Can be either GraphImage or GraphSidecar.
    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('The type of the entity.'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Date, the date the post was published.
    $fields['date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Date'))
      ->setDescription('The date the post was published.')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Likes, number of likes.
    $fields['likes'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Likes'))
      ->setDescription(t('Number of likes.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number',
        'weight' => 7,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // View count, number of views a video has.
    $fields['view_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Video view count'))
      ->setDescription(t('Number of views a video has.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number',
        'weight' => 8,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Created, the timestamp for when the entity was created.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    // Changed, the timestamp for when the entity was changed.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * Load entity by UUID.
   *
   * @param int $uuid
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function loadByUUID(int $uuid) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage('instagram_post');
    $query = $storage->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('uuid', $uuid);
    $ids = $query->execute();
    if (empty($ids)) {
      return NULL;
    }
    return $storage->load(reset($ids));
  }
}
