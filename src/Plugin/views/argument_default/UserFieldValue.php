<?php

namespace Drupal\user_field_value\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Default argument plugin to use the Current user field value match.
 *
 * @ingroup views_argument_default_plugins
 *
 * @ViewsArgumentDefault(
 *   id = "userfieldvalue",
 *   title = @Translation("User Field Value")
 * )
 */
class UserFieldValue extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The current user.
   *
   * @var user
   */
  public $user;

  /**
   * The entity type manager.
   *
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->user = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['user_field'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $list = $this->entityFieldManager->getFieldDefinitions('user', 'user');

    $fields_to_show = [];
    $fields_not_to_use = [
      'uuid',
      'langcode',
      'preferred_langcode',
      'preferred_admin_langcode',
      'name',
      'pass',
      'mail',
      'timezone',
      'status',
      'created',
      'changed',
      'access',
      'login',
      'init',
      'roles',
      'path',
      'default_langcode',
    ];
    foreach ($list as $key => $val) {
      if (!empty($val)) {
        if (!in_array($key, $fields_not_to_use)) {
          $fields_to_show[$key] = $key;
        }
      }
    }

    $form['user_field'] = [
      '#type' => 'select',
      '#title' => $this->t('User Field Mach'),
      '#options' => $fields_to_show,
      '#default_value' => $this->options['user_field'],
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $field_key = $this->options['user_field'];
    $uid = $this->user->id();
    $loaded_user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!empty($loaded_user->get($field_key))) {
      $main_property = $loaded_user->get($field_key)->getItemDefinition()->getMainPropertyName() ?? 'value';
      $field_values = $loaded_user->get($field_key)->getIterator();
      $values = [];
      foreach ($field_values as $key => $field_value) {
        $values[] = $field_value->{$main_property};
      }
    }
    if (!empty($values)) {
      if ($this->argument->options['break_phrase']) {
        return implode(',', $values);
      }
      else {
        return $values[0];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user'];
  }

}
