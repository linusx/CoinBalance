<?php

namespace Coin;

use GuzzleHttp\Exception\GuzzleException;
use DirectoryIterator;

class Coin extends CoinController {

	protected function __construct() {
		parent::__construct();

		if ( defined( 'CRON' ) ) {
			return $this->runCron();
		} elseif ( defined( 'RECORD' ) ) {
			return $this->record();
		} elseif ( defined('LOGIN') ) {
			return $this->login();
		} elseif ( defined('LOGOUT') ) {
			return $this->logout();
		} else {
			return $this->showBalance();
		}
	}

	public function logout() {
		unset($_SESSION["authenticated"]);
		Header('Location: ' . getenv('APP_URL'));
	}

	public function login() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
			$password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
			if(!empty($username) && !empty($password)) {
				if($username == getenv('COIN_USERNAME') && $password == getenv('COIN_PASSWORD')) {
					$_SESSION["authenticated"] = 'true';
					$this->sendJson( [ 'success' => TRUE ] );
				} else {
					$this->sendUnauthorized( [ 'error' => 'Authentication failed.' ] );
				}

			} else {
				$this->sendUnauthorized( [ 'error' => 'Wrong Credentials.' ] );
			}
		}

		$this->sendUnauthorized( [ 'error' => 'Missing data.' ] );
	}

	public function record() {
		if ( ! is_dir( $this->balances_dir ) ) {
			$this->sendError( [ 'error' => $this->balances_dir . ' does not exist.' ] );
		}

		$coin = filter_input(INPUT_POST, 'coin', FILTER_SANITIZE_STRING);
		$balance = filter_input(INPUT_POST, 'balance', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

		if (empty($coin)) {
			$this->sendError( [ 'error' => 'No coin given.' ] );
		}

		$fp = fopen($this->balances_dir . $coin . '.coin', 'w');
		fwrite($fp, $balance);
		fclose($fp);

		$this->sendJson(['coin' => $coin, 'balance' => $balance]);
	}

	/**
	 * Render a template to show the current balances.
	 */
	public function showBalance() {
		if ( getenv( 'PASSWORD_PROTECT' ) && (empty($_SESSION["authenticated"]) || $_SESSION["authenticated"] != 'true')) {
			$this->render( 'login');
		}

		$balances = $this->getBalances();

		$this->render( 'index', [ 'coins' => $balances ] );
	}

	/**
	 * Get coin balances from the local directory.
	 *
	 * @return array|void
	 */
	private function getBalances() {
		$balances = [];

		if ( ! is_dir( $this->balances_dir ) ) {
			$this->sendError( [ 'error' => $this->balances_dir . ' does not exist.' ] );
		}

		$dir = new DirectoryIterator( $this->balances_dir );
		if ( empty( $dir ) ) {
			return $balances;
		}

		$i = 0;
		foreach ( $dir as $file_info ) {
			if ( ! $file_info->isDot() ) {
				$balances[ $i ]['coin']    = $file_info->getBasename( '.coin' );
				$balances[ $i ]['balance'] = file_get_contents( $file_info->getPathname() );
				$i ++;
			}
		}

		return $balances;
	}

	/**
	 * Send the balance and coin info to the main server to record.
	 *
	 * @return bool|void
	 */
	public function runCron() {
		$info = $this->getInfo();

		if ( ! empty( $info->balance ) ) {
			return $this->sendRecord( [
				'coin'    => $this->coin,
				'balance' => $info->balance,
			] );
		}

		return FALSE;
	}

	/**
	 * Get coin information from the CLI command.
	 *
	 * @return \stdClass
	 */
	private function getInfo() {
		if ( empty( $this->test ) ) {
			exec( $this->cli_command, $output );
		} else {
			$output          = new \stdClass();
			$output->balance = self::randFloat( 0, 20000 );
		}

		return $output;
	}

	/**
	 * Call this method to get singleton
	 *
	 * @return Coin
	 */
	public static function Instance() {
		static $inst = NULL;
		if ( $inst === NULL ) {
			$inst = new Coin();
		}

		return $inst;
	}

	/**
	 * Send balance info to env: RECORDER
	 *
	 * @param array $data
	 * @return bool
	 */
	public function sendRecord( $data = [] ) {
		try {
			$this->client->request( 'POST', $this->recorder, [
				'form_params' => $data,
				'headers'     => [
					'X-Set-Balance' => 1,
				],
			] );
		} catch ( GuzzleException $e ) {
			return $this->sendError( [ 'error' => $e->getMessage() ] );
		}

		return true;
	}
}