<?php

declare(strict_types=1);

namespace Drupal\instag\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Drupal\instag\InstagramUserFeedImporter;
use Drush\Attributes as CLI;

/**
 * Drush commands for Instag module.
 */
class InstagramCommands extends DrushCommands {

  protected InstagramUserFeedImporter $importer;
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a InstagramCommands object.
   */
  public function __construct(InstagramUserFeedImporter $importer, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct();
    $this->importer = $importer;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('instag.importer'),
      $container->get('entity_type.manager')
    );
  }

  #[CLI\Command(name: 'instag:import-user')]
  #[CLI\Argument(name: 'user', description: 'The username of the Instagram user feed to import.')]
  #[CLI\Usage(name: 'drush instag:import-user thisisbillgates', description: 'Import all posts by user thisisbillgates.')]
  public function importUser(string $user): void {
    $output = new ConsoleOutput();
    $this->importer->setOutput($output);
    $this->importer->importProfile($user);
  }

  #[CLI\Command(name: 'instag:import-tag')]
  #[CLI\Argument(name: 'tag', description: 'The hashtag to import posts from.')]
  #[CLI\Argument(name: 'max', description: 'The maximum number of posts to import.')]
  #[CLI\Usage(name: 'drush instag:import-tag drupal10party 50', description: 'Import 50 posts with the tag drupal10party.')]
  public function importTag(string $tag, int $max = 25): void {
    $output = new ConsoleOutput();
    $this->importer->setOutput($output);
    $this->importer->importTag($tag);
  }

  #[CLI\Command(name: 'instag:delete-all')]
  #[CLI\Usage(name: 'drush instag:delete-all', description: 'Delete all posts.')]
  public function deleteAll(): void {
    if ($this->io()->confirm('Are you sure you want to delete all posts?')) {
      $posts = $this->entityTypeManager->getStorage('instagram_post')->loadMultiple();
      $this->entityTypeManager->getStorage('instagram_post')->delete($posts);
    }
  }

}
