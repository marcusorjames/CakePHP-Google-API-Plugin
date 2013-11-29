<?php
App::uses('GoogleMapsApi', 'Google.Model');
class GooglePlaces extends GoogleMapsApi {

	protected $_request = array(
		'method' => 'GET',
		'uri' => array(
			'scheme' => 'https',
			'host' => 'maps.googleapis.com',
			'path' => 'maps/api/place/nearbysearch/json',
		)
	);

	public function get($param, $searchType = 'nearby', $options = array()) {
		
		$request = array();
		
		if ($searchType == 'nearby')
			$request['uri']['path'] = 'maps/api/place/nearbysearch/json';
		else if ($searchType == 'text')
			$request['uri']['path'] = 'maps/api/place/textsearch/json';
		else if ($searchType == 'radar')
			$request['uri']['path'] = 'maps/api/place/radarsearch/json';
			
		
		$query = array(
			'address' => $address,
			'sensor' => 'false'
		);
		$query = array_merge($query, $options);
		
		$request['uri']['query'] = $query;
		
		return $this->_request(null, $request);
	}
}
