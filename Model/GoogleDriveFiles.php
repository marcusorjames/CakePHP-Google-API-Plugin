<?php
App::uses('GoogleApi', 'Google.Model');
class GoogleDriveFiles extends GoogleApi {

	public $hasOne = array('Google.GoogleDriveFilesUpload');

	protected $_request = array(
		'method' => 'GET',
		'uri' => array(
			'scheme' => 'https',
			'host' => 'www.googleapis.com',
			'path' => '/drive/v2/files',
		)
	);

	/**
	 * https://developers.google.com/drive/v2/reference/files/insert
	 **/
	public function insert($file, $options = array()) {
		$request = array();
		$request['method'] = 'POST';
		$request['uri']['query'] = $options;
		$body = array(
			'title' => $file['name'],
			'mimeType' => $file['type']
		);
		$request['body'] = json_encode($body);
		$request['header']['Content-Type'] = 'application/json';
		return $this->GoogleDriveFilesUpload->insert(
			$file, $this->_request(null, $request),
			$options
		);
	}

	/**
	 * https://developers.google.com/drive/v2/reference/files/list
	 **/
	public function listItems($options = array()) {
		$request = array();
		if ($options) {
			$request['uri']['query'] = $options;
		}
		return $this->_request(null, $request);
	}
}