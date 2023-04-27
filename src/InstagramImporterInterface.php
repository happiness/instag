<?php

declare(strict_types=1);

namespace Drupal\instag;

interface InstagramImporterInterface {

  /**
   * Import Instagram posts to instagram_post entity.
   *
   * @return int
   *   Number of imported posts.
   */
  public function import(): int;

}
