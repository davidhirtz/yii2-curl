<?php
/**
 * @author David Hirtz <hello@davidhirtz.com>
 * @copyright Copyright (c) 2016 David Hirtz
 * @version 1.0.0
 */

namespace davidhirtz\yii2\curl;

use Yii;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * Class Curl.
 * @package davidhirtz\yii2\curl
 */
class Curl
{
	const ERROR_TIMEOUT='timeout';

	/**
	 * @var string
	 * Holds response data right after sending a request.
	 */
	public $response=null;

	/**
	 * @var integer HTTP status code
	 * This value will hold the HTTP status Code. False if request was not successful.
	 */
	public $responseCode=null;

	/**
	 * @var integer cURL error code
	 * This value will hold the cURL error code. False if request was successful.
	 */
	public $errorCode=null;

	/**
	 * @var array HTTP-Status Code
	 * Custom options holder
	 */
	private $_options=array();

	/**
	 * @var resource
	 * Holds cURL-Handler
	 */
	private $_curl=null;

	/**
	 * @var array default curl options
	 * Default curl options
	 */
	private $_defaultOptions=array(
		CURLOPT_TIMEOUT=>30,
		CURLOPT_CONNECTTIMEOUT=>30,
		CURLOPT_RETURNTRANSFER=>true,
		CURLOPT_FAILONERROR=>true,
		CURLOPT_HEADER=>false,
	);

	/**
	 * Start performing GET-HTTP-Request.
	 *
	 * @param string $url
	 * @param array $params
	 * @param boolean $json
	 * @return mixed
	 */
	public function get($url, $params=array(), $json=false)
	{
		$url=rtrim($url, '&').(strpos($url, '?')===false ? '?' : '&').http_build_query($params);
		return $this->request('GET', $url, $json);
	}

	/**
	 * Start performing HEAD-HTTP-Request.
	 *
	 * @param string $url
	 * @return mixed
	 */
	public function head($url)
	{
		return $this->request('HEAD', $url);
	}

	/**
	 * Start performing POST-HTTP-Request.
	 *
	 * @param string $url
	 * @param array $params
	 * @param boolean $json
	 * @return mixed
	 */
	public function post($url, $params=array(), $json=false)
	{
		$this->setOption(CURLOPT_POSTFIELDS, http_build_query($params));
		return $this->request('POST', $url, $json);
	}

	/**
	 * Start performing PUT-HTTP-Request.
	 *
	 * @param string $url
	 * @param string $file
	 * @param boolean $json
	 * @return mixed
	 */
	public function put($url, $file=null, $json=false)
	{
		if($file)
		{
			$file=fopen($file, 'r');
			$this->setOption(CURLOPT_INFILE, $file);
		}

		return $this->request('PUT', $url, $json);
	}

	/**
	 * Start performing DELETE-HTTP-Request.
	 *
	 * @param string $url
	 * @param boolean $json
	 * @return mixed
	 */
	public function delete($url, $json=false)
	{
		return $this->request('DELETE', $url, $json);
	}

	/**
	 * Performs HTTP request
	 *
	 * @param string $method
	 * @param string $url
	 * @param boolean $json
	 * @return mixed
	 */
	private function request($method, $url, $json=false)
	{
		/**
		 * Set request type and writer function.
		 */
		$this->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($method));
		$profile=$method.' '.$url.'#'.md5(serialize($this->getOption(CURLOPT_POSTFIELDS)));

		if($method==='HEAD')
		{
			$this->setOption(CURLOPT_NOBODY, true);
			$this->unsetOption(CURLOPT_WRITEFUNCTION);
		}

		/**
		 * Setup error reporting and profiling.
		 */
		Yii::trace('Start sending cURL-Request: '.$url.'\n', __METHOD__);
		Yii::beginProfile($profile, __METHOD__);

		/**
		 * Execute curl request.
		 */
		$this->_curl=curl_init($url);
		curl_setopt_array($this->_curl, $this->getOptions());
		$body=curl_exec($this->_curl);

		$this->errorCode=curl_errno($this->_curl) ?: false;

		if($this->errorCode)
		{
			switch($this->errorCode)
			{
				case 7:
				case 28:
					$this->responseCode=self::ERROR_TIMEOUT;
					return false;
					break;

				default:
					throw new Exception('cURL request failed: '.curl_error($this->_curl), $this->errorCode);
					break;
			}
		}

		$this->responseCode=curl_getinfo($this->_curl, CURLINFO_HTTP_CODE);
		$this->response=$body;

		Yii::endProfile($profile, __METHOD__);

		/**
		 * Check responseCode and return data/status.
		 */
		if($this->getOption(CURLOPT_CUSTOMREQUEST)==='HEAD')
		{
			return true;
		}

		if($json)
		{
			$this->response=Json::decode($this->response);
		}

		return $this->response;
	}

	/**
	 * Get curl info.
	 * @link http://php.net/manual/de/function.curl-getinfo.php
	 *
	 * @param int $opt
	 * @return mixed
	 */
	public function getInfo($opt=null)
	{
		if($this->_curl!==null && $opt===null)
		{
			return curl_getinfo($this->_curl);
		}

		if($this->_curl!==null && $opt!==null)
		{
			return curl_getinfo($this->_curl, $opt);
		}

		return [];
	}

	/**
	 * Return a single option
	 *
	 * @param string|integer $key
	 * @return mixed|boolean
	 */
	public function getOption($key)
	{
		return ArrayHelper::getValue($this->getOptions(), $key, false);
	}

	/**
	 * Return merged curl options and keep keys.
	 * @return array
	 */
	public function getOptions()
	{
		return $this->_options+$this->_defaultOptions;
	}

	/**
	 * Set option.
	 *
	 * @param string $key
	 * @param mixed $value
	 *
	 * @return $this
	 */
	public function setOption($key, $value)
	{
		if(in_array($key, $this->_defaultOptions) && $key!==CURLOPT_WRITEFUNCTION)
		{
			$this->_defaultOptions[$key]=$value;
		}
		else
		{
			$this->_options[$key]=$value;
		}

		return $this;
	}

	/*
	 * Sets cookie option.
	 *
	 * @param mixed $params
	 * @return $this
	 */
	public function setCookies($params)
	{
		return $this->setOption(CURLOPT_COOKIE, $params);
	}

	/**
	 * Sets http headers.
	 *
	 * @param mixed $headers
	 * @return $this
	 */
	public function setHeaders($headers)
	{
		return $this->setOption(CURLOPT_HTTPHEADER, (array)$headers);
	}

	/**
	 * Sets login option.
	 *
	 * @param string $username
	 * @param string $password
	 * @return $this
	 */
	public function setHttpLogin($username, $password)
	{
		return $this->setOption(CURLOPT_USERPWD, $username.':'.$password);
	}

	/**
	 * Sets proxy options.
	 *
	 * @param string $url
	 * @param integer $port
	 * @return $this
	 */
	public function setProxy($url, $port)
	{
		$this->setOption(CURLOPT_HTTPPROXYTUNNEL, true);
		$this->setOption(CURLOPT_PROXY, $url.':'.$port);

		return $this;
	}

	/**
	 * Sets proxy login option.
	 *
	 * @param string $username
	 * @param string $password
	 * @return $this
	 */
	public function setProxyLogin($username='', $password='')
	{
		return $this->setOption(CURLOPT_PROXYUSERPWD, $username.':'.$password);
	}

	/**
	 * Unset a single curl option.
	 *
	 * @param string $key
	 * @return $this
	 */
	public function unsetOption($key)
	{
		ArrayHelper::remove($this->_options, $key);
		return $this;
	}

	/**
	 * Unset all curl option, excluding default options.
	 * @return $this
	 */
	public function unsetOptions()
	{
		$this->_options=array();
		return $this;
	}

	/**
	 * Total reset of options, responses, etc.
	 *
	 * @return $this
	 */
	public function reset()
	{
		if($this->_curl!==null)
		{
			curl_close($this->_curl);
		}

		$this->_curl=null;
		$this->response=null;
		$this->responseCode=null;
		$this->_options=array();

		return $this;
	}
}