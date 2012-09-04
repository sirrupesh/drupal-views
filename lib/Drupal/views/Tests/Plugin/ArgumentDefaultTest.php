<?php

/**
 * @file
 * Definition of Drupal\views\Tests\Plugin\ArgumentDefaultTest.
 */

namespace Drupal\views\Tests\Plugin;

/**
 * Basic test for pluggable argument default.
 */
class ArgumentDefaultTest extends PluginTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('views_ui');

  /**
   * A random string used in the default views.
   *
   * @var string
   */
  protected $random;

  public static function getInfo() {
    return array(
      'name' => 'Argument default',
      'description' => 'Tests pluggable argument_default for views.',
      'group' => 'Views Plugins'
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->enableViewsTestModule();

    $this->random = $this->randomString();
  }

  /**
   * Tests the use of a default argument plugin that provides no options.
   */
  function testArgumentDefaultNoOptions() {
    $admin_user = $this->drupalCreateUser(array('administer views', 'administer site configuration'));
    $this->drupalLogin($admin_user);

    // The current_user plugin has no options form, and should pass validation.
    $argument_type = 'current_user';
    $edit = array(
      'options[default_argument_type]' => $argument_type,
    );
    $this->drupalPost('admin/structure/views/nojs/config-item/test_argument_default_current_user/default/argument/uid', $edit, t('Apply'));

    // Note, the undefined index error has two spaces after it.
    $error = array(
      '%type' => 'Notice',
      '!message' => 'Undefined index:  ' . $argument_type,
      '%function' => 'views_handler_argument->validateOptionsForm()',
    );
    $message = t('%type: !message in %function', $error);
    $this->assertNoRaw($message, t('Did not find error message: !message.', array('!message' => $message)));
  }

  /**
   * Tests fixed default argument.
   */
  function testArgumentDefaultFixed() {
    $view = $this->view_argument_default_fixed();

    $view->setDisplay('default');
    $view->preExecute();
    $view->initHandlers();

    $this->assertEqual($view->argument['null']->get_default_argument(), $this->random, 'Fixed argument should be used by default.');

    $view->destroy();

    // Make sure that a normal argument provided is used
    $view = $this->view_argument_default_fixed();

    $view->setDisplay('default');
    $random_string = $this->randomString();
    $view->executeDisplay('default', array($random_string));

    $this->assertEqual($view->args[0], $random_string, 'Provided argument should be used.');
  }

  /**
   * @todo Test php default argument.
   */
  //function testArgumentDefaultPhp() {}

  /**
   * @todo Test node default argument.
   */
  //function testArgumentDefaultNode() {}

  function view_argument_default_fixed() {
    $view =  $this->createViewFromConfig('test_argument_default_fixed');
    $view->display_handler->display->display_options['arguments']['null']['default_argument_options']['argument'] = $this->random;

    return $view;
  }

}
