<?php

namespace Drupal\leaflet_latlon\Plugin\views\style;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Component\Utility\Html;
use Drupal\leaflet_views\Plugin\views\style\LeafletMap;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * Style plugin to render a view output as a leaflet map.
 *
 * @ingroup views_style_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @ViewsStyle(
 *   id = "leaflet_latlon_map",
 *   title = @Translation("Leaflet LatLon Map"),
 *   help = @Translation("Displays a View as a Leaflet map."),
 *   display_types = {"normal"},
 *   theme = "leaflet-map"
 * )
 */
class LeafletLatLonMap extends LeafletMap {

  /**
   * Specifies if the plugin uses row plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    StylePluginBase::init($view, $display, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['leaflet_geo']['leaflet_lat'] = ['default' => ''];
    $options['leaflet_geo']['leaflet_long'] = ['default' => ''];
    $options['name_field'] = ['default' => ''];
    $options['description_field'] = ['default' => ''];
    $options['icon']['default'] = [
      'iconUrl' => '',
      'shadowUrl' => '',
      'iconSize' => [
        'x' => '',
        'y' => '',
      ],
      'iconAnchor' => [
        'x' => '',
        'y' => '',
      ],
      'shadowAnchor' => [
        'x' => '',
        'y' => '',
      ],
      'popupAnchor' => [
        'x' => '',
        'y' => '',
      ],
    ];
    $options['map_position']['default'] = [
      'force' => 0,
      'center' => [
        'lat' => 0,
        'lon' => 0,
      ],
      'zoom' => 12,
      'minZoom' => 1,
      'maxZoom' => 18,
      'zoomFiner' => 0,
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    StylePluginBase::buildOptionsForm($form, $form_state);
    $options = $this->displayHandler->getFieldLabels(TRUE);

    $form['#attached'] = [
      'library' => [
        'leaflet/general',
      ],
    ];

    $form['leaflet_geo'] = [
      '#type' => 'tree',
    ];
    $form['leaflet_geo']['leaflet_lat'] = [
      '#title' => $this->t('Latitude'),
      '#description' => $this->t('Select the field that will be used for latitude'),
      '#type' => 'select',
      '#default_value' => $this->options['leaflet_geo']['leaflet_lat'],
      '#options' => array_merge(['' => ' - None - '], $options),
    ];

    $form['leaflet_geo']['leaflet_long'] = [
      '#title' => $this->t('Longitude'),
      '#description' => $this->t('Select the field that will be used for logitude'),
      '#type' => 'select',
      '#default_value' => $this->options['leaflet_geo']['leaflet_long'],
      '#options' => array_merge(['' => ' - None - '], $options),
    ];

    // Name field.
    $form['name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Title Field'),
      '#description' => $this->t('Choose the field which will appear as a title on tooltips.'),
      '#options' => array_merge(['' => ' - None - '], $options),
      '#default_value' => $this->options['name_field'],
    ];

    $form['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description Field'),
      '#description' => $this->t('Choose the field or rendering method which will appear as a description on tooltips or popups.'),
      '#required' => FALSE,
      '#options' => array_merge(['' => ' - None - '], $options),
      '#default_value' => $this->options['description_field'],
    ];

    // Generate the Leaflet Map General Settings.
    $this->generateMapGeneralSettings($form, $this->options);

    // Generate the Leaflet Map Reset Control.
    $this->setResetMapControl($form, $this->options);

    // Generate the Leaflet Map Position Form Element.
    $map_position_options = $this->options['map_position'];
    $form['map_position'] = $this->generateMapPositionElement($map_position_options);

    // Generate Icon form element.
    $icon_options = $this->options['icon'];

    $form['icon'] = $this->generateIconFormElement($icon_options, $form);

    // Set Map Marker Cluster Element.
    $this->setMapMarkerclusterElement($form, $this->options);

    // Set Map Geometries Options Element.
    $this->setMapPathOptionsElement($form, $this->options);

    // Set Map Geocoder Control Element, if the Geocoder Module exists,
    // otherwise output a tip on Geocoder Module Integration.
    $this->setGeocoderMapControl($form, $this->options);
  }

  /**
   * Renders the View.
   */
  public function render() {
    $data = [];

    // Collect bubbleable metadata when doing early rendering.
    $build_for_bubbleable_metadata = [];

    // Always render the map, otherwise ...
    $leaflet_map_style = !isset($this->options['leaflet_map']) ? $this->options['map'] : $this->options['leaflet_map'];
    $map = leaflet_map_get_info($leaflet_map_style);

    // Set Map additional map Settings.
    $this->setAdditionalMapOptions($map, $this->options);

    // Add a specific map id.
    $map['id'] = Html::getUniqueId("symphony3_map_view_" . $this->view->id() . '_' . $this->view->current_display);

    if ($geofield_name = $this->options['leaflet_geo']) {
      $this->renderFields($this->view->result);
      foreach ($this->view->result as $id => $result) {
        $lon = isset($this->view->field[$geofield_name['leaflet_long']])
          ? $this->view->field[$geofield_name['leaflet_long']]->getValue($this->view->result[$result->index]) : NULL;
        $lat = isset($this->view->field[$geofield_name['leaflet_lat']])
          ? $this->view->field[$geofield_name['leaflet_lat']]->getValue($this->view->result[$result->index]) : NULL;
        $geofield_value = NULL;
        if (!is_null($lon) && !is_null($lat)) {
          $geofield_value = ["POINT ({$lon} {$lat})"];
        }
        if (!empty($geofield_value)) {

          $features = $this->leafletService->leafletProcessGeofield($geofield_value);
          $view = $this->view;

          if (!empty($this->options['description_field'])
          && isset($this->rendered_fields[$result->index][$this->options['description_field']])) {
            $render_desc = [
              '#type' => 'markup',
              '#markup' => $this->rendered_fields[$result->index][$this->options['description_field']],
            ];
            $description = $this->renderer->renderPlain($render_desc);
          }

          if (!empty($this->options['name_field'])
          && isset($this->rendered_fields[$result->index][$this->options['name_field']])) {
            $render_label = [
              '#type' => 'markup',
              '#markup' => $this->rendered_fields[$result->index][$this->options['name_field']],
            ];
            $tooltip_label = Html::decodeEntities($this->renderer->renderPlain($render_label));
          }

          // Merge eventual map icon definition from hook_leaflet_map_info.
          if (!empty($map['icon'])) {
            $this->options['icon'] = $this->options['icon'] ?: [];
            // Remove empty icon options so that they might be replaced by
            // the ones set by the hook_leaflet_map_info.
            foreach ($this->options['icon'] as $k => $icon_option) {
              if (empty($icon_option) || (is_array($icon_option) && $this->leafletService->multipleEmpty($icon_option))) {
                unset($this->options['icon'][$k]);
              }
            }
            $this->options['icon'] = array_replace($map['icon'], $this->options['icon']);
          }

          // Define possible tokens.
          $tokens = [];
          foreach ($this->rendered_fields[$result->index] as $field_name => $field_value) {
            $tokens[$field_name] = $field_value;
            $tokens["{{ $field_name }}"] = $field_value;
          }
          $icon_type = isset($this->options['icon']['iconType']) ? $this->options['icon']['iconType'] : 'marker';

          // Relates the feature with additional properties.
          foreach ($features as &$feature) {
            // Attach pop-ups if we have a description field.
            if (isset($description)) {
              $feature['popup'] = $description;
            }

            // Attach also titles, they might be used later on.
            if ($tooltip_label) {
              $feature['label'] = $tooltip_label;
            }

            // Eventually set the custom Marker icon (DivIcon, Icon Url or
            // Circle Marker).
            if ($feature['type'] === 'point' && isset($this->options['icon'])) {
              $feature['icon'] = $this->options['icon'];
              switch ($icon_type) {
                case 'html':
                  $feature['icon']['html'] = str_replace(["\n", "\r"], "", $this->viewsTokenReplace($this->options['icon']['html'], $tokens));
                  $feature['icon']['html_class'] = $this->options['icon']['html_class'];
                  break;

                case 'circle_marker':
                  $feature['icon']['options'] = str_replace(["\n", "\r"], "", $this->viewsTokenReplace($this->options['icon']['circle_marker_options'], $tokens));
                  break;

                default:
                  if (!empty($this->options['icon']['iconUrl'])) {
                    $feature['icon']['iconUrl'] = str_replace(["\n", "\r"], "", $this->viewsTokenReplace($this->options['icon']['iconUrl'], $tokens));
                    // Generate correct Absolute iconUrl & shadowUrl,
                    // if not external.
                    if (!empty($feature['icon']['iconUrl'])) {
                      $feature['icon']['iconUrl'] = $this->leafletService->generateAbsoluteString($feature['icon']['iconUrl']);
                    }
                    if (!empty($this->options['icon']['shadowUrl'])) {
                      $feature['icon']['shadowUrl'] = str_replace(["\n", "\r"], "", $this->viewsTokenReplace($this->options['icon']['shadowUrl'], $tokens));
                      if (!empty($feature['icon']['shadowUrl'])) {
                        $feature['icon']['shadowUrl'] = $this->leafletService->generateAbsoluteString($feature['icon']['shadowUrl']);
                      }
                    }
                  }

                  // Set the Feature IconSize and ShadowSize to the IconUrl or
                  // ShadowUrl Image sizes (if empty or invalid).
                  $this->leafletService->setFeatureIconSizesIfEmptyOrInvalid($feature);
                  break;
              }
            }

            // Add new points to the whole basket.
            $data = array_merge($data, $features);
          }
        }
      }
    }

    // Don't render the map, if we do not have any data
    // and the hide option is set.
    if (empty($data) && !empty($this->options['hide_empty_map'])) {
      return [];
    }

    // Order the data features based on the 'weight' element.
    uasort($data, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

    $js_settings = [
      'map' => $map,
      'features' => $data,
    ];

    // Allow other modules to add/alter the map js settings.
    $this->moduleHandler->alter('leaflet_map_view_style', $js_settings, $this);

    $map_height = !empty($this->options['height']) ? $this->options['height'] . $this->options['height_unit'] : '';
    $element = $this->leafletService->leafletRenderMap($js_settings['map'], $js_settings['features'], $map_height);
    // Add the Core Drupal Ajax library for Ajax Popups.
    if (isset($map['settings']['ajaxPoup']) && $map['settings']['ajaxPoup'] == TRUE) {
      $build_for_bubbleable_metadata['#attached']['library'][] = 'core/drupal.ajax';
    }

    BubbleableMetadata::createFromRenderArray($element)
      ->merge(BubbleableMetadata::createFromRenderArray($build_for_bubbleable_metadata))
      ->applyTo($element);

    return $element;
  }

}
