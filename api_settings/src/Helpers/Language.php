<?php

namespace Drupal\api_settings\Helpers;

use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait Language
{
  public static function getLanguages() {
    // Get all the languages
    $languages = \Drupal::languageManager()->getLanguages();
    $languagesArray = [];

    // Instantiate request
    $request = \Drupal::request();

    if(count($languages) > 0) {
      // Get the settings for each language
      foreach ($languages as $language) {
        $id = $language->getId();
        $name = $language->getName();
        $default = $language->isDefault();
        $direction = $language->getDirection();
        $url = Language::getLanguageDomain($request, $id);

        $languagesArray[] = [
          'id' => $id,
          'name' => $name,
          'default' => $default,
          'direction' => $direction,
          'url' => $url,
        ];
      }
    }
    return $languagesArray;
  }

  private static function getLanguageDomain(Request $request = null, $languageId) {
    if($request) {
      // Instantiate configuration drupal
      $config = \Drupal::configFactory();
      // Get language negotiation config. This will give a array with prefixes or domains.
      $languageNegotiation = $config->get('language.negotiation')->get('url');

      // Check if the website is configurated with prefixes or domains
      switch ($languageNegotiation['source']) {
        // If the configuration is path_prefix go further
        case LanguageNegotiationUrl::CONFIG_PATH_PREFIX:

          // Get prefix for given language
          $prefix = $languageNegotiation['prefixes'][$languageId];

          // Check if the prefix returns null if so the languageId is probally wrong.
          if(empty($prefix)) {
            return new NotFoundHttpException('Language id is probally wrong.');
          }

          $url = $request->getHost() . '/' . $prefix;

          // Return the url
          return $url;
          break;

        // If the configuration is path_domain go further
        case LanguageNegotiationUrl::CONFIG_DOMAIN:

          // Get domain for given language
          $domain = $languageNegotiation['domain'][$languageId];

          // Check if the domain returns null if so the languageId is probally wrong.
          if(empty($domain)) {
            return new NotFoundHttpException('Language id is probally wrong.');
          }

          // return the url
          return $domain;
          break;
      }
    }
    return NULL;
  }
}