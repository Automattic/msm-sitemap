<?php

if ( false === class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public function __call( $method, $params ) {
			return;
		}

		public static function __callStatic( $method, $params ) {
			return;
		}
	}
}

if ( false === class_exists( 'WP_CLI_Command' ) ) {
	class WP_CLI_Command {
		public function __call( $method, $params ) {
			return;
		}

		public static function __callStatic( $method, $params ) {
			return;
		}
	}
}