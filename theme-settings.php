<?php

use \Drupal\Core\File\FileSystemInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function pece_scholarly_lite_form_system_theme_settings_alter(&$form, &$form_state) {

  $form['looknfeel']['custom_color_scheme'] = array(
    '#type' => 'checkbox',
    '#title' => t('Custom Color Scheme'),
    '#description'   => t('Select the color scheme you prefer.'),
    '#default_value' => theme_get_setting('custom_color_scheme'),
  );

  $form['looknfeel']['color_scheme_settings'] = array(
    '#type' => 'container',
    '#states' => array(
      'invisible' => array(
       ':input[name="custom_color_scheme"]' => array('checked' => FALSE),
      ),
    ),
    '#attached' => [
      'library' => [
        'core/jquery.farbtastic',
        'pece_scholarly_lite/color-picker',
      ],
    ],
  );

  $form['looknfeel']['color_scheme_settings']['color_primary'] = array(
    '#type' => 'textfield',
    '#title' => t('Primary Color'),
    '#description'   => t('The main color of the website (Header menu and major details). e.g. #A1A1A1'),
    '#default_value' => theme_get_setting('color_primary'),
    '#element_validate' => array('pece_scholarly_lite_color_validate'),
    '#states' => array(
      'required' => array(
       ':input[name="custom_color_scheme"]' => array('checked' => TRUE),
      ),
    ),
    '#size' => 20,
    '#attributes' => array('class' => array('pece-field-colorpicker')),
    '#prefix' => '<div class="pece-colorfield-wrapper">',
    '#suffix' => '<div class="pece-colorpicker"></div></div>',
  );

  $form['looknfeel']['color_scheme_settings']['color_secondary'] = array(
    '#type' => 'textfield',
    '#title' => t('Secondary Color'),
    '#description'   => t('Used on links and buttons. e.g. #888888'),
    '#default_value' => theme_get_setting('color_secondary'),
    '#element_validate' => array('pece_scholarly_lite_color_validate'),
    '#states' => array(
      'required' => array(
       ':input[name="custom_color_scheme"]' => array('checked' => TRUE),
      ),
    ),
    '#size' => 20,
    '#attributes' => array('class' => array('pece-field-colorpicker')),
    '#prefix' => '<div class="pece-colorfield-wrapper">',
    '#suffix' => '<div class="pece-colorpicker"></div></div>',
  );

  $form['looknfeel']['color_scheme_settings']['color_tertiary'] = array(
    '#type' => 'textfield',
    '#title' => t('Tertiary Color'),
    '#description'   => t('Used on header menu active page, active links and buttons. e.g. #5F5F5F'),
    '#default_value' => theme_get_setting('color_tertiary'),
    '#element_validate' => array('pece_scholarly_lite_color_validate'),
    '#states' => array(
      'required' => array(
       ':input[name="custom_color_scheme"]' => array('checked' => TRUE),
      ),
    ),
    '#size' => 20,
    '#attributes' => array('class' => array('pece-field-colorpicker')),
    '#prefix' => '<div class="pece-colorfield-wrapper">',
    '#suffix' => '<div class="pece-colorpicker"></div></div>',
  );

  $form['#submit'][] = 'pece_scholarly_lite_system_theme_settings_submit';
}

/**
 * Form Element Validate function for color scheme overrides.
 */
function pece_scholarly_lite_color_validate($element, &$form_state, $form) {
  $values = $form_state->getValues();
  if ($form_state->getValue('custom_color_scheme') && empty($values[$element['#name']]))
    form_error($element, t('Custom scheme - %name is required.', array('%name' => $element['#title'])));
}

/**
 * Submit function for color scheme overrides.
 * Apply colors and create stylesheet file.
 */
function pece_scholarly_lite_system_theme_settings_submit($form, $form_state) {
  // Exit when color overrides is not set.
  if (!$form_state->getValue('custom_color_scheme')) {
    // Remove Scheme Override Styles if exists.
    try {
      \Drupal::service('file_system')->delete('public://pece_scholarly_lite/scheme_override.css');
    }
    catch (\Drupal\Core\File\Exception\FileException $e) {
      \Drupal::logger('file')->error($e);
    }
    return;
  }

  // Prepare override stylesheet template.
  $theme_path = \Drupal::service('extension.path.resolver')->getPath('theme', 'pece_scholarly_lite');
  $base_style = file_get_contents($theme_path . '/assets/overrides/scheme_override.base.css');
  $directory = 'public://pece_scholarly_lite/';
  try {
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
  } catch (\Drupal\Core\File\Exception\FileException $e) {
    \Drupal::logger('file')->error($e);
  }

  // Update colors on template styles.
  $scheme_override = str_replace('_color_primary_', $form_state->getValue('color_primary'), $base_style);
  $scheme_override = str_replace('_color_dark_primary_', darkenColor($form_state->getValue('color_primary'), 90), $scheme_override);
  $scheme_override = str_replace('_color_alpha_primary_', hex2rgba($form_state->getValue('color_primary'), 0.95), $scheme_override);
  $scheme_override = str_replace('_color_secondary_', $form_state->getValue('color_secondary'), $scheme_override);
  $scheme_override = str_replace('_color_alpha_secondary_', hex2rgba($form_state->getValue('color_secondary'), 0.95), $scheme_override);
  $scheme_override = str_replace('_color_tertiary_', $form_state->getValue('color_tertiary'), $scheme_override);
  try {
    \Drupal::service('file_system')->delete('public://pece_scholarly_lite/scheme_override.css');
  }
  catch (\Drupal\Core\File\Exception\FileException $e) {
    \Drupal::logger('file')->error($e);
  }
  // Saves file to site's public directory and replaces it if already exists.
  try {
    $file = \Drupal::service('file_system')->saveData($scheme_override, 'public://pece_scholarly_lite/scheme_override.css', FILE_EXISTS_REPLACE);
  }
  catch (\Drupal\Core\File\Exception\FileException $e) {
    \Drupal::logger('file')->error($e);
  }
  if (!isset($file) || $file == FALSE) {
    \Drupal::messenger()->addError('Error on saving Custom color scheme.');
    return;
  };

  // Enforce reload for all users.
  _drupal_flush_css_js();

  \Drupal::service('asset.css.collection_optimizer')->deleteAll();
  //drupal_clear_css_cache();
  \Drupal::service('asset.js.collection_optimizer')->deleteAll();
  //drupal_clear_js_cache();

  // Clear the page cache, since cached HTML pages might link to old CSS and
  // JS aggregates.
  \Drupal::cache('data')->invalidate('*');
  //cache_clear_all('*', 'cache_page', TRUE);

  \Drupal::messenger()->addStatus('Custom color scheme successfully created.');
}

/**
 * Darken the color to the percentage privided.
 * Default to decrease 20%.
*/
function darkenColor($color, $percent = 80) {
  if(!preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $color, $parts))
  $colr = '';
  for($i = 1; $i <= 3; $i++) {
    $parts[$i] = hexdec($parts[$i]);
    $parts[$i] = ($percent > 0) ? round($parts[$i] * $percent/100) : $parts[$i];
    $colr .= str_pad(dechex($parts[$i]), 2, '0', STR_PAD_LEFT);
  }
  return '#' . $colr;
}

/**
 * Sanitize $color by removing "#" if provided.
*/
function color_sanitize($color) {
  if ($color[0] == '#' ) {
    $color = substr( $color, 1 );
  }
  return $color;
}

/* Convert hexdec color string to rgb(a) string */
function hex2rgba($color, $opacity = false) {
  $default = 'rgb(0,0,0)';

  //Return default if no color provided
  if(empty($color))
    return $default;

  //Sanitize $color
  $color = color_sanitize($color);

  //Check if color has 6 or 3 characters and get values
  if (strlen($color) == 6) {
    $hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
  } elseif ( strlen( $color ) == 3 ) {
   $hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
  } else {
    return $default;
  }

  //Convert hexadec to rgb
  $rgb =  array_map('hexdec', $hex);

  //Check if opacity is set(rgba or rgb)
  if($opacity){
    if(abs($opacity) > 1)
      $opacity = 1.0;
    $output = 'rgba('.implode(",",$rgb).','.$opacity.')';
  } else {
   $output = 'rgb('.implode(",",$rgb).')';
  }

  //Return rgb(a) color string
  return $output;
}
