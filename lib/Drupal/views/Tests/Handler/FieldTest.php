<?php

/**
 * @file
 * Definition of Drupal\views\Tests\Handler\FieldTest.
 */

namespace Drupal\views\Tests\Handler;

/**
 * Tests the generic field handler.
 *
 * @see Drupal\views\Plugin\views\field\FieldPluginBase
 */
use DOMDocument;

class FieldTest extends HandlerTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Field: Base',
      'description' => 'Tests the generic field handler.',
      'group' => 'Views Handlers',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->enableViewsTestModule();

    $this->column_map = array(
      'views_test_name' => 'name',
    );
  }

  /**
   * Overrides Drupal\views\Tests\ViewTestBase::viewsData().
   */
  protected function viewsData() {
    $data = parent::viewsData();
    $data['views_test']['job']['field']['id'] = 'test_field';

    return $data;
  }

  /**
   * Tests that the render function is called.
   */
  public function testRender() {
    $view = views_get_view('test_field_tokens');
    $this->executeView($view);

    $random_text = $this->randomName();
    $view->field['job']->setTestValue($random_text);
    $this->assertEqual($view->field['job']->theme($view->result[0]), $random_text, 'Make sure the render method rendered the manual set value.');
  }

  /**
   * Tests all things related to the query.
   */
  public function testQuery() {
    // Tests adding additional fields to the query.
    $view = $this->getBasicView();
    $view->initDisplay();
    $view->initHandlers();

    $id_field = $view->field['id'];
    $id_field->additional_fields['job'] = 'job';
    // Choose also a field alias key which doesn't match to the table field.
    $id_field->additional_fields['created_test'] = array('table' => 'views_test', 'field' => 'created');
    $view->build();

    // Make sure the field aliases have the expected value.
    $this->assertEqual($id_field->aliases['job'], 'views_test_job');
    $this->assertEqual($id_field->aliases['created_test'], 'views_test_created');

    $this->executeView($view);
    // Tests the get_value method with and without a field aliases.
    foreach ($this->dataSet() as $key => $row) {
      $id = $key + 1;
      $result = $view->result[$key];
      $this->assertEqual($id_field->get_value($result), $id);
      $this->assertEqual($id_field->get_value($result, 'job'), $row['job']);
      $this->assertEqual($id_field->get_value($result, 'created_test'), $row['created']);
    }

  }

  /**
   * Tests the click sorting functionality.
   */
  public function testClickSorting() {
    $this->drupalGet('test_click_sort');
    // Only the id and name should be click sortable, but not the name.
    $this->assertLinkByHref(url('test_click_sort', array('query' => array('order' => 'id', 'sort' => 'asc'))));
    $this->assertLinkByHref(url('test_click_sort', array('query' => array('order' => 'name', 'sort' => 'desc'))));
    $this->assertNoLinkByHref(url('test_click_sort', array('query' => array('order' => 'created'))));

    // Clicking a click sort should change the order.
    $this->clickLink(t('ID'));
    $this->assertLinkByHref(url('test_click_sort', array('query' => array('order' => 'id', 'sort' => 'desc'))));
    // Check that the output has the expected order (asc).
    $ids = $this->clickSortLoadIdsFromOutput();
    $this->assertEqual($ids, range(1, 5));

    $this->clickLink(t('ID'));
    // Check that the output has the expected order (desc).
    $ids = $this->clickSortLoadIdsFromOutput();
    $this->assertEqual($ids, range(5, 1, -1));
  }

  /**
   * Small helper function to get all ids in the output.
   *
   * @return array
   *   A list of beatle ids.
   */
  protected function clickSortLoadIdsFromOutput() {
    $fields = $this->xpath("//td[contains(@class, 'views-field-id')]");
    $ids = array();
    foreach ($fields as $field) {
      $ids[] = (int) $field[0];
    }
    return $ids;
  }

  /**
   * Assertion helper which checks whether a string is part of another string.
   *
   * @param string $haystack
   *   The value to search in.
   * @param string $needle
   *   The value to search for.
   * @param string $message
   *   The message to display along with the assertion.
   * @param string $group
   *   The type of assertion - examples are "Browser", "PHP".
   * @return bool
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertSubString($haystack, $needle, $message = '', $group = 'Other') {
    return $this->assertTrue(strpos($haystack, $needle) !== FALSE, $message, $group);
  }

  /**
   * Assertion helper which checks whether a string is not part of another string.
   *
   * @param string $haystack
   *   The value to search in.
   * @param string $needle
   *   The value to search for.
   * @param string $message
   *   The message to display along with the assertion.
   * @param string $group
   *   The type of assertion - examples are "Browser", "PHP".
   * @return bool
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertNotSubString($haystack, $needle, $message = '', $group = 'Other') {
    return $this->assertTrue(strpos($haystack, $needle) === FALSE, $message, $group);
  }

  /**
   * Parse a content and return the html element.
   *
   * @param string $content
   *   The html to parse.
   *
   * @return array
   *   An array containing simplexml objects.
   */
  protected function parseContent($content) {
    $htmlDom = new DOMDocument();
    @$htmlDom->loadHTML('<?xml encoding="UTF-8">' . $content);
    $elements = simplexml_import_dom($htmlDom);

    return $elements;
  }

  /**
   * Performs an xpath search on a certain content.
   *
   * The search is relative to the root element of the $content variable.
   *
   * @param string $content
   *   The html to parse.
   * @param string $xpath
   *   The xpath string to use in the search.
   * @param array $arguments
   *   Some arguments for the xpath.
   *
   * @return array|FALSE
   *   The return value of the xpath search. For details on the xpath string
   *   format and return values see the SimpleXML documentation,
   *   http://php.net/manual/function.simplexml-element-xpath.php.
   */
  protected function xpathContent($content, $xpath, array $arguments = array()) {
    if ($elements = $this->parseContent($content)) {
      $xpath = $this->buildXPathQuery($xpath, $arguments);
      $result = $elements->xpath($xpath);
      // Some combinations of PHP / libxml versions return an empty array
      // instead of the documented FALSE. Forcefully convert any falsish values
      // to an empty array to allow foreach(...) constructions.
      return $result ? $result : array();
    }
    else {
      return FALSE;
    }
  }

  /**
   * Tests rewriting the output to a link.
   */
  public function testAlterUrl() {
    $view = $this->getBasicView();
    $view->initDisplay();
    $view->initHandlers();
    $this->executeView($view);
    $row = $view->result[0];
    $id_field = $view->field['id'];

    // Setup the general settings required to build a link.
    $id_field->options['alter']['make_link'] = TRUE;
    $id_field->options['alter']['path'] = $path = $this->randomName();

    // Tests that the suffix/prefix appears on the output.
    $id_field->options['alter']['prefix'] = $prefix = $this->randomName();
    $id_field->options['alter']['suffix'] = $suffix = $this->randomName();
    $output = $id_field->theme($row);
    $this->assertSubString($output, $prefix);
    $this->assertSubString($output, $suffix);
    unset($id_field->options['alter']['prefix']);
    unset($id_field->options['alter']['suffix']);

    $output = $id_field->theme($row);
    $this->assertSubString($output, $path, 'Make sure that the path is part of the output');

    // Some generic test code adapted from the UrlTest class, which tests
    // mostly the different options for the path.
    global $base_url, $script_path;

    foreach (array(FALSE, TRUE) as $absolute) {
      // Get the expected start of the path string.
      $base = ($absolute ? $base_url . '/' : base_path()) . $script_path;
      $absolute_string = $absolute ? 'absolute' : NULL;
      $alter =& $id_field->options['alter'];
      $alter['path'] = 'node/123';

      $expected_result = url('node/123', array('absolute' => $absolute));
      $alter['absolute'] = $absolute;
      $result = $id_field->theme($row);
      $this->assertSubString($result, $expected_result);

      $expected_result = url('node/123', array('fragment' => 'foo', 'absolute' => $absolute));
      $alter['path'] = 'node/123#foo';
      $result = $id_field->theme($row);
      $this->assertSubString($result, $expected_result);

      $expected_result = url('node/123', array('query' => array('foo' => NULL), 'absolute' => $absolute));
      $alter['path'] = 'node/123?foo';
      $result = $id_field->theme($row);
      $this->assertSubString($result, $expected_result);

      $expected_result = url('node/123', array('query' => array('foo' => 'bar', 'bar' => 'baz'), 'absolute' => $absolute));
      $alter['path'] = 'node/123?foo=bar&bar=baz';
      $result = $id_field->theme($row);
      $this->assertSubString(decode_entities($result), decode_entities($expected_result));

      $expected_result = url('node/123', array('query' => array('foo' => NULL), 'fragment' => 'bar', 'absolute' => $absolute));
      $alter['path'] = 'node/123?foo#bar';
      $result = $id_field->theme($row);
      // @fixme: The actual result is node/123?foo#bar so views has a bug here.
      // $this->assertSubStringExists(decode_entities($result), decode_entities($expected_result));

      $expected_result = url('<front>', array('absolute' => $absolute));
      $alter['path'] = '<front>';
      $result = $id_field->theme($row);
      $this->assertSubString($result, $expected_result);
    }

    // Tests the replace spaces with dashes feature.
    $id_field->options['alter']['replace_spaces'] = TRUE;
    $id_field->options['alter']['path'] = $path = $this->randomName() . ' ' . $this->randomName();
    $output = $id_field->theme($row);
    $this->assertSubString($output, str_replace(' ', '-', $path));
    $id_field->options['alter']['replace_spaces'] = FALSE;
    $output = $id_field->theme($row);
    // The url has a space in it, so to check we have to decode the url output.
    $this->assertSubString(urldecode($output), $path);

    // Tests the external flag.
    // Switch on the external flag should output an external url as well.
    $id_field->options['alter']['external'] = TRUE;
    $id_field->options['alter']['path'] = $path = 'drupal.org';
    $output = $id_field->theme($row);
    $this->assertSubString($output, 'http://drupal.org');

    // Setup a not external url, which shouldn't lead to an external url.
    $id_field->options['alter']['external'] = FALSE;
    $id_field->options['alter']['path'] = $path = 'drupal.org';
    $output = $id_field->theme($row);
    $this->assertNotSubString($output, 'http://drupal.org');

    // Tests the transforming of the case setting.
    $id_field->options['alter']['path'] = $path = $this->randomName();
    $id_field->options['alter']['path_case'] = 'none';
    $output = $id_field->theme($row);
    $this->assertSubString($output, $path);

    // Switch to uppercase and lowercase.
    $id_field->options['alter']['path_case'] = 'upper';
    $output = $id_field->theme($row);
    $this->assertSubString($output, strtoupper($path));
    $id_field->options['alter']['path_case'] = 'lower';
    $output = $id_field->theme($row);
    $this->assertSubString($output, strtolower($path));

    // Switch to ucfirst and ucwords.
    $id_field->options['alter']['path_case'] = 'ucfirst';
    $id_field->options['alter']['path'] = 'drupal has a great community';
    $output = $id_field->theme($row);
    $this->assertSubString($output, drupal_encode_path('Drupal has a great community'));

    $id_field->options['alter']['path_case'] = 'ucwords';
    $output = $id_field->theme($row);
    $this->assertSubString($output, drupal_encode_path('Drupal Has A Great Community'));
    unset($id_field->options['alter']['path_case']);

    // Tests the linkclass setting and see whether it actuall exists in the output.
    $id_field->options['alter']['link_class'] = $class = $this->randomName();
    $output = $id_field->theme($row);
    $elements = $this->xpathContent($output, '//a[contains(@class, :class)]', array(':class' => $class));
    $this->assertTrue($elements);
    // @fixme link_class, alt, rel cannot be unset, which should be fixed.
    $id_field->options['alter']['link_class'] = '';

    // Tests the alt setting.
    $id_field->options['alter']['alt'] = $rel = $this->randomName();
    $output = $id_field->theme($row);
    $elements = $this->xpathContent($output, '//a[contains(@title, :alt)]', array(':alt' => $rel));
    $this->assertTrue($elements);
    $id_field->options['alter']['alt'] = '';

    // Tests the rel setting.
    $id_field->options['alter']['rel'] = $rel = $this->randomName();
    $output = $id_field->theme($row);
    $elements = $this->xpathContent($output, '//a[contains(@rel, :rel)]', array(':rel' => $rel));
    $this->assertTrue($elements);
    $id_field->options['alter']['rel'] = '';

    // Tests the target setting.
    $id_field->options['alter']['target'] = $target = $this->randomName();
    $output = $id_field->theme($row);
    $elements = $this->xpathContent($output, '//a[contains(@target, :target)]', array(':target' => $target));
    $this->assertTrue($elements);
    unset($id_field->options['alter']['target']);
  }


  /**
   * Tests general rewriting of the output.
   */
  public function testRewrite() {
    $view = $this->getBasicView();
    $view->initDisplay();
    $view->initHandlers();
    $this->executeView($view);
    $row = $view->result[0];
    $id_field = $view->field['id'];

    // Don't check the rewrite checkbox, so the text shouldn't appear.
    $id_field->options['alter']['text'] = $random_text = $this->randomString();
    $output = $id_field->theme($row);
    $this->assertNotSubString($output, $random_text);

    $id_field->options['alter']['alter_text'] = TRUE;
    $output = $id_field->theme($row);
    $this->assertSubString($output, $random_text);
  }

  /**
   * Tests the field/label/wrapper classes.
   */
  public function testFieldClasses() {
    $view = views_get_view('test_field_classes');
    $view->initDisplay();
    $view->initHandlers();

    // Tests whether the default field classes are added.
    $id_field = $view->field['id'];

    $id_field->options['element_default_classes'] = FALSE;
    $output = $view->preview();
    $this->assertFalse($this->xpathContent($output, '//div[contains(@class, :class)]', array(':class' => 'field-content')));
    $this->assertFalse($this->xpathContent($output, '//div[contains(@class, :class)]', array(':class' => 'field-label')));

    $id_field->options['element_default_classes'] = TRUE;
    $output = $view->preview();
    // Per default the label and the element of the field are spans.
    $this->assertTrue($this->xpathContent($output, '//span[contains(@class, :class)]', array(':class' => 'field-content')));
    $this->assertTrue($this->xpathContent($output, '//span[contains(@class, :class)]', array(':class' => 'views-label')));
    $this->assertTrue($this->xpathContent($output, '//div[contains(@class, :class)]', array(':class' => 'views-field')));

    // Tests the element wrapper classes/element.
    $random_class = $this->randomName();

    // Set some common wrapper element types and see whether they appear with and without a custom class set.
    foreach (array('h1', 'span', 'p', 'div') as $element_type) {
      $id_field->options['element_wrapper_type'] = $element_type;

      // Set a custom wrapper element css class.
      $id_field->options['element_wrapper_class'] = $random_class;
      $output = $view->preview();
      $this->assertTrue($this->xpathContent($output, "//{$element_type}[contains(@class, :class)]", array(':class' => $random_class)));

      // Set no custom css class.
      $id_field->options['element_wrapper_class'] = '';
      $output = $view->preview();
      $this->assertFalse($this->xpathContent($output, "//{$element_type}[contains(@class, :class)]", array(':class' => $random_class)));
      $this->assertTrue($this->xpathContent($output, "//li[contains(@class, views-row)]/{$element_type}"));
    }

    // Tests the label class/element.

    // Set some common label element types and see whether they appear with and without a custom class set.
    foreach (array('h1', 'span', 'p', 'div') as $element_type) {
      $id_field->options['element_label_type'] = $element_type;

      // Set a custom label element css class.
      $id_field->options['element_label_class'] = $random_class;
      $output = $view->preview();
      $this->assertTrue($this->xpathContent($output, "//li[contains(@class, views-row)]//{$element_type}[contains(@class, :class)]", array(':class' => $random_class)));

      // Set no custom css class.
      $id_field->options['element_label_class'] = '';
      $output = $view->preview();
      $this->assertFalse($this->xpathContent($output, "//li[contains(@class, views-row)]//{$element_type}[contains(@class, :class)]", array(':class' => $random_class)));
      $this->assertTrue($this->xpathContent($output, "//li[contains(@class, views-row)]//{$element_type}"));
    }

    // Tests the element classes/element.

    // Set some common element element types and see whether they appear with and without a custom class set.
    foreach (array('h1', 'span', 'p', 'div') as $element_type) {
      $id_field->options['element_type'] = $element_type;

      // Set a custom label element css class.
      $id_field->options['element_class'] = $random_class;
      $output = $view->preview();
      $this->assertTrue($this->xpathContent($output, "//li[contains(@class, views-row)]//div[contains(@class, views-field)]//{$element_type}[contains(@class, :class)]", array(':class' => $random_class)));

      // Set no custom css class.
      $id_field->options['element_class'] = '';
      $output = $view->preview();
      $this->assertFalse($this->xpathContent($output, "//li[contains(@class, views-row)]//div[contains(@class, views-field)]//{$element_type}[contains(@class, :class)]", array(':class' => $random_class)));
      $this->assertTrue($this->xpathContent($output, "//li[contains(@class, views-row)]//div[contains(@class, views-field)]//{$element_type}"));
    }

    // Tests the available html elements.
    $element_types = $id_field->get_elements();
    $expected_elements = array(
      '',
      '0',
      'div',
      'span',
      'h1',
      'h2',
      'h3',
      'h4',
      'h5',
      'h6',
      'p',
      'strong',
      'em',
      'marquee'
    );
    $this->assertEqual(array_keys($element_types), $expected_elements);
  }

  /**
   * Tests the field tokens, row level and field level.
   */
  public function testFieldTokens() {
    $view = views_get_view('test_field_tokens');
    $this->executeView($view);
    $name_field_0 = $view->field['name'];
    $name_field_1 = $view->field['name_1'];
    $name_field_2 = $view->field['name_2'];
    $row = $view->result[0];

    $name_field_0->options['alter']['alter_text'] = TRUE;
    $name_field_0->options['alter']['text'] = '[name]';

    $name_field_1->options['alter']['alter_text'] = TRUE;
    $name_field_1->options['alter']['text'] = '[name_1] [name]';

    $name_field_2->options['alter']['alter_text'] = TRUE;
    $name_field_2->options['alter']['text'] = '[name_2] [name_1]';

    foreach ($view->result as $row) {
      $expected_output_0 = $row->views_test_name;
      $expected_output_1 = "$row->views_test_name $row->views_test_name";
      $expected_output_2 = "$row->views_test_name $row->views_test_name $row->views_test_name";

      $output = $name_field_0->advanced_render($row);
      $this->assertEqual($output, $expected_output_0);

      $output = $name_field_1->advanced_render($row);
      $this->assertEqual($output, $expected_output_1);

      $output = $name_field_2->advanced_render($row);
      $this->assertEqual($output, $expected_output_2);
    }

    $job_field = $view->field['job'];
    $job_field->options['alter']['alter_text'] = TRUE;
    $job_field->options['alter']['text'] = '[test-token]';

    $random_text = $this->randomName();
    $job_field->setTestValue($random_text);
    $output = $job_field->advanced_render($row);
    $this->assertSubString($output, $random_text, format_string('Make sure the self token (!value) appears in the output (!output)'. array('!value' => $random_text, '!output' => $output)));
  }

  /**
   * Tests the exclude setting.
   */
  public function testExclude() {
    $view = views_get_view('test_field_output');
    $view->initDisplay();
    $view->initHandlers();
    // Hide the field and see whether it's rendered.
    $view->field['name']->options['exclude'] = TRUE;

    $output = $view->preview();
    foreach ($this->dataSet() as $entry) {
      $this->assertNotSubString($output, $entry['name']);
    }

    // Show and check the field.
    $view->field['name']->options['exclude'] = FALSE;

    $output = $view->preview();
    foreach ($this->dataSet() as $entry) {
      $this->assertSubString($output, $entry['name']);
    }
  }

  /**
   * Tests trimming/read-more/ellipses.
   */
  public function testTextRendering() {
    $view = views_get_view('test_field_output');
    $view->initDisplay();
    $view->initHandlers();
    $name_field = $view->field['name'];

    // Tests stripping of html elements.
    $this->executeView($view);
    $random_text = $this->randomName();
    $name_field->options['alter']['alter_text'] = TRUE;
    $name_field->options['alter']['text'] = $html_text = '<div class="views-test">' . $random_text . '</div>';
    $row = $view->result[0];

    $name_field->options['alter']['strip_tags'] = TRUE;
    $output = $name_field->advanced_render($row);
    $this->assertSubString($output, $random_text, 'Find text without html if stripping of views field output is enabled.');
    $this->assertNotSubString($output, $html_text, 'Find no text with the html if stripping of views field output is enabled.');

    // Tests preserving of html tags.
    $name_field->options['alter']['preserve_tags'] = '<div>';
    $output = $name_field->advanced_render($row);
    $this->assertSubString($output, $random_text, 'Find text without html if stripping of views field output is enabled but a div is allowed.');
    $this->assertSubString($output, $html_text, 'Find text with the html if stripping of views field output is enabled but a div is allowed.');

    $name_field->options['alter']['strip_tags'] = FALSE;
    $output = $name_field->advanced_render($row);
    $this->assertSubString($output, $random_text, 'Find text without html if stripping of views field output is disabled.');
    $this->assertSubString($output, $html_text, 'Find text with the html if stripping of views field output is disabled.');

    // Tests for removing whitespace and the beginning and the end.
    $name_field->options['alter']['alter_text'] = FALSE;
    $views_test_name = $row->views_test_name;
    $row->views_test_name = '  ' . $views_test_name . '     ';
    $name_field->options['alter']['trim_whitespace'] = TRUE;
    $output = $name_field->advanced_render($row);

    $this->assertSubString($output, $views_test_name, 'Make sure the trimmed text can be found if trimming is enabled.');
    $this->assertNotSubString($output, $row->views_test_name, 'Make sure the untrimmed text can be found if trimming is enabled.');

    $name_field->options['alter']['trim_whitespace'] = FALSE;
    $output = $name_field->advanced_render($row);
    $this->assertSubString($output, $views_test_name, 'Make sure the trimmed text can be found if trimming is disabled.');
    $this->assertSubString($output, $row->views_test_name, 'Make sure the untrimmed text can be found  if trimming is disabled.');


    // Tests for trimming to a maximum length.
    $name_field->options['alter']['trim'] = TRUE;
    $name_field->options['alter']['word_boundary'] = FALSE;

    // Tests for simple trimming by string length.
    $row->views_test_name = $this->randomName(8);
    $name_field->options['alter']['max_length'] = 5;
    $trimmed_name = drupal_substr($row->views_test_name, 0, 5);

    $output = $name_field->advanced_render($row);
    $this->assertSubString($output, $trimmed_name, format_string('Make sure the trimmed output (!trimmed) appears in the rendered output (!output).', array('!trimmed' => $trimmed_name, '!output' => $output)));
    $this->assertNotSubString($output, $row->views_test_name, format_string("Make sure the untrimmed value (!untrimmed) shouldn't appear in the rendered output (!output).", array('!untrimmed' => $row->views_test_name, '!output' => $output)));

    $name_field->options['alter']['max_length'] = 9;
    $output = $name_field->advanced_render($row);
    $this->assertSubString($output, $trimmed_name, format_string('Make sure the untrimmed (!untrimmed) output appears in the rendered output  (!output).', array('!trimmed' => $trimmed_name, '!output' => $output)));

    // Take word_boundary into account for the tests.
    $name_field->options['alter']['max_length'] = 5;
    $name_field->options['alter']['word_boundary'] = TRUE;
    $random_text_2 = $this->randomName(2);
    $random_text_4 = $this->randomName(4);
    $random_text_8 = $this->randomName(8);
    $touples = array(
      // Create one string which doesn't fit at all into the limit.
      array(
        'value' => $random_text_8,
        'trimmed_value' => '',
        'trimmed' => TRUE
      ),
      // Create one string with two words which doesn't fit both into the limit.
      array(
        'value' => $random_text_8 . ' ' . $random_text_8,
        'trimmed_value' => '',
        'trimmed' => TRUE
      ),
      // Create one string which contains of two words, of which only the first
      // fits into the limit.
      array(
        'value' => $random_text_4 . ' ' . $random_text_8,
        'trimmed_value' => $random_text_4,
        'trimmed' => TRUE
      ),
      // Create one string which contains of two words, of which both fits into
      // the limit.
      array(
        'value' => $random_text_2 . ' ' . $random_text_2,
        'trimmed_value' => $random_text_2 . ' ' . $random_text_2,
        'trimmed' => FALSE
      )
    );

    foreach ($touples as $touple) {
      $row->views_test_name = $touple['value'];
      $output = $name_field->advanced_render($row);

      if ($touple['trimmed']) {
        $this->assertNotSubString($output, $touple['value'], format_string('The untrimmed value (!untrimmed) should not appear in the trimmed output (!output).', array('!untrimmed' => $touple['value'], '!output' => $output)));
      }
      if (!empty($touble['trimmed_value'])) {
        $this->assertSubString($output, $touple['trimmed_value'], format_string('The trimmed value (!trimmed) should appear in the trimmed output (!output).', array('!trimmed' => $touple['trimmed_value'], '!output' => $output)));
      }
    }

    // Tests for displaying a readmore link when the output got trimmed.
    $row->views_test_name = $this->randomName(8);
    $name_field->options['alter']['max_length'] = 5;
    $name_field->options['alter']['more_link'] = TRUE;
    $name_field->options['alter']['more_link_text'] = $more_text = $this->randomName();
    $name_field->options['alter']['more_link_path'] = $more_path = $this->randomName();

    $output = $name_field->advanced_render($row);
    $this->assertSubString($output, $more_text, 'Make sure a read more text is displayed if the output got trimmed');
    $this->assertTrue($this->xpathContent($output, '//a[contains(@href, :path)]', array(':path' => $more_path)), 'Make sure the read more link points to the right destination.');

    $name_field->options['alter']['more_link'] = FALSE;
    $output = $name_field->advanced_render($row);
    $this->assertNotSubString($output, $more_text, 'Make sure no read more text appears.');
    $this->assertFalse($this->xpathContent($output, '//a[contains(@href, :path)]', array(':path' => $more_path)), 'Make sure no read more link appears.');

    // Check for the ellipses.
    $row->views_test_name = $this->randomName(8);
    $name_field->options['alter']['max_length'] = 5;
    $output = $name_field->advanced_render($row);
    $this->assertSubString($output, '...', 'An ellipsis should appear if the output is trimmed');
    $name_field->options['alter']['max_length'] = 10;
    $output = $name_field->advanced_render($row);
    $this->assertNotSubString($output, '...', 'No ellipsis should appear if the output is not trimmed');
  }

  /**
   * Tests everything related to empty output of a field.
   */
  function testEmpty() {
    $this->_testHideIfEmpty();
    $this->_testEmptyText();
  }

  /**
   * Tests the hide if empty functionality.
   *
   * This tests alters the result to get easier and less coupled results.
   */
  function _testHideIfEmpty() {
    $view = $this->getView();
    $view->initDisplay();
    $this->executeView($view);

    $column_map_reversed = array_flip($this->column_map);
    $view->row_index = 0;
    $random_name = $this->randomName();
    $random_value = $this->randomName();

    // Test when results are not rewritten and empty values are not hidden.
    $view->field['name']->options['hide_alter_empty'] = FALSE;
    $view->field['name']->options['hide_empty'] = FALSE;
    $view->field['name']->options['empty_zero'] = FALSE;

    // Test a valid string.
    $view->result[0]->{$column_map_reversed['name']} = $random_name;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_name, 'By default, a string should not be treated as empty.');

    // Test an empty string.
    $view->result[0]->{$column_map_reversed['name']} = "";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'By default, "" should not be treated as empty.');

    // Test zero as an integer.
    $view->result[0]->{$column_map_reversed['name']} = 0;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, '0', 'By default, 0 should not be treated as empty.');

    // Test zero as a string.
    $view->result[0]->{$column_map_reversed['name']} = "0";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "0", 'By default, "0" should not be treated as empty.');

    // Test when results are not rewritten and non-zero empty values are hidden.
    $view->field['name']->options['hide_alter_empty'] = TRUE;
    $view->field['name']->options['hide_empty'] = TRUE;
    $view->field['name']->options['empty_zero'] = FALSE;

    // Test a valid string.
    $view->result[0]->{$column_map_reversed['name']} = $random_name;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_name, 'If hide_empty is checked, a string should not be treated as empty.');

    // Test an empty string.
    $view->result[0]->{$column_map_reversed['name']} = "";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'If hide_empty is checked, "" should be treated as empty.');

    // Test zero as an integer.
    $view->result[0]->{$column_map_reversed['name']} = 0;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, '0', 'If hide_empty is checked, but not empty_zero, 0 should not be treated as empty.');

    // Test zero as a string.
    $view->result[0]->{$column_map_reversed['name']} = "0";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "0", 'If hide_empty is checked, but not empty_zero, "0" should not be treated as empty.');

    // Test when results are not rewritten and all empty values are hidden.
    $view->field['name']->options['hide_alter_empty'] = TRUE;
    $view->field['name']->options['hide_empty'] = TRUE;
    $view->field['name']->options['empty_zero'] = TRUE;

    // Test zero as an integer.
    $view->result[0]->{$column_map_reversed['name']} = 0;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'If hide_empty and empty_zero are checked, 0 should be treated as empty.');

    // Test zero as a string.
    $view->result[0]->{$column_map_reversed['name']} = "0";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'If hide_empty and empty_zero are checked, "0" should be treated as empty.');

    // Test when results are rewritten to a valid string and non-zero empty
    // results are hidden.
    $view->field['name']->options['hide_alter_empty'] = FALSE;
    $view->field['name']->options['hide_empty'] = TRUE;
    $view->field['name']->options['empty_zero'] = FALSE;
    $view->field['name']->options['alter']['alter_text'] = TRUE;
    $view->field['name']->options['alter']['text'] = $random_name;

    // Test a valid string.
    $view->result[0]->{$column_map_reversed['name']} = $random_value;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_name, 'If the rewritten string is not empty, it should not be treated as empty.');

    // Test an empty string.
    $view->result[0]->{$column_map_reversed['name']} = "";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_name, 'If the rewritten string is not empty, "" should not be treated as empty.');

    // Test zero as an integer.
    $view->result[0]->{$column_map_reversed['name']} = 0;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_name, 'If the rewritten string is not empty, 0 should not be treated as empty.');

    // Test zero as a string.
    $view->result[0]->{$column_map_reversed['name']} = "0";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_name, 'If the rewritten string is not empty, "0" should not be treated as empty.');

    // Test when results are rewritten to an empty string and non-zero empty results are hidden.
    $view->field['name']->options['hide_alter_empty'] = TRUE;
    $view->field['name']->options['hide_empty'] = TRUE;
    $view->field['name']->options['empty_zero'] = FALSE;
    $view->field['name']->options['alter']['alter_text'] = TRUE;
    $view->field['name']->options['alter']['text'] = "";

    // Test a valid string.
    $view->result[0]->{$column_map_reversed['name']} = $random_name;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_name, 'If the rewritten string is empty, it should not be treated as empty.');

    // Test an empty string.
    $view->result[0]->{$column_map_reversed['name']} = "";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'If the rewritten string is empty, "" should be treated as empty.');

    // Test zero as an integer.
    $view->result[0]->{$column_map_reversed['name']} = 0;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, '0', 'If the rewritten string is empty, 0 should not be treated as empty.');

    // Test zero as a string.
    $view->result[0]->{$column_map_reversed['name']} = "0";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "0", 'If the rewritten string is empty, "0" should not be treated as empty.');

    // Test when results are rewritten to zero as a string and non-zero empty
    // results are hidden.
    $view->field['name']->options['hide_alter_empty'] = FALSE;
    $view->field['name']->options['hide_empty'] = TRUE;
    $view->field['name']->options['empty_zero'] = FALSE;
    $view->field['name']->options['alter']['alter_text'] = TRUE;
    $view->field['name']->options['alter']['text'] = "0";

    // Test a valid string.
    $view->result[0]->{$column_map_reversed['name']} = $random_name;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "0", 'If the rewritten string is zero and empty_zero is not checked, the string rewritten as 0 should not be treated as empty.');

    // Test an empty string.
    $view->result[0]->{$column_map_reversed['name']} = "";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "0", 'If the rewritten string is zero and empty_zero is not checked, "" rewritten as 0 should not be treated as empty.');

    // Test zero as an integer.
    $view->result[0]->{$column_map_reversed['name']} = 0;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "0", 'If the rewritten string is zero and empty_zero is not checked, 0 should not be treated as empty.');

    // Test zero as a string.
    $view->result[0]->{$column_map_reversed['name']} = "0";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "0", 'If the rewritten string is zero and empty_zero is not checked, "0" should not be treated as empty.');

    // Test when results are rewritten to a valid string and non-zero empty
    // results are hidden.
    $view->field['name']->options['hide_alter_empty'] = TRUE;
    $view->field['name']->options['hide_empty'] = TRUE;
    $view->field['name']->options['empty_zero'] = FALSE;
    $view->field['name']->options['alter']['alter_text'] = TRUE;
    $view->field['name']->options['alter']['text'] = $random_value;

    // Test a valid string.
    $view->result[0]->{$column_map_reversed['name']} = $random_name;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_value, 'If the original and rewritten strings are valid, it should not be treated as empty.');

    // Test an empty string.
    $view->result[0]->{$column_map_reversed['name']} = "";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'If either the original or rewritten string is invalid, "" should be treated as empty.');

    // Test zero as an integer.
    $view->result[0]->{$column_map_reversed['name']} = 0;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_value, 'If the original and rewritten strings are valid, 0 should not be treated as empty.');

    // Test zero as a string.
    $view->result[0]->{$column_map_reversed['name']} = "0";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $random_value, 'If the original and rewritten strings are valid, "0" should not be treated as empty.');

    // Test when results are rewritten to zero as a string and all empty
    // original values and results are hidden.
    $view->field['name']->options['hide_alter_empty'] = TRUE;
    $view->field['name']->options['hide_empty'] = TRUE;
    $view->field['name']->options['empty_zero'] = TRUE;
    $view->field['name']->options['alter']['alter_text'] = TRUE;
    $view->field['name']->options['alter']['text'] = "0";

    // Test a valid string.
    $view->result[0]->{$column_map_reversed['name']} = $random_name;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'If the rewritten string is zero, it should be treated as empty.');

    // Test an empty string.
    $view->result[0]->{$column_map_reversed['name']} = "";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'If the rewritten string is zero, "" should be treated as empty.');

    // Test zero as an integer.
    $view->result[0]->{$column_map_reversed['name']} = 0;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'If the rewritten string is zero, 0 should not be treated as empty.');

    // Test zero as a string.
    $view->result[0]->{$column_map_reversed['name']} = "0";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "", 'If the rewritten string is zero, "0" should not be treated as empty.');
  }

  /**
   * Tests the usage of the empty text.
   */
  function _testEmptyText() {
    $view = $this->getView();
    $view->initDisplay();
    $this->executeView($view);

    $column_map_reversed = array_flip($this->column_map);
    $view->row_index = 0;

    $empty_text = $view->field['name']->options['empty'] = $this->randomName();
    $view->result[0]->{$column_map_reversed['name']} = "";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $empty_text, 'If a field is empty, the empty text should be used for the output.');

    $view->result[0]->{$column_map_reversed['name']} = "0";
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, "0", 'If a field is 0 and empty_zero is not checked, the empty text should not be used for the output.');

    $view->result[0]->{$column_map_reversed['name']} = "0";
    $view->field['name']->options['empty_zero'] = TRUE;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $empty_text, 'If a field is 0 and empty_zero is checked, the empty text should be used for the output.');

    $view->result[0]->{$column_map_reversed['name']} = "";
    $view->field['name']->options['alter']['alter_text'] = TRUE;
    $alter_text = $view->field['name']->options['alter']['text'] = $this->randomName();
    $view->field['name']->options['hide_alter_empty'] = FALSE;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $alter_text, 'If a field is empty, some rewrite text exists, but hide_alter_empty is not checked, render the rewrite text.');

    $view->field['name']->options['hide_alter_empty'] = TRUE;
    $render = $view->field['name']->advanced_render($view->result[0]);
    $this->assertIdentical($render, $empty_text, 'If a field is empty, some rewrite text exists, and hide_alter_empty is checked, use the empty text.');
  }

  /**
   * Tests views_handler_field::is_value_empty().
   */
  function testIsValueEmpty() {
    $view = $this->getView();
    $view->initDisplay();
    $view->initHandlers();
    $field = $view->field['name'];

    $this->assertFalse($field->is_value_empty("not empty", TRUE), 'A normal string is not empty.');
    $this->assertTrue($field->is_value_empty("not empty", TRUE, FALSE), 'A normal string which skips empty() can be seen as empty.');

    $this->assertTrue($field->is_value_empty("", TRUE), '"" is considered as empty.');

    $this->assertTrue($field->is_value_empty('0', TRUE), '"0" is considered as empty if empty_zero is TRUE.');
    $this->assertTrue($field->is_value_empty(0, TRUE), '0 is considered as empty if empty_zero is TRUE.');
    $this->assertFalse($field->is_value_empty('0', FALSE), '"0" is considered not as empty if empty_zero is FALSE.');
    $this->assertFalse($field->is_value_empty(0, FALSE), '0 is considered not as empty if empty_zero is FALSE.');

    $this->assertTrue($field->is_value_empty(NULL, TRUE, TRUE), 'Null should be always seen as empty, regardless of no_skip_empty.');
    $this->assertTrue($field->is_value_empty(NULL, TRUE, FALSE), 'Null should be always seen as empty, regardless of no_skip_empty.');
  }

}
