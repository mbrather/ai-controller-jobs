<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2017-2020
 * @package Controller
 * @subpackage Common
 */


namespace Aimeos\Controller\Common\Order\Export\Csv\Processor\Address;


/**
 * Address processor for order CSV exports
 *
 * @package Controller
 * @subpackage Common
 */
class Standard
	extends \Aimeos\Controller\Common\Order\Export\Csv\Processor\Base
	implements \Aimeos\Controller\Common\Order\Export\Csv\Processor\Iface
{
	/** controller/common/order/export/csv/processor/address/name
	 * Name of the address processor implementation
	 *
	 * Use "Myname" if your class is named "\Aimeos\Controller\Common\Order\Export\Csv\Processor\Address\Myname".
	 * The name is case-sensitive and you should avoid camel case names like "MyName".
	 *
	 * @param string Last part of the processor class name
	 * @since 2017.08
	 * @category Developer
	 */


	/**
	 * Returns the order related data
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $invoice Invoice item
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $order Full order with associated items
	 * @return array Two dimensional associative list of order data representing the lines in CSV
	 */
	public function process( \Aimeos\MShop\Order\Item\Iface $invoice, \Aimeos\MShop\Order\Item\Base\Iface $order ) : array
	{
		$result = [];

		foreach( $order->getAddresses()->krsort() as $items )
		{
			foreach( $items as $item )
			{
				$data = [];
				$list = $item->toArray( true );

				foreach( $this->getMapping() as $pos => $key )
				{
					if( array_key_exists( $key, $list ) ) {
						$data[$pos] = $list[$key];
					} else {
						$data[$pos] = '';
					}
				}

				ksort( $data );
				$result[] = $data;
			}
		}

		return $result;
	}
}
