<?php

	namespace Opencart\Catalog\Model\Extension\iyzico\Total;

	use Opencart\System\Engine\Model;

	class IyzicoFee extends Model
	{

		public function confirm($order_info, $order_total)
		{
			return true;
		}

	}
