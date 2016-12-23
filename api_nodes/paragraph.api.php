<?php

/**
 * This hook will be triggered everytime a paragraph is loaded though the
 * url endpoint
 *
 * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
 */
function hook_alter_paragraph_load(&$paragraph) {
  // Do something with the paragraph here
}