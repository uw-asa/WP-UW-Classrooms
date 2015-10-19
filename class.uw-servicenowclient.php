<?php

class UW_ServiceNowClient {

  public $options;

  public function __construct($options=array())
  {
    $default_options = array(
			     'base_url' => null,
			     'username' => null,
			     'password' => null,
			     );

    $this->options = array_merge($default_options, $options);
  }    

  public function execute($pagename, $params=array(), $headers=array(), $method='GET', $content='')
  {
    $url = $this->options['base_url'] . '/' . $pagename . '.do?JSONv2&' . http_build_query($params);

    $cred = sprintf('Authorization: Basic %s',
		    base64_encode( $this->options['username'] . ':' . $this->options['password'] ) );

    $headers = array_merge(array($cred), $headers);

    $opts = array(
		  'http' => array(
				  'method' => $method,
				  'header' => implode("\r\n", $headers),
				  ) 
		  );

    $ctx = stream_context_create($opts);

    return file_get_contents($url, false, $ctx);
  }

  public function get_records($table, $query, $displayvalue='all')
  {
    $pagename = $table . '_list';

    $params = array(
		    'sysparm_action' => 'getRecords',
		    'sysparm_query' => $query,
		    'displayvalue' => $displayvalue,
		    );

    return $this->execute($pagename, $params);
  }

}
