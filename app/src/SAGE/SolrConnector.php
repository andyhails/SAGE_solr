<?php

namespace SAGE;

/**
 * Class SolrConnector
 */
class SolrConnector {

  // Solr search path
  const PATH_SEARCH = 'select';

  // Solr search types.
  const SEARCH_TYPE_PRODUCT = 'product';

  /**
   * Solr's base url.
   *
   * @var string
   */
  private $baseUrl;

  /**
   * Search params to search on Solr.
   *
   * @var array
   */
  private $searchParams = array();

  /**
   * The type of search.
   *
   * @var string
   *  self::SEARCH_TYPE_*
   */
  private $searchType;

  /**
   * Whitelist of query params we allow to be passed to Solr.
   *
   * @var array
   */
  public static $searchParamsWhitelist = array(
    'q',
    'fq',
    'fl',
  );

  /**
   * Force query params we send to Solr.
   *
   * @var array
   */
  public static $searchAdditionalParams = array(
    'wt' => 'json',
    'json.nl' => 'map',
    'rows' => 3,
    'start' => 0,
    'defType' => 'edismax',
    'sort' => 'score desc',
    //'mm' => '-1',
  );

  /**
   * Product search params.
   *
   * @var array
   */
  public static $productSearchFieldParams = array(
    'qf' => array(
      'tm_field_affiliations:description^2.0',
      'tm_field_associated_contributors^1.0',
      'tm_field_courses:description^1.0',
      'twm_field_courses:name^1.0',
      'tm_field_custom_author_lastnames^10.0',
      'tm_field_custom_authors^10.0',
      'tm_field_custom_eb_authors^5.0',
      'tm_field_custom_lead_author^20.0',
      'tm_field_custom_website_description^1.5',
      'tm_field_disciplines:description^2.0',
      'twm_field_disciplines:name^1.0',
      'tm_field_edition^2.0',
      'tm_field_imprint^1.0',
      'tm_field_issn^1.0',
      'tm_field_keywords:name^10.0',
      'part_field_partial_authors^5.0',
      'part_field_partial_lead_author^15.0',
      'part_field_partial_title^15.0',
      'tm_field_product:field_isbn^1.0',
      'tm_field_product:field_isbn_13^1.0',
      'tm_field_series:description^2.0',
      'twm_field_series:name^1.0',
      'tm_field_subtitle^10.0',
      'tm_field_year_copyright^1.0',
      'tm_sage_apachesolr_path_alias^1.0',
      'tm_title^10.0',
    ),
    'pf' => array(
      'tm_field_custom_authors^20.0',
      'part_field_partial_authors^30.0',
      'tm_field_custom_lead_author^15.0',
      'tm_title^25.0',
      'part_field_partial_title^15.0',
      'tm_field_subtitle^8.0',
      'im_field_keywords:name^10.0',
      'im_field_disciplines:description^2.0',
      'tm_field_custom_website_description:value^1.5',
      'field_series:description^2.0',
      'field_affiliations:description^2.0',
      'im_field_courses:description^5.0',
      'field_courses_partial^20.0',
    ),
    'bq' => array(
      'bs_is_edge:true^20'
    ),
  );

  /**
   * @param string $host
   *  Solr host.
   * @param string $port
   *  Solr port.
   * @param string $path
   *  Solr path.
   */
  public function __construct($host, $port, $path) {
    $this->setBasePath($host, $port, $path);
  }

  /**
   * Set the solr base path.
   *
   * @param string $host
   *  Solr host.
   * @param string $port
   *  Solr port.
   * @param string $path
   *  Solr path.
   */
  private function setBasePath($host, $port, $path) {
    $this->baseUrl = $host . ':' . $port . '/' . $path;
  }

  /**
   * Performs a search from a solr formatted query string.
   *
   * @param string $queryString
   * @return array
   */
  public function search($queryString) {
    $this->searchParams = $this->transformQueryStringToQueryParams($queryString);
    return $this->getResults();
  }

  /**
   * Transform the query string to search parameter array, with checks applied to
   * the keys and values provides in the string.
   *
   * @param $queryString
   * @return array
   */
  private function transformQueryStringToQueryParams($queryString) {
    $filtered_params = array();

    foreach (self::queryStringToArray($queryString) as $param) {
      list($key, $value) = explode('=', $param, 2);
      $key = self::decodeAndCheckPlain($key);
      $value = self::decodeAndCheckPlain($value);
      if ($this->isQueryParamAllowed($key)) {
        $filtered_params[$key][] = $value;
      }
      if ($this->isQueryTypeParam($key)) {
        $this->searchType = $value;
      }
    }

    return array_merge($filtered_params, self::$searchAdditionalParams, $this->getFieldParams());
  }

  /**
   * Get the field params per search type.
   *
   * @return array
   */
  private function getFieldParams() {
    switch ($this->searchType) {
      case self::SEARCH_TYPE_PRODUCT:
        return self::$productSearchFieldParams;

      default:
        return array();
    }
  }

  /**
   * Simple transformation of the query string to an array.
   *
   * We can not use parse_str as the Solr query string has multiple values for
   * the same key.
   *
   * @param string $queryString
   * @return array
   */
  private static function queryStringToArray($queryString) {
    return explode('&', $queryString);
  }

  /**
   * Decode URL-encoded strings, and encode special characters.
   *
   * @param string $string
   * @return string
   */
  private static function decodeAndCheckPlain($string) {
    return htmlspecialchars(rawurldecode($string), ENT_NOQUOTES, 'UTF-8');
  }

  /**
   * Check is a given parameter is allowed in the Solr query.
   *
   * @param string $key
   *  Param key
   * @return bool
   */
  private function isQueryParamAllowed($key) {
    return in_array($key, self::$searchParamsWhitelist);
  }

  /**
   * Check if this param is the query type param.
   *
   * @param string $key
   *
   * @return bool
   */
  private function isQueryTypeParam($key) {
    return $key == 'queryType';
  }

  /**
   * Get the results of the query.
   *
   * @return array
   */
  private function getResults() {
    $solrQueryURL = $this->getSolrQueryURL(self::PATH_SEARCH, $this->searchParams);
    $results =  json_decode(file_get_contents($solrQueryURL));
    return isset($results->response->docs) ? json_encode($results->response->docs) : json_encode(array());
  }

  /**
   * Get the URL to call.
   *
   * @param string $path
   *  Path to call.
   * @param array $queryParams
   *  Query params.
   *
   * @return string
   *  URL to call for the query.
   */
  private function getSolrQueryURL($path, array $queryParams = array()) {
    return $this->baseUrl . '/' . $path . '?' . $this->getQueryParamString($queryParams);
  }

  /**
   * Generates an URL-encoded query string.
   *
   * Works like PHP's http_build_query() but uses rawurlencode() and no [] for
   * repeated params, to be compatible with what Solr is expecting.
   *
   * @see SearchApiSolrConnection::httpBuildQuery()
   *
   * @param array $query
   *  Array of query params.
   * @param string $parent
   *  Parent param key used when building child query strings.
   *
   * @return string
   *  Query string for use in a URL.
   */
  private function getQueryParamString(array $query, $parent = '') {
    $params = array();

    foreach ($query as $key => $value) {
      $key = ($parent ? $parent : rawurlencode($key));

      // Recurse into children.
      if (is_array($value)) {
        if ($value = $this->getQueryParamString($value, $key)) {
          $params[] = $value;
        }
      }
      else {
        $params[] = $key . '=' . rawurlencode($value);
      }
    }

    return implode('&', $params);
  }
}