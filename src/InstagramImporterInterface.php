<?php

declare(strict_types=1);

namespace Drupal\instag;

use Drupal\instag\Entity\InstagramPost;
use Instagram\Model\Media;

interface InstagramImporterInterface {

  /**
   * Get posts from Instagram by username.
   *
   * @param string $user
   *   The username.
   * @return array
   */
  public function getPosts(string $user): array;

  /**
   * Get posts by hashtag.
   *
   * @param string $tag
   *   The hashtag.
   * @param int $max
   *   Maximum number of posts to fetch.
   * @return array
   */
  public function getPostsByTag(string $tag, int $max = 25): array;

  /**
   * Import Instagram posts to instagram_post entity.
   *
   * @param string $user
   *   The instagram user.
   */
  public function importProfile(string $user): void;

  /**
   * Import Instagram posts with specific hashtag to instagram_post entity.
   *
   * @param string $tag
   *  The hashtag.
   * @param int $max
   *   The maximum number of posts to import.
   */
  public function importTag(string $tag, int $max = 25): void;

  /**
   * Import post.
   *
   * @param Media $post
   * @param string|null $user
   * @return InstagramPost
   */
  public function import(Media $post, ?string $user = NULL): InstagramPost;

}
