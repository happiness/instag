<?php

declare(strict_types=1);

namespace Drupal\instag\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Output\ConsoleOutput;
use \Consolidation\OutputFormatters\Formatters\JsonFormatter;

/**
 * Drush commands.
 */
class InstagramCommands extends DrushCommands {

  /**
   * Import instagram posts.
   *
   * @command instag:import
   * @usage drush instag:import
   */
  public function import(): void {

    $output = new ConsoleOutput();
    $output->writeln('Imported Instragram posts.');
  }

}
