<?PHP
	require_once("pandaApi.php");
	require_once("app.php");

	session_start(); //In this example we will use sessions to store access tokens

	/*
		Some customers has multiple shops, so session handling must be per shop. 
		Therefor it can be a good idea to append "?shop=example.pandacommerce.net" on all internal links as we will do in this example
	*/

	if (isset($_GET['shop'])) {
		$api = new pandaApi($_GET['shop']);

		$unique_session_key = md5(PANDA_API_KEY.$_GET['shop']); //If loading saved access token from db, make sure to validate request

		if (array_key_exists($unique_session_key, $_SESSION)) {

			$access_token = $_SESSION[$unique_session_key];

			$api->setAccessToken($access_token); 

			$myApp = new pandaApp($_GET['shop'], $api);
			$myApp->render();
			exit;
		}
		elseif ((isset($_GET['code'])) && ($api->isValidRequest($_GET))) {
			try {
				$access_token = $api->getAccessTokenFromCode($_GET['code']);
			} catch(Exception $e) { 
				echo "failed to receive access token";
				exit;
			}

			if ($access_token) {
				$_SESSION[$unique_session_key] = $access_token;

				$api->setAccessToken($access_token);

				$myApp = new pandaApp($_GET['shop'], $api); 
				$myApp->setup();

				redirectTo($api->getAppUrl());
			}
		} 
		ELSE {
			redirectTo($api->getAuthorizeUrl());
		}
	}

	function redirectTo($url) {
		if ($_GET['embed']) {
			echo "<div style=\"text-align:center;\"><img src=\"//cdn.pandacommerce.net/static/global/load-big.gif\"></div><script>top.location.href=".json_encode($url).";</script>";
		}
		ELSE {
			header('Location: '.$url);
		}
		exit;
	}

	/*
		Show install form if no other action is taken
	*/
?>
<form method="GET" action="<?PHP echo $_SERVER['PHP_SELF']; ?>">
	<label for="shop">
		<strong>Install app to shop</strong> 
		<em>(enter it exactly like this: myshop.pandacommerce.net)</em> 
	</label> 
	<p> 
		<input id="shop" name="shop" size="45" type="text" placeholder="myshop.pandacommerce.net" /> 
		<input name="commit" type="submit" value="Install" /> 
	</p> 
</form>