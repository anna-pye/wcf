<?php

declare(strict_types=1);

namespace Drupal\wcf_migrate\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Extracts a canonical YouTube watch URL from legacy iframe embed markup.
 */
#[MigrateProcess('wcf_d7_youtube_embed_url')]
class WcfD7YoutubeEmbedUrl extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): ?string {
    $embed = trim((string) $value);
    if ($embed === '') {
      return NULL;
    }
    if (preg_match('#(?:youtube\.com/embed/|youtube-nocookie\.com/embed/)([a-zA-Z0-9_-]+)#', $embed, $matches)) {
      return 'https://www.youtube.com/watch?v=' . $matches[1];
    }
    if (preg_match('#youtu\.be/([a-zA-Z0-9_-]+)#', $embed, $matches)) {
      return 'https://www.youtube.com/watch?v=' . $matches[1];
    }
    if (preg_match('#[?&]v=([a-zA-Z0-9_-]+)#', $embed, $matches)) {
      return 'https://www.youtube.com/watch?v=' . $matches[1];
    }
    return NULL;
  }

}
