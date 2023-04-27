<?php

declare(strict_types=1);

namespace Drupal\instag\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Drush commands.
 */
class InstagramCommands extends DrushCommands {

  /**
   * Import instagram posts.
   *
   * @param string $user
   *   The Instagram account name of the posts to import.
   * @command instag:import
   * @usage drush instag:import thisisbillgates
   */
  public function import(string $user): void {
    /** @var \Drupal\instag\InstagramUserFeedImporter $importer */
    $importer = \Drupal::service('instag.importer');
    $count = $importer->importProfile($user);

    $output = new ConsoleOutput();
    $output->writeln(sprintf('Imported %d Instragram posts.', $count));
  }

  /**
   * Import instagram posts.
   *
   * @param string $tag
   *   The hashtag to import posts from.
   * @command instag:import-tag
   * @usage drush instag:import-tag drupal10party
   */
  public function importTag(string $tag): void {
    /** @var \Drupal\instag\InstagramUserFeedImporter $importer */
    $importer = \Drupal::service('instag.importer');
    $count = $importer->importTag($tag);

    $output = new ConsoleOutput();
    $output->writeln(sprintf('Imported %d Instragram posts.', $count));
  }

}
