<?php

namespace Drupal\api_view_serializer\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsFormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'referenced entities json formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "referenced_entities_json_formatter",
 *   label = @Translation("Json format"),
 *   description = @Translation("Display the referenced entities rendered by jsonEncode."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class ReferencedEntitiesJsonFormatter extends EntityReferenceRevisionsFormatterBase implements ContainerFactoryPluginInterface {
  use \Drupal\api_nodes\FieldTrait;
  use \Drupal\api_nodes\FileUrlsTrait;


  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepo;

  /**
   * Constructs a StringFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepo
   *   The entity display repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_factory, EntityDisplayRepositoryInterface $entityDisplayRepo) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->loggerFactory = $logger_factory;
    $this->entityDisplayRepo = $entityDisplayRepo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'view_mode' => 'default',
      'link' => FALSE,
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['view_mode'] = array(
      '#type' => 'select',
      '#options' => $this->entityDisplayRepo->getViewModeOptions($this->getFieldSetting('target_type')),
      '#title' => $this->t('View mode'),
      '#default_value' => $this->getSetting('view_mode'),
      '#required' => TRUE,
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $view_modes = $this->entityDisplayRepo->getViewModeOptions($this->getFieldSetting('target_type'));
    $view_mode = $this->getSetting('view_mode');
    $summary[] = $this->t('Rendered as @mode', array('@mode' => isset($view_modes[$view_mode]) ? $view_modes[$view_mode] : $view_mode));

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();

    // There is a way to cause a infinite loop in this code: This is why it
    // happens:
    // When you add a view to a reference view field and this view also has
    // a reference to all paragraphs it will loop forever.
    // SO DON'T DO THIS!
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $object = $this->getFields($entity);

      $elements[$delta] = array('#markup' => json_encode($object));
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for entity types that have a view
    // builder.
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return \Drupal::entityTypeManager()->getDefinition($target_type)->hasViewBuilderClass();
  }

}
