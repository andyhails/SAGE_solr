<?php

require_once __DIR__ . '/bootstrap.php';

use Symfony\Component\HttpFoundation\Response;

$field_map = [
  "accountLevel" => "",
  "address1" => "",
  "city" => "",
  "country" => "",
  "id" => "",
  "location" => "",
  "name" => "",
  "parentId" => "",
  "phoneNumber" => "",
  "postalcode" => "",
  "region" => "",
  "showGeneric" => "",
  "state" => "",
];



// Institutions search
$app->get('/', function() use($app) {
//  $query = new \SolrQuery();
//  $query->setQuery('index_id:sage_taxonomy_index');
//  $query->addFilterQuery('sm_field_institution_id:"' . $app->escape($institution_id) . '"');
//  $query->setStart(0);
//  $query->setRows(10);
//
//  /** @var SolrQueryResponse $query_response */
//  $query_response = $app['solr']->query($query);
//  $response = new \Symfony\Component\HttpFoundation\Response($query_response->getRawResponse());
//  $response->headers->set('Content-Type', 'xml');

  $response = new Response("Hello me!");
  return $response;

});

