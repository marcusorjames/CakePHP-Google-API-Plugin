<?php
/*
 * Retrieves latitude and longitude from Google using information in passed data array. 
 * Subsequently adds retruned coordinates to passed data array.
 */
class GoogleMapCoordinateBehavior extends ModelBehavior {
	
	public function setup(Model $model, $settings = array()) {
		$settings = (array) $settings;
		
		$settings = am(array(
			'latitudeField' => 'latitude',
			'longitudeField' => 'longitude',
			'addressField' => 'address',
			'postfix' => null
		), $settings);
		
		$this->settings[$model->alias] = $settings;
	}
	
	public function beforeSave(Model $model, $options = array()) {
				
		$address = array($this->settings[$model->alias]['postfix']);
		if (isset($this->settings[$model->alias]['addressField'])) {
			$address[] = str_replace(array("\r\n", "\r"), ', ', $model->data[$model->alias][$this->settings[$model->alias]['addressField']]);
			$address = array_reverse(array_filter($address));
		}
		$address = implode(', ', $address);
		
		// Retrieve location information through Google Geocode service
		
		$GoogleGeocoding = ClassRegistry::init('Google.GoogleGeocoding');
		
		$outputGeocode = $GoogleGeocoding->get($address);
		
		if (!empty($outputGeocode) && $outputGeocode['status'] === 'OK') {
			
			$latitude = $outputGeocode['results'][0]['geometry']['location']['lat'];
			$longitude = $outputGeocode['results'][0]['geometry']['location']['lng'];
		
		} else {
		
			$latitude = null;
			$longitude = null;
			
		}
		
		$model->data[$model->alias][$this->settings[$model->alias]['latitudeField']] = $latitude;
		$model->data[$model->alias][$this->settings[$model->alias]['longitudeField']] = $longitude;
		
		return true;
	}
}