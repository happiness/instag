<?php

declare(strict_types=1);

namespace Drupal\instag;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\instag\Entity\InstagramPost;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Instagram\Exception\InstagramAuthException;
use Instagram\Exception\InstagramException;
use Instagram\Model\Media;
use Instagram\Model\MediaDetailed;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Instagram\Api;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * A service for importing Instagram content.
 */
class InstagramUserFeedImporter implements InstagramImporterInterface {

  protected ImmutableConfig $config;
  protected LoggerInterface $logger;
  protected FileRepositoryInterface $fileRepository;
  protected FileSystemInterface $fileSystem;
  protected Client $client;
  protected Api $api;
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * InstagramImporter constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param LoggerInterface $logger
   *   The logger.
   * @param FileRepositoryInterface $fileRepository
   *   The file repository.
   * @param FileSystemInterface $fileSystem
   *   The file system.
   * @param Client $client
   *   The HTTP client.
   *
   * @throws \Exception
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, FileRepositoryInterface $fileRepository, FileSystemInterface $fileSystem, Client $client, EntityTypeManagerInterface $entityTypeManager) {
    $this->config = $config_factory->get('instag.settings');
    $this->logger = $logger;
    $this->fileRepository = $fileRepository;
    $this->fileSystem = $fileSystem;
    $this->client = $client;
    $this->entityTypeManager = $entityTypeManager;
    $this->initApi();
  }

  /**
   * Make sure all settings are set.
   *
   * @return bool
   * @throws \Exception
   */
  protected function checkSettings(): bool {
    $values = ['username', 'password', 'cache_dir', 'cache_lifetime'];
    foreach ($values as $value) {
      $val = $this->config->get($value);
      if (empty($val)) {
        throw new \Exception(sprintf('Instagram configuration key "%s" is not set.', $value));
      }
    }
    return TRUE;
  }

  /**
   * Init Instagram API.
   *
   * @return void
   * @throws \Exception
   */
  protected function initApi(): void {
    // Check settings.
    $this->checkSettings();

    // Init Instagram API.
    $cache_dir = PublicStream::basePath() . '/' . $this->config->get('cache_dir');
    $cache_lifetime = (int) $this->config->get('cache_lifetime');
    $cache = new FilesystemAdapter('instag', $cache_lifetime, $cache_dir);
    $this->api = new Api($cache, $this->client);
  }

  /**
   * Login to Instagram.
   *
   * @return void
   * @throws GuzzleException
   * @throws InvalidArgumentException
   */
  protected function login(): void {
    try {
      $this->api->login($this->config->get('username'), $this->config->get('password'));
    }
    catch (InstagramAuthException $e) {
      $this->logger->error('Failed to login to Instagram. Message was: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function importProfile(string $user): int {
    $posts = $this->getPosts($user, $user);
    return $this->import($posts);
  }

  /**
   * {@inheritdoc}
   */
  public function importTag(string $tag, int $max = 25): int {
    $posts = $this->getPostsByTag($tag, $max);
    return $this->import($posts, NULL);
  }

  /**
   * Import posts.
   *
   * @param array $posts
   *
   * @return int
   *   Number of imported posts.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \Instagram\Exception\InstagramAuthException
   * @throws \Instagram\Exception\InstagramFetchException
   */
  protected function import(array $posts, string|null $user): int {
    $count = 0;

    /** @var Media $post */
    foreach ($posts as $post) {
      // Load existing post.
      $entity = InstagramPost::loadByUUID($post->getId());

      // Convert date to storage format.
      $date = $post->getDate();
      $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $date_string = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

      // Timestamp for created and changed.
      $now = time();

      // Create Instagram post.
      if (is_null($entity)) {
        $entity = InstagramPost::create([
          'uuid' => $post->getId(),
          'shortcode' => $post->getShortCode(),
          'user' => $user ?? $post->getOwnerId(),
          'title' => $this->getTitle($post),
          'caption' => $this->getCaption($post),
          'type' => $post->getTypeName(),
          'date' => $date_string,
          'likes' => $post->getLikes(),
          'view_count' => $post->getVideoViewCount(),
          'created' => $now,
          'changed' => $now,
        ]);

        // Create media entities and add reference from post.
        $media_entities = $this->createMediaEntities($post);
        $entity->get('field_instagram_media')->setValue($media_entities);

        // Create tags.
        $tags = $this->createTags($post);
        $entity->get('field_instagram_tags')->setValue($tags);
      }
      else {
        // Update fields.
        $entity->get('likes')->setValue($post->getLikes());
        $entity->get('view_count')->setValue($post->getVideoViewCount());
        $entity->get('changed')->setValue($now);
      }

      $entity->save();
      $count++;
    }

    return $count;
  }

  /**
   * Get posts from Instagram.
   *
   * @param string $user
   *   The username.
   * @return array
   *
   * @throws GuzzleException
   * @throws InvalidArgumentException
   */
  protected function getPosts(string $user): array {
    $posts = [];
    try {
      $this->login();
      $profile = $this->api->getProfile($user);
      sleep(1);
      $posts = array_merge([], $profile->getMedias());
      do {
        $profile = $this->api->getMoreMedias($profile);
        $posts = array_merge($posts, $profile->getMedias());
        sleep(1);
      } while ($profile->hasMoreMedias());
    }
    catch (InstagramException $e) {
      $this->logger->error('Failed to fetch Instagram posts. Message was: ' . $e->getMessage());
    }

    return $posts;
  }

  /**
   * Get posts by hashtag.
   *
   * @param string $tag
   *   The hashtag.
   * @param int $max
   *   Maximum number of posts to fetch.
   * @return array
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  protected function getPostsByTag(string $tag, int $max = 25): array {
    $posts = [];
    $count = 0;
    try {
      $this->login();
      $hashtag = $this->api->getHashtag($tag);
      sleep(1);
      $posts = array_merge([], $hashtag->getMedias());

      while ($cursor = $hashtag->getEndCursor()) {
        $medias = $this->api->getMoreHashtagMedias($tag, $cursor);
        $posts = array_merge($posts, $medias);
        $count++;
        if ($count >= $max) break;
        sleep(1);
      }
    }
    catch (InstagramException $e) {
      $this->logger->error('Failed to fetch Instagram posts. Message was: ' . $e->getMessage());
    }

    return $posts;
  }

  /**
   * Assemble a title that is suitable for an entity title.
   *
   * @param Media $post
   * @return string
   */
  protected function getTitle(Media $post): string {
    $title = explode("! ", $post->getCaption() ?? '')[0];
    $title = explode(". ", $title)[0];
    $title = explode(", ", $title)[0];
    $title = explode(" ", $title);
    $title = implode(" ", array_splice($title, 0, 10));

    // If post has no title use shortcode.
    if (empty($title)) {
      $title = $post->getShortCode();
    }

    return $title;
  }

  /**
   * Get caption without hashtags.
   *
   * @param Media $post
   * @return string
   */
  protected function getCaption(Media $post): string {
    return preg_replace('/#([^ \\r\ \	]+)/', '', $post->getCaption() ?? '');
  }

  /**
   * Create media entities for post.
   *
   * @param \Instagram\Model\Media $post
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \Instagram\Exception\InstagramAuthException
   * @throws \Instagram\Exception\InstagramFetchException
   */
  protected function createMediaEntities(Media $post): array {
    // Prepare target directory.
    // @todo Fetch the destination directory from the field instance settings.
    $destination = 'public://instagram/' . date('Y-m');
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Get all media items for post.
    $items = $this->getMediaItems($post);
    $media_entities = [];
    foreach ($items as $item) {
      // Assemble filename.
      $filename = substr(str_replace('/', '-', parse_url($item['url'], PHP_URL_PATH)), 1);

      // Get image from remote server and create the file.
      try {
        $response = $this->client->get($item['url']);
        $file_data = $response->getBody()->getContents();
        $file = $this->fileRepository->writeData($file_data, $destination . '/' . $filename);
      }
      catch (GuzzleException $e) {
        $this->logger->error('Failed to download media @uri. Message was: @message', ['@uri' => $item['url'], '@message' => $e->getMessage()]);
        continue;
      }

      // Create the media entity.
      $media = \Drupal\media\Entity\Media::create([
        'bundle' => $item['bundle'],
        'status' => 1
      ]);
      $media->get($item['field'])->setValue(['target_id' => $file->id()]);
      $media->save();
      $media_entities[] = $media;
    }

    return $media_entities;
  }

  /**
   * Get all media items.
   *
   * @param \Instagram\Model\Media $post
   *
   * @return array
   * @throws \Instagram\Exception\InstagramAuthException
   * @throws \Instagram\Exception\InstagramFetchException
   */
  protected function getMediaItems(Media $post): array {
    $items = [];
    if ($post->getTypeName() == 'GraphSidecar') {
      $sidecar = new Media();
      $sidecar->setLink(sprintf('https://www.instagram.com/p/%s/', $post->getShortCode()));
      $detailed = $this->api->getMediaDetailed($sidecar);
      $a = $detailed->getSideCarItems();
      foreach ($a as $item) {
        $items[] = $this->getMediaItem($item);
      }
    }
    else {
      $items[] = $this->getMediaItem($post);
    }

    return $items;
  }

  /**
   * Get a single media item.
   *
   * @param \Instagram\Model\Media|\Instagram\Model\MediaDetailed $media
   *
   * @return array
   */
  protected function getMediaItem(Media|MediaDetailed $media): array {
    if ($media->getVideoUrl()) {
      $item = [
        'bundle' => 'instagram_video',
        'field' => 'field_media_video_file',
        'url' => $media->getVideoUrl(),
      ];
    }
    else {
      $item = [
        'bundle' => 'instagram_image',
        'field' => 'field_media_image',
      ];

      if ($media instanceof MediaDetailed) {
        $resources = $media->getDisplayResources();
        $item['url'] = $resources[0]->url;
      }
      else {
        $item['url'] = $media->getDisplaySrc();
      }
    }

    return $item;
  }

  /**
   * Create tags for post.
   *
   * @param \Instagram\Model\Media $post
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTags(Media $post): array {
    $tags = [];
    $hashtags = $post->getHashtags();
    foreach ($hashtags as $tag) {
      $entity = $this->lookupTag($tag);
      if (is_null($entity)) {
        $entity = Term::create([
          'vid' => 'instagram_tags',
          'name' => $tag,
        ]);
        $entity->save();
      }
      $tags[] = $entity;
    }

    return $tags;
  }

  /**
   * Lookup term based on name.
   *
   * @param string $tag
   *
   * @return \Drupal\Core\Entity\EntityInterface|NULL
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function lookupTag(string $tag): EntityInterface|NULL {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $storage->getQuery();
    $tids = $query->condition('vid', 'instagram_tags')
      ->condition('name', $tag)
      ->accessCheck(FALSE)
      ->execute();
    if (!empty($tids)) {
      return $storage->load(reset($tids));
    }

    return NULL;
  }

}
