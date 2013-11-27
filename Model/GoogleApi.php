<?php
App::uses('AppModel', 'Model');
App::uses('CakeSession', 'Model/Datasource');
App::uses('Hash', 'Utility');
App::uses('HttpSocket', 'Network/Http');
App::uses('Set', 'Utility');
class GoogleApi extends AppModel {

	public $useTable = false;
	
	protected $_config = array();

	protected $_request = array(
		'method' => 'GET',
		'uri' => array(
			'scheme' => 'https',
			'host' => 'www.googleapis.com',
		)
	);

	protected $_strategy = 'Google';

	public function __construct($id = false, $table = null, $ds = null) {
		parent::__construct($id, $table, $ds);
		
		// Read Google configuration from Opauth
		
		$this->_config = Configure::read('Opauth.Strategy.'.$this->_strategy);
		
		if (CakeSession::check($this->_strategy.'.auth')) {
			
			$auth = CakeSession::read($this->_strategy.'.auth');
		
			$this->_config['token'] = isset($auth['token']) ? $auth['token'] : null;
			$this->_config['refresh_token'] = isset($auth['refresh_token']) ? $auth['refresh_token'] : null;
			$this->_config['expires'] = isset($auth['expires']) ? $auth['expires'] : null;

		}
	}

	protected function _generateCacheKey() {
		$backtrace = debug_backtrace();
		$cacheKey = array();
		$cacheKey[] = $this->alias;
		if (!empty($backtrace[2]['function'])) {
			$cacheKey[] = $backtrace[2]['function'];
		}
		if ($backtrace[2]['args']) {
			$cacheKey[] = md5(serialize($backtrace[2]['args']));	
		}
		return implode('_', $cacheKey);
	}

	protected function _parseResponse($response) {
		$results = json_decode($response->body);
		if (is_object($results)) {
			$results = Set::reverse($results);
		}
		return $results;
	}

	protected function _request($path, $request = array()) {
		
		if (empty($this->_config['token']))
			return false;
	
		// preparing request
		$request = Hash::merge($this->_request, $request);
		$request['uri']['path'] .= $path;
		$request['header']['Authorization'] = sprintf('OAuth %s', $this->_config['token']);
		
		// Read cached GET results
		// Do not read cache if debug is more than 1
		if ($request['method'] == 'GET' && Configure::read('debug') >= 1) {
			$cacheKey = $this->_generateCacheKey();
			$results = Cache::read($cacheKey);
			if ($results !== false) {
				return $results;
			}
		}

		// createding http socket object for later use
		$HttpSocket = new HttpSocket();

		// checking access token expires time, using refresh token when needed
		$date = date('c', time());
		if(isset($this->_config['expires']) 
		&& isset($this->_config['refresh_token'])
		&& $date > $this->_config['expires']) {

			// getting new credentials
			$requestRefreshToken = array(
				'method' => 'POST',
				'uri' => array(
					'scheme' => 'https',
					'host' => 'accounts.google.com',
					'path' => '/o/oauth2/token',
				),
				'body' => sprintf(
					'client_id=%s&client_secret=%s&refresh_token=%s&grant_type=refresh_token',
					$this->_config['client_id'],
					$this->_config['client_secret'],
					$this->_config['refresh_token']
				),
				'header' => array(
					'Content-Type' => 'application/x-www-form-urlencoded'
				)
			);
			$response = $HttpSocket->request($requestRefreshToken);
			if ($response->code != 200) {
				if (Configure::read('debugApis')) {
					debug($requestRefreshToken);
					debug($response->body);
				}
				return false;
			}
			$results = $this->_parseResponse($response);
			$credentials = array(
				'token' => $results['access_token'],
				'expires' => date('c', time() + $results['expires_in'])
			);

			// saving new credentials
			
			$this->saveCredentials($credentials);

			// writing authorization token again (refreshed one)
			$request['header']['Authorization'] = sprintf('OAuth %s', $this->_config['token']);
		}
		
		
		// Build query compatible
		
		if (!empty($request['uri']['query']['q']) && is_array($request['uri']['query']['q'])) {
			
			$filterQuery = array();
			foreach ($request['uri']['query']['q'] as $filter => $filterValue) {
			
				if (is_array($filterValue)) {
				
					$filterValues = array();
					foreach ($filterValue as $filterValueOption) {

						$operator = "=";

						if (preg_match("/(<=|<|=|>|>=|contains|in)/i", $filterValueOption))
							$operator = "";
							
						if (preg_match("(title|fullText|mimeType)", $filter))
							$filterValueOption = "'".$filterValueOption."'";

						$filterValues[] = "$filter$operator$filterValueOption";
					}
					$filterQuery[] = implode(" and ", $filterValues);
					
				} else {
					if ($filterValue === true)
						$filterValue = 'true';
					if ($filterValue === false)
						$filterValue = 'false';
					$filterQuery[] = "$filter=$filterValue";	
				}
				
			}
			$filterQuery = implode(" and ", $filterQuery);
			
			$request['uri']['query']['q'] = $filterQuery;
		}
		
		// issuing request
		$response = $HttpSocket->request($request);

		// only valid response is going to be parsed
		if (substr($response->code, 0, 1) != 2) {
			if (Configure::read('debugApis')) {
				debug($request);
				debug($response->body);
			}
			return 0;
		}
		
		// parsing response
		$results = $this->_parseResponse($response);

		// cache and return results
		if ($request['method'] == 'GET') {
			Cache::write($cacheKey, $results);
		}
		return $results;
	}
	
	public function clearCredentials($credentials) {
		CakeSession::delete($this->_strategy.'.auth');
	}
	
	public function saveCredentials($credentials = array()) {
		
		$filterCredentials = function($item) {
			return in_array($item, array(
				'token',
				'expires',
				'refresh_token'
			));
		};
		$credentials = array_flip(array_filter(array_flip($credentials), $filterCredentials));
		
		CakeSession::write($this->_strategy.'.auth', $credentials);
	}
}
