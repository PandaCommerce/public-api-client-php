<?PHP
	class pandaApp {
		public function __construct($shop_host, pandaApi $api) {
			$this->shop = $shop_host;
			$this->api = $api;
		}
		public function setup() {
			//Called after first retrieving access token
		}
		public function render() { 
			//This should output html
			$result = $this->api->call('GET', '/admin/api/shop.json', array());

			echo "Hello! Your store has ".$result['products_count']." products!";
			exit;
		}
	}
?>