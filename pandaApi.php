<?PHP
	class pandaApi {
		const PANDA_API_KEY = 'YOUR_API_KEY';
		const PANDA_API_SECRET = 'YOUR_API_SECRET';
		const PANDA_API_SHARED_SECRET = 'YOUR_SHARED_SECRET';
		const PANDA_API_SCOPE = 'write_orders,read_products';

		public function __construct($shop_host, $token = null) {
			$this->shop_host = $shop_host;
			$this->api_key = self::PANDA_API_KEY;
			$this->api_secret = self::PANDA_API_SECRET;
			$this->api_shared_secret = self::PANDA_API_SHARED_SECRET;
			$this->api_scope = self::PANDA_API_SCOPE;
			$this->setAccessToken($token);
		}
		public function isValidRequest($query_params) {
			$seconds_in_a_day = 24 * 60 * 60;
			$older_than_a_day = $query_params['timestamp'] < (time() - $seconds_in_a_day);
			if ($older_than_a_day) return false;

			if ($this->shop_host != $query_params['shop']) return false;

			$signature = $query_params['signature'];
			unset($query_params['signature']);

			foreach ($query_params as $key=>$val) $params[] = "$key=$val";
			sort($params);

			return (md5($this->api_shared_secret.implode('', $params)) === $signature);
		}
		public function validateHmac($str, $hmac_signature) {
			$calculated_hmac = base64_encode(hash_hmac('sha256', $str, $this->api_shared_secret, true));

			if ($calculated_hmac == $hmac_signature) {
				return true;
			}
			return false;
		}
		public function call($method, $endpoint, $data = array()) {
			if (empty($this->access_token)) {
				throw new AuthenticationException('Access token not set');
			}
			$headers = array('API-ACCESS-TOKEN: '.$this->access_token);
			$url = "https://".$this->shop_host.$endpoint;

			return $this->makeCurlRequest($method, $url, $data, $headers);
		}
		private function makeCurlRequest($method, $url, $data, $headers = array()) {
			$method = strtoupper($method);
			if (in_array($method, array('GET','DELETE')) && !empty($data)) {
				if (is_array($data)) $data = http_build_query($data);
				$url .= "?".ltrim($data, "?");
			}

			$ch = curl_init($url); 
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

			if (in_array($method, array('POST','PUT')) && !empty($data)) {
				if (is_array($data)) {
					$headers[] = 'Content-Type: application/json; charset=utf-8';
					curl_setopt ($ch, CURLOPT_POSTFIELDS, json_encode($data));
				}
				ELSE {
					curl_setopt ($ch, CURLOPT_POSTFIELDS, ltrim($data, "?"));
				}
			}

			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$result = curl_exec($ch);
			if (!$result) {
				throw new CurlException();
			}

			$response = json_decode($result, true);
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if (($http_status == 403) || ($http_status == 401)) {
				if (is_array($response['errors']['scope'])) {
					throw new AuthenticationException($response['errors']['scope'][0]);
				}
				throw new AuthenticationException('Authentication Error');
			}

			if ((isset($response['errors'])) || ($http_status >= 400)) {
				throw new ApiException(compact('method', 'endpoint', 'data', 'http_status', 'response'));
			}
			ELSE {
				return $response;
			}
		}
		public function getAccessTokenFromCode($code) {
			$url = "https://".$this->shop_host."/admin/oauth/token.json"; 
			$data = "client_id=".$this->api_key."&grant_type=authorization_code&client_secret=".$this->api_secret."&code=".$code; 

			$response = $this->makeCurlRequest('POST', $url, $data);

			if (isset($response['access_token'])) {
				return $response['access_token'];
			}
			ELSE {
				throw new AuthenticationException('Invalid Code');
			}
		}
		public function getAccessToken() {
			return $this->access_token;
		}
		public function setAccessToken($token) {
			$this->access_token = $token;
		}
		public function getAppUrl() {
			return "https://".$this->shop_host."/admin/oauth/run/".$this->api_key;
		}
		public function getAuthorizeUrl($return_url = "") {
			$url = "https://".$this->shop_host."/admin/oauth/authorize?response_type=code&client_id=".$this->api_key."&scope=".urlencode(preg_replace('/(\s|,)+/ms', ' ', $this->api_scope));
			if ($return_url != '')
			{
				$url .= "&redirect_uri=" . urlencode($return_url);
			}
			return $url;
		}
	}
	class CurlException extends Exception {}
	class AuthenticationException extends Exception {}
	class ApiException extends Exception {
		public $info;
		public function __construct($info) {
			$this->info = $info;
			parent::__construct();
		}
		public function getInfo() {
			return $this->info;
		}
	}
?>