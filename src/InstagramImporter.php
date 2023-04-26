<?php

declare(strict_types=1);

namespace Drupal\instag;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Instagram\Exception\InstagramAuthException;
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\file\FileRepositoryInterface $fileRepository
   *   The file repository.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   * @param \GuzzleHttp\Client $client
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
}
