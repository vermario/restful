<?php

/**
 * @file
 * Contains \Drupal\restful\Plugin\formatter\FormatterJson.
 */

namespace Drupal\restful\Plugin\formatter;
use Drupal\restful\Plugin\resource\Field\ResourceFieldInterface;

/**
 * Class FormatterHalJson
 * @package Drupal\restful\Plugin\formatter
 *
 * @Formatter(
 *   id = "json",
 *   label = "Simple JSON",
 *   description = "Output in using the JSON format."
 * )
 */
class FormatterJson extends Formatter implements FormatterInterface {

  /**
   * Content Type
   *
   * @var string
   */
  protected $contentType = 'application/json; charset=utf-8';

  /**
   * {@inheritdoc}
   */
  public function prepare(array $data) {
    // If we're returning an error then set the content type to
    // 'application/problem+json; charset=utf-8'.
    if (!empty($data['status']) && floor($data['status'] / 100) != 2) {
      $this->contentType = 'application/problem+json; charset=utf-8';
      return $data;
    }

    $output = array('data' => array());
    foreach ($data as $row) {
      foreach ($row as $public_field_name => $resource_field) {
        /* @var ResourceFieldInterface $resource_field */
        $value = $resource_field->value();
        $value = $resource_field->executeProcessCallbacks($value);
        $output['data'][$public_field_name] = $value;
      }
    }

    if ($resource = $this->getResource()) {
      $request = $resource->getRequest();
      $data_provider = $resource->getDataProvider();
      if ($request->isListRequest($resource->getPath())) {
        // Get the total number of items for the current request without
        // pagination.
        $output['count'] = $data_provider->count();
      }
      if (method_exists($resource, 'additionalHateoas')) {
        $output = array_merge($output, $resource->additionalHateoas($output));
      }

      // Add HATEOAS to the output.
      $this->addHateoas($output);
    }

    return $output;
  }

  /**
   * Add HATEOAS links to list of item.
   *
   * @param $data
   *   The data array after initial massaging.
   */
  protected function addHateoas(array &$data) {
    if (!$resource = $this->getResource()) {
      return;
    }
    $request = $resource->getRequest();

    // Get self link.
    $data['self'] = array(
      'title' => 'Self',
      'href' => $resource->versionedUrl($resource->getPath()),
    );

    $input = $request->getParsedInput();
    $page = !empty($input['page']) ? $input['page'] : 1;

    if ($page > 1) {
      $query = array(
        'page' => $page - 1,
      ) + $input;
      $data['previous'] = array(
        'title' => 'Previous',
        'href' => $resource->versionedUrl('', array('query' => $query), TRUE),
      );
    }

    // We know that there are more pages if the total count is bigger than the
    // number of items of the current request plus the number of items in
    // previous pages.
    $items_per_page = $resource->getDataProvider()->getRange();
    $previous_items = ($page - 1) * $items_per_page;
    if (isset($data['count']) && $data['count'] > count($data['data']) + $previous_items) {
      $query = array(
        'page' => $page + 1,
      ) + $input;
      $data['next'] = array(
        'title' => 'Next',
        'href' => $resource->versionedUrl('', array('query' => $query), TRUE),
      );
    }

  }

  /**
   * {@inheritdoc}
   */
  public function render(array $structured_data) {
    return drupal_json_encode($structured_data);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentTypeHeader() {
    return $this->contentType;
  }
}

