<?php

declare(strict_types=1);

namespace Drupal\instag;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\instag\Entity\InstagramPost;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Instagram\Exception\InstagramAuthException;
use Instagram\Exception\InstagramException;
use Instagram\Model\Media;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Instagram\Api;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * A service for importing Instagram content.
 */
class InstagramImporter {

  protected ImmutableConfig $config;
  protected LoggerInterface $logger;
  protected FileRepositoryInterface $fileRepository;
  protected FileSystemInterface $fileSystem;
  protected Client $client;
  protected Api $api;

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
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, FileRepositoryInterface $fileRepository, FileSystemInterface $fileSystem, Client $client) {
    $this->config = $config_factory->get('instag.settings');
    $this->logger = $logger;
    $this->fileRepository = $fileRepository;
    $this->fileSystem = $fileSystem;
    $this->client = $client;
    $this->initApi();
  }

  /**
   * Init Instagram API.
   *
   * @return void
   */
  protected function initApi(): void {
    // Init Instagram API.
    $cache_dir = PublicStream::basePath() . '/' . $this->config->get('cache_dir');
    $cache_lifetime = $this->config->get('cache_lifetime');
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
   * Import posts to instagram_post entity.
   *
   * @param string $user
   * @return int
   *   Number of imported posts.
   * @throws GuzzleException
   * @throws InvalidArgumentException
   * @throws EntityStorageException
   */
  public function import(string $user): int {
    $posts = $this->getPosts($user);
    $count = 0;
    /** @var Media $post */
    foreach ($posts as $post) {
      $date = $post->getDate();
      $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $date_string = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
      $now = time();
      $entity = InstagramPost::create([
        'uuid' => $post->getId(),
        'shortcode' => $post->getShortCode(),
        'title' => $this->getTitle($post),
        'caption' => $this->getCaption($post),
        'type' => $post->getTypeName(),
        'date' => $date_string,
        'likes' => $post->getLikes(),
        'view_count' => $post->getVideoViewCount(),
        'created' => $now,
        'changed' => $now,
      ]);

      // Video.
      //$post->getVideoUrl();

      // Image.
      //$post->getDisplaySrc();

      // Tags.
      //implode(", ", $post->getHashtags());

      $entity->save();
      $count++;
    }

    return $count;
  }

  /**
   * Get posts from Instagram.
   *
   * @param string $user
   * @return array
   * @throws GuzzleException
   * @throws InvalidArgumentException
   */
  protected function getPosts(string $user): array {
    $posts = [];
    try {
      $this->login();
      $profile = $this->api->getProfile($user);
      $posts = $profile->getMedias();
      do {
        $profile = $this->api->getMoreMedias($profile);
        $posts += $profile->getMedias();
        sleep(1);
      } while ($profile->hasMoreMedias());
    }
    catch (InstagramException $e) {
      $this->logger->error('Failed to import Instagram posts. Message was: ' . $e->getMessage());
    }

    return $posts;
  }

  /**
   * Assemble a title that is suitable for a entity title.
   *
   * @param Media $post
   * @return string
   */
  protected function getTitle(Media $post): string {
    $title = explode("! ", $post->getCaption())[0];
    $title = explode(". ", $title)[0];
    $title = explode(", ", $title)[0];
    $title = explode(" ", $title);
    return implode(" ", array_splice($title, 0, 10));
  }

  /**
   * Get caption without hashtags.
   *
   * @param Media $post
   * @return string
   */
  protected function getCaption(Media $post): string {
    return preg_replace('/#([^ \\r\ \	]+)/', '', $post->getCaption());
  }

}
