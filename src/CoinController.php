<?php

namespace Coin;

use GuzzleHttp\Client;
use Twig_Loader_Filesystem;
use Twig_Environment;

class CoinController {

	protected $client;
	protected $env;
	protected $cli_command;
	protected $balances_dir;
	protected $twig;
	protected $recorder;
	protected $coin;
	protected $test = FALSE;

	protected function __construct() {
		if ( ! defined( 'CRON' ) ) {
			$headers = getallheaders();
			if ( empty( $headers['X-Set-Balance'] ) ) {
				return $this->sendUnauthorized();
			}
		}

		$this->client       = new Client();
		$this->cli_command  = getenv( 'CLI_COMMAND' );
		$this->balances_dir = getenv( 'BALANCE_DIR' );
		$this->recorder     = getenv( 'RECORDER' );
		$this->coin         = getenv( 'COIN_NAME' );
		$this->test         = getenv( 'TEST' );

		$loader     = new Twig_Loader_Filesystem( __DIR__ . '/../templates' );
		$this->twig = new Twig_Environment( $loader, [ 'debug' => TRUE ] );
	}

	/**
	 * Send 401 error headers with data.
	 *
	 * @param array $data
	 */
	protected function sendUnauthorized( $data = [ 'error' => 'Unauthorized' ] ) {
		header( "HTTP/1.1 401 Unauthorized" );
		header( 'Content-Type: application/json' );
		echo json_encode( $data );
		exit();
	}

	/**
	 * Send 500 error headers with title and data.
	 *
	 * @param array $data
	 * @param string $error
	 */
	protected function sendError( $data = [], $error = 'Script Generated Error' ) {
		//header( "HTTP/1.1 500 " . $error );
		header( 'Content-Type: application/json' );
		echo json_encode( $data );
		exit();
	}

	/**
	 * Render a Twig template.
	 *
	 * @param $template
	 * @param array $data
	 *
	 * @throws \Throwable
	 */
	protected function render( $template, $data = [] ) {
		echo $this->twig->render( $template . '.twig', $data );
		exit();
	}

	/**
	 * Get a random float.
	 *
	 * @param int $st_num
	 * @param int $end_num
	 * @param int $mul
	 *
	 * @return bool|float|int
	 */
	protected static function randFloat( $st_num = 0, $end_num = 1, $mul = 1000000 ) {
		if ( $st_num > $end_num ) {
			return FALSE;
		}

		return mt_rand( $st_num * $mul, $end_num * $mul ) / $mul;
	}

	/**
	 * Send JSON output with proper headers.
	 *
	 * @param $data
	 */
	protected function sendJson( $data ) {
		header( 'Content-Type: application/json' );
		echo json_encode( $data );
		exit();
	}
}