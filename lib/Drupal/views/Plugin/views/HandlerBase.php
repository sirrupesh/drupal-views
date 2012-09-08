<?php

/**
 * @file
 * Definition of Drupal\views\Plugin\views\HandlerBase.
 */

namespace Drupal\views\Plugin\views;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\views\Plugin\views\PluginBase;
use Drupal\views\View;

abstract class HandlerBase extends PluginBase {

  /**
   * Where the $query object will reside:
   *
   * @var Drupal\views\Plugin\views\query\QueryPluginBase
   */
  public $query = NULL;

  /**
   * The table this handler is attached to.
   *
   * @var string
   */
  public $table;

  /**
   * The alias of the table of this handler which is used in the query.
   *
   * @var string
   */
  public $table_alias;

  /**
   * When a table has been moved this property is set.
   *
   * @var string
   */
  public $actual_table;

  /**
   * The actual field in the database table, maybe different
   * on other kind of query plugins/special handlers.
   *
   * @var string
   */
  public $real_field;

  /**
   * With field you can override the real_field if the real field is not set.
   *
   * @var string
   */
  public $field;

  /**
   * When a field has been moved this property is set.
   *
   * @var string
   */
  public $actual_field;

  /**
   * The relationship used for this field.
   *
   * @var string
   */
  public $relationship = NULL;

  /**
   * Constructs a Handler object.
   */
  public function __construct(array $configuration, $plugin_id, DiscoveryInterface $discovery) {
    parent::__construct($configuration, $plugin_id, $discovery);
    $this->is_handler = TRUE;
  }

  /**
   * Init the handler with necessary data.
   *
   * @param Drupal\views\View $view
   *   The $view object this handler is attached to.
   * @param array $options
   *   The item from the database; the actual contents of this will vary
   *   based upon the type of handler.
   */
  public function init(&$view, &$options) {
    $this->setOptionDefaults($this->options, $this->defineOptions());
    $this->view = &$view;
    $display_id = $this->view->current_display;
    // Check to see if this handler type is defaulted. Note that
    // we have to do a lookup because the type is singular but the
    // option is stored as the plural.

    // If the 'moved to' keyword moved our handler, let's fix that now.
    if (isset($this->actual_table)) {
      $options['table'] = $this->actual_table;
    }

    if (isset($this->actual_field)) {
      $options['field'] = $this->actual_field;
    }

    $types = View::viewsHandlerTypes();
    $plural = $this->plugin_type;
    if (isset($types[$this->plugin_type]['plural'])) {
      $plural = $types[$this->plugin_type]['plural'];
    }
    if ($this->view->display_handler->isDefaulted($plural)) {
      $display_id = 'default';
    }

    $this->unpackOptions($this->options, $options);

    // This exist on most handlers, but not all. So they are still optional.
    if (isset($options['table'])) {
      $this->table = $options['table'];
    }

    if (isset($this->definition['real field'])) {
      $this->real_field = $this->definition['real field'];
    }

    if (isset($this->definition['field'])) {
      $this->real_field = $this->definition['field'];
    }

    if (isset($options['field'])) {
      $this->field = $options['field'];
      if (!isset($this->real_field)) {
        $this->real_field = $options['field'];
      }
    }

    $this->query = &$view->query;
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['id'] = array('default' => '');
    $options['table'] = array('default' => '');
    $options['field'] = array('default' => '');
    $options['relationship'] = array('default' => 'none');
    $options['group_type'] = array('default' => 'group');
    $options['admin_label'] = array('default' => '', 'translatable' => TRUE);

    return $options;
  }

  /**
   * Return a string representing this handler's name in the UI.
   */
  public function adminLabel($short = FALSE) {
    if (!empty($this->options['admin_label'])) {
      $title = check_plain($this->options['admin_label']);
      return $title;
    }
    $title = ($short && isset($this->definition['title short'])) ? $this->definition['title short'] : $this->definition['title'];
    return t('!group: !title', array('!group' => $this->definition['group'], '!title' => $title));
  }

  /**
   * Shortcut to get a handler's raw field value.
   *
   * This should be overridden for handlers with formulae or other
   * non-standard fields. Because this takes an argument, fields
   * overriding this can just call return parent::getField($formula)
   */
  public function getField($field = NULL) {
    if (!isset($field)) {
      if (!empty($this->formula)) {
        $field = $this->get_formula();
      }
      else {
        $field = $this->table_alias . '.' . $this->real_field;
      }
    }

    // If grouping, check to see if the aggregation method needs to modify the field.
    if ($this->view->display_handler->useGroupBy()) {
      $this->view->initQuery();
      if ($this->query) {
        $info = $this->query->get_aggregation_info();
        if (!empty($info[$this->options['group_type']]['method'])) {
          $method = $info[$this->options['group_type']]['method'];
          if (method_exists($this->query, $method)) {
            return $this->query->$method($this->options['group_type'], $field);
          }
        }
      }
    }

    return $field;
  }

  /**
   * Sanitize the value for output.
   *
   * @param $value
   *   The value being rendered.
   * @param $type
   *   The type of sanitization needed. If not provided, check_plain() is used.
   *
   * @return string
   *   Returns the safe value.
   */
  protected function sanitizeValue($value, $type = NULL) {
    switch ($type) {
      case 'xss':
        $value = filter_xss($value);
        break;
      case 'xss_admin':
        $value = filter_xss_admin($value);
        break;
      case 'url':
        $value = check_url($value);
        break;
      default:
        $value = check_plain($value);
        break;
    }
    return $value;
  }

  /**
   * Transform a string by a certain method.
   *
   * @param $string
   *    The input you want to transform.
   * @param $option
   *    How do you want to transform it, possible values:
   *      - upper: Uppercase the string.
   *      - lower: lowercase the string.
   *      - ucfirst: Make the first char uppercase.
   *      - ucwords: Make each word in the string uppercase.
   *
   * @return string
   *    The transformed string.
   */
  protected function caseTransform($string, $option) {
    global $multibyte;

    switch ($option) {
      default:
        return $string;
      case 'upper':
        return drupal_strtoupper($string);
      case 'lower':
        return drupal_strtolower($string);
      case 'ucfirst':
        return drupal_strtoupper(drupal_substr($string, 0, 1)) . drupal_substr($string, 1);
      case 'ucwords':
        if ($multibyte == UNICODE_MULTIBYTE) {
          return mb_convert_case($string, MB_CASE_TITLE);
        }
        else {
          return ucwords($string);
        }
    }
  }

  /**
   * Validate the options form.
   */
  public function validateOptionsForm(&$form, &$form_state) { }

  /**
   * Build the options form.
   */
  public function buildOptionsForm(&$form, &$form_state) {
    // Some form elements belong in a fieldset for presentation, but can't
    // be moved into one because of the form_state['values'] hierarchy. Those
    // elements can add a #fieldset => 'fieldset_name' property, and they'll
    // be moved to their fieldset during pre_render.
    $form['#pre_render'][] = 'views_ui_pre_render_add_fieldset_markup';

    $form['admin_label'] = array(
      '#type' => 'textfield',
      '#title' => t('Administrative title'),
      '#description' => t('This title will be displayed on the views edit page instead of the default one. This might be useful if you have the same item twice.'),
      '#default_value' => $this->options['admin_label'],
      '#fieldset' => 'more',
    );

    // This form is long and messy enough that the "Administrative title" option
    // belongs in a "more options" fieldset at the bottom of the form.
    $form['more'] = array(
      '#type' => 'fieldset',
      '#title' => t('More'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 150,
    );
    // Allow to alter the default values brought into the form.
    drupal_alter('views_handler_options', $this->options, $view);
  }

  /**
   * Perform any necessary changes to the form values prior to storage.
   * There is no need for this function to actually store the data.
   */
  public function submitOptionsForm(&$form, &$form_state) { }

  /**
   * Provides the handler some groupby.
   */
  public function usesGroupBy() {
    return TRUE;
  }
  /**
   * Provide a form for aggregation settings.
   */
  public function buildGroupByForm(&$form, &$form_state) {
    $view = &$form_state['view'];
    $display_id = $form_state['display_id'];
    $types = View::viewsHandlerTypes();
    $type = $form_state['type'];
    $id = $form_state['id'];

    $form['#title'] = check_plain($view->display[$display_id]->display_title) . ': ';
    $form['#title'] .= t('Configure aggregation settings for @type %item', array('@type' => $types[$type]['lstitle'], '%item' => $this->adminLabel()));

    $form['#section'] = $display_id . '-' . $type . '-' . $id;

    $view->initQuery();
    $info = $view->query->get_aggregation_info();
    foreach ($info as $id => $aggregate) {
      $group_types[$id] = $aggregate['title'];
    }

    $form['group_type'] = array(
      '#type' => 'select',
      '#title' => t('Aggregation type'),
      '#default_value' => $this->options['group_type'],
      '#description' => t('Select the aggregation function to use on this field.'),
      '#options' => $group_types,
    );
  }

  /**
   * Perform any necessary changes to the form values prior to storage.
   * There is no need for this function to actually store the data.
   */
  public function submitGroupByForm(&$form, &$form_state) {
    $item =& $form_state['handler']->options;

    $item['group_type'] = $form_state['values']['options']['group_type'];
  }

  /**
   * If a handler has 'extra options' it will get a little settings widget and
   * another form called extra_options.
   */
  public function hasExtraOptions() { return FALSE; }

  /**
   * Provide defaults for the handler.
   */
  public function defineExtraOptions(&$option) { }

  /**
   * Provide a form for setting options.
   */
  public function buildExtraOptionsForm(&$form, &$form_state) { }

  /**
   * Validate the options form.
   */
  public function validateExtraOptionsForm($form, &$form_state) { }

  /**
   * Perform any necessary changes to the form values prior to storage.
   * There is no need for this function to actually store the data.
   */
  public function submitExtraOptionsForm($form, &$form_state) { }

  /**
   * Determine if a handler can be exposed.
   */
  public function canExpose() { return FALSE; }

  /**
   * Set new exposed option defaults when exposed setting is flipped
   * on.
   */
  public function defaultExposeOptions() { }

  /**
   * Get information about the exposed form for the form renderer.
   */
  public function exposedInfo() { }

  /**
   * Render our chunk of the exposed handler form when selecting
   */
  public function buildExposedForm(&$form, &$form_state) { }

  /**
   * Validate the exposed handler form
   */
  public function validateExposed(&$form, &$form_state) { }

  /**
   * Submit the exposed handler form
   */
  public function submitExposed(&$form, &$form_state) { }

  /**
   * Form for exposed handler options.
   */
  public function buildExposeForm(&$form, &$form_state) { }

  /**
   * Validate the options form.
   */
  public function validateExposeForm($form, &$form_state) { }

  /**
   * Perform any necessary changes to the form exposes prior to storage.
   * There is no need for this function to actually store the data.
   */
  public function submitExposeForm($form, &$form_state) { }

  /**
   * Shortcut to display the expose/hide button.
   */
  public function showExposeButton(&$form, &$form_state) { }

  /**
   * Shortcut to display the exposed options form.
   */
  public function showExposeForm(&$form, &$form_state) {
    if (empty($this->options['exposed'])) {
      return;
    }

    $this->buildExposeForm($form, $form_state);

    // When we click the expose button, we add new gadgets to the form but they
    // have no data in $_POST so their defaults get wiped out. This prevents
    // these defaults from getting wiped out. This setting will only be TRUE
    // during a 2nd pass rerender.
    if (!empty($form_state['force_expose_options'])) {
      foreach (element_children($form['expose']) as $id) {
        if (isset($form['expose'][$id]['#default_value']) && !isset($form['expose'][$id]['#value'])) {
          $form['expose'][$id]['#value'] = $form['expose'][$id]['#default_value'];
        }
      }
    }
  }

  /**
   * Check whether current user has access to this handler.
   *
   * @return boolean
   */
  public function access() {
    if (isset($this->definition['access callback']) && function_exists($this->definition['access callback'])) {
      if (isset($this->definition['access arguments']) && is_array($this->definition['access arguments'])) {
        return call_user_func_array($this->definition['access callback'], $this->definition['access arguments']);
      }
      return $this->definition['access callback']();
    }

    return TRUE;
  }

  /**
   * Run before the view is built.
   *
   * This gives all the handlers some time to set up before any handler has
   * been fully run.
   */
  public function preQuery() { }

  /**
   * Run after the view is executed, before the result is cached.
   *
   * This gives all the handlers some time to modify values. This is primarily
   * used so that handlers that pull up secondary data can put it in the
   * $values so that the raw data can be utilized externally.
   */
  public function postExecute(&$values) { }

  /**
   * Provides a unique placeholders for handlers.
   */
  protected function placeholder() {
    return $this->query->placeholder($this->options['table'] . '_' . $this->options['field']);
  }

  /**
   * Called just prior to query(), this lets a handler set up any relationship
   * it needs.
   */
  public function setRelationship() {
    // Ensure this gets set to something.
    $this->relationship = NULL;

    // Don't process non-existant relationships.
    if (empty($this->options['relationship']) || $this->options['relationship'] == 'none') {
      return;
    }

    $relationship = $this->options['relationship'];

    // Ignore missing/broken relationships.
    if (empty($this->view->relationship[$relationship])) {
      return;
    }

    // Check to see if the relationship has already processed. If not, then we
    // cannot process it.
    if (empty($this->view->relationship[$relationship]->alias)) {
      return;
    }

    // Finally!
    $this->relationship = $this->view->relationship[$relationship]->alias;
  }

  /**
   * Ensure the main table for this handler is in the query. This is used
   * a lot.
   */
  public function ensureMyTable() {
    if (!isset($this->table_alias)) {
      $this->table_alias = $this->query->ensure_table($this->table, $this->relationship);
    }
    return $this->table_alias;
  }

  /**
   * Provide text for the administrative summary
   */
  public function adminSummary() { }

  /**
   * Determine if the argument needs a style plugin.
   *
   * @return TRUE/FALSE
   */
  public function needsStylePlugin() { return FALSE; }

  /**
   * Determine if this item is 'exposed', meaning it provides form elements
   * to let users modify the view.
   *
   * @return TRUE/FALSE
   */
  public function isExposed() {
    return !empty($this->options['exposed']);
  }

  /**
   * Returns TRUE if the exposed filter works like a grouped filter.
   */
  public function isAGroup() { return FALSE; }

  /**
   * Define if the exposed input has to be submitted multiple times.
   * This is TRUE when exposed filters grouped are using checkboxes as
   * widgets.
   */
  public function multipleExposedInput() { return FALSE; }

  /**
   * Take input from exposed handlers and assign to this handler, if necessary.
   */
  public function acceptExposedInput($input) { return TRUE; }

  /**
   * If set to remember exposed input in the session, store it there.
   */
  public function storeExposedInput($input, $status) { return TRUE; }

  /**
   * Get the join object that should be used for this handler.
   *
   * This method isn't used a great deal, but it's very handy for easily
   * getting the join if it is necessary to make some changes to it, such
   * as adding an 'extra'.
   */
  public function getJoin() {
    // get the join from this table that links back to the base table.
    // Determine the primary table to seek
    if (empty($this->query->relationships[$this->relationship])) {
      $base_table = $this->query->base_table;
    }
    else {
      $base_table = $this->query->relationships[$this->relationship]['base'];
    }

    $join = views_get_table_join($this->table, $base_table);
    if ($join) {
      return clone $join;
    }
  }

  /**
   * Validates the handler against the complete View.
   *
   * This is called when the complete View is being validated. For validating
   * the handler options form use validateOptionsForm().
   *
   * @see views_handler::validateOptionsForm()
   *
   * @return
   *   Empty array if the handler is valid; an array of error strings if it is not.
   */
  public function validate() { return array(); }

  /**
   * Determine if the handler is considered 'broken', meaning it's a
   * a placeholder used when a handler can't be found.
   */
  public function broken() { }

}
