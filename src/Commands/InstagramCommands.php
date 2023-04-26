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
    /** @var \Drupal\instag\InstagramImporter $importer */
    $importer = \Drupal::service('instag.importer');
    $count = $importer->import($user);

    $output = new ConsoleOutput();
    $output->writeln(sprintf('Imported %d Instragram posts.', $count));
  }

}
