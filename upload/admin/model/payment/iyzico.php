<?php

	namespace Opencart\Admin\Model\Extension\iyzico\Payment;

	use Opencart\System\Engine\Model;

	class iyzico extends Model
	{

		public function install()
		{
			$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "iyzico_order` (
			  `iyzico_order_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `payment_id` INT(11) NOT NULL,
			  `order_id` INT(11) NOT NULL,
			  `total_amount` DECIMAL( 10, 2 ) NOT NULL,
			  `status` VARCHAR(20) NOT NULL,
			  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			  PRIMARY KEY (`iyzico_order_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

			$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "iyzico_card` (
			  	`iyzico_card_id` INT(11) NOT NULL AUTO_INCREMENT,
			  	`customer_id` INT(11) NOT NULL,
				`card_user_key` VARCHAR(50),
				`api_key` VARCHAR(50),
			  	`created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			  	PRIMARY KEY (`iyzico_card_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
		}

		public function uninstall()
		{
			$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "iyzico_order`;");
			$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "iyzico_card`;");
		}

	}
