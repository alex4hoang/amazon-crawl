<?php

class amazon {

	public function __construct() {
		// Initial login page, always the referrer
		$this->url = 'https://www.amazon.com/ap/signin?_encoding=UTF8&openid.assoc_handle=usflex&op' .
					'enid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select' .
					'&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_selec' .
					't&openid.mode=checkid_setup&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2' .
					'.0&openid.ns.pape=http%3A%2F%2Fspecs.openid.net%2Fextensions%2Fpape%2F1.0&open' .
					'id.pape.max_auth_age=0&openid.return_to=https%3A%2F%2Fwww.amazon.com%2Fgp%2Fyo' .
					'ur-account%2Forder-history%3Fie%3DUTF8%26ref_%3Dya_orders_ap&pageId=webcs-your' .
					'order&showRmrMe=1&action=sign-in';

		$this->config = array();
		$this->config_file = '/config.cfg';
		if( file_exists( $this->config_file ) ) {
			$this->config = json_decode( file_get_contents( $this->config_file ), true );
		}

		if( isset( $this->config[ 'amazon' ] ) ) {
			$this->_AMZN_EMAIL = $config[ 'amazon' ][ 'email' ];
			$this->_AMZN_PASSWORD = $config[ 'amazon' ][ 'password' ];
		}
	}

	/**
	 * Get orders from My Orders page
	 * @return [str] raw data
	 */
	public function get_orders() {
		if( !$this->_AMZN_EMAIL || !$this->_AMZN_PASSWORD ) {
			echo 'Amazon email and password missing';
			return false;
		}
		// Go to initial login page which redirects to correct sign in page, set some cookies
		$ch  = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->url );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, '/var/www/html/amznreview/temp/cookie.txt' );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, '/var/www/html/amznreview/temp/cookie.txt' );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:35.0) Gecko/20100101 Firefox/35.0' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_HEADER, 1 );
		//curl_setopt( $ch, CURLOPT_VERBOSE, true );
		curl_setopt( $ch, CURLOPT_STDERR, fopen( 'php://stdout', 'w' ) );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );

		$page = curl_exec( $ch );

		// Find the actual login form
		if( !preg_match( '/<form name="signIn".*?<\/form>/is', $page, $form ) ) {
			die( 'Failed to find log in form!' );
		}

		$form = $form[ 0 ];

		// find the action of the login form
		if ( !preg_match( '/action=(?:\'|")?([^\s\'">]+)/i', $form, $action ) ) {
			die( 'Failed to find login form url' );
		}

		$url2 = $action[ 1 ]; // this is our new post url

		// Find all hidden fields which we need to send with our login, this includes security tokens
		$count = preg_match_all( '/<input type="hidden"\s*name="([^"]*)"\s*value="([^"]*)"/i', $form, $hiddenFields );

		$postFields = array();

		// Turn the hidden fields into an array
		for( $i = 0; $i < $count; ++$i ) {
			$postFields[ $hiddenFields[ 1 ][ $i ] ] = $hiddenFields[ 2 ][ $i ];
		}

		// Add our login values
		$postFields[ 'email' ] = $this->_AMZN_EMAIL;
		$postFields[ 'password' ] = $this->_AMZN_PASSWORD;

		$post = '';

		// Convert to string, this won't work as an array, form will not accept multipart/form-data, only application/x-www-form-urlencoded
		foreach( $postFields as $key => $value ) {
			$post .= $key . '=' . urlencode( $value ) . '&';
		}

		$post = substr( $post, 0, -1 );

		// Set additional curl options using our previous options
		curl_setopt( $ch, CURLOPT_URL, $url2 );
		curl_setopt( $ch, CURLOPT_REFERER, $this->url );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

		$response = curl_exec( $ch ); // make request
		curl_close( $ch );
		return $response;
	}

	/**
	 * Get all order links
	 * @param  [str]   $response [raw page data from crawl]
	 * @return [array] array( [link1], [link2], etc );
	 */
	public function get_all_order_links( $response ) {
		if( !$response ) return false;
		$r = array();
		preg_match_all( '/href="\/gp\/product\/(.*?)">/si', $response, $matches );
		// foreach( array_unique( $matches[ 1 ] ) as $match ) {
		foreach( $matches[ 1 ] as $match ) {
			$id = amazon::get_product_id( $match );
			$r[ $id ] = 'http://www.amazon.com/gp/product/' . $match;
		}
		return $r;
	}

	/**
	 * Get product id from amazon
	 * @param  [str] $url
	 * @return [str] B00MHM0Z3Q
	 */
	public function get_product_id( $url ) {
		if( !$url ) return false;
		return explode( "/", $url, 2 )[ 0 ];
	}

	/**
	 * Get product page
	 * @param  [str] $url [URL of product page]
	 * @return [str] raw data of product page
	 */
	public function get_product_page( $url ) {
		if( !$url ) return false;
		$ch  = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, '/var/www/html/amznreview/ctemp/cookie.txt' );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, '/var/www/html/amznreview/ctemp/cookie.txt' );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:35.0) Gecko/20100101 Firefox/35.0' );
		curl_setopt( $ch, CURLOPT_REFERER, $this->url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$page = curl_exec( $ch );
		curl_close( $ch );
		return $page;
	}

	/**
	 * Get the product name
	 * @param  [str] $page [raw data of product page]
	 * @return [str]
	 */
	public function get_product_name( $page ) {
		if( !$page ) return false;
		preg_match( '/<span id="productTitle" class="a-size-large">(.*?)<\/span>/si', $page, $product_name );
		return $product_name[ 1 ];
	}

	/**
	 * Get div of reviews
	 * @param  [str] $page [raw data of product page]
	 * @return [str]
	 */
	public function get_div_reviews( $page ) {
		if( !$page ) return false;
		preg_match( '/<div id="revMHRL" class="a-section">(.*?)<div id="revF" class="a-section">/si', $page, $div );
		return $div[ 0 ];
	}

	/**
	 * Get all reviews from raw div string
	 * @param  [str]   $div [raw div string]
	 * @return [array] array( str, str, str, etc );
	 */
	public function get_all_reviews( $div ) {
		if( !$div ) return false;
		preg_match_all( '/<div class="a-section">(.*?)<\/div>/si', $div, $all_reviews );
		return $all_reviews[ 1 ];
	}

	/**
	 * Simple string cleaner
	 * @param  [str] $str
	 * @return [str]
	 */
	public function clean_string( $str ) {
		if( !$str ) return false;
		$str = str_replace( '<br>', '', $str );
		$str = str_replace( '  ', ' ', $str );
		$str = trim( $str );
		$stt = ltrim( rtrim( $str ) );
		return $str;
	}

	/**
	 * Get string between two elements
	 * @param  [str] $string [full string to search]
	 * @param  [str] $start
	 * @param  [str] $end
	 * @return [str] between $start and #end
	 */
	public function get_string_between( $string, $start, $end ){
		if( !$string || !$start || !$end ) return false;
		$string = " ".$string;
		$ini = strpos( $string, $start );
		if ( $ini == 0 ) return "";
		$ini += strlen( $start );
		$len = strpos( $string, $end, $ini ) - $ini;
		return substr( $string, $ini, $len );
	}

}
