<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2021
 * @package Controller
 * @subpackage Common
 */


namespace Aimeos\Controller\Common\Product\Import\Csv\Processor\Product;

use \Aimeos\MW\Logger\Base as Log;


/**
 * Product processor for CSV imports
 *
 * @package Controller
 * @subpackage Common
 */
class Standard
	extends \Aimeos\Controller\Common\Product\Import\Csv\Processor\Base
	implements \Aimeos\Controller\Common\Product\Import\Csv\Processor\Iface
{
	/** controller/common/product/import/csv/processor/product/name
	 * Name of the product processor implementation
	 *
	 * Use "Myname" if your class is named "\Aimeos\Controller\Common\Product\Import\Csv\Processor\Product\Myname".
	 * The name is case-sensitive and you should avoid camel case names like "MyName".
	 *
	 * @param string Last part of the processor class name
	 * @since 2015.10
	 * @category Developer
	 */

	private $cache;
	private $listTypes;


	/**
	 * Initializes the object
	 *
	 * @param \Aimeos\MShop\Context\Item\Iface $context Context object
	 * @param array $mapping Associative list of field position in CSV as key and domain item key as value
	 * @param \Aimeos\Controller\Common\Product\Import\Csv\Processor\Iface $object Decorated processor
	 */
	public function __construct( \Aimeos\MShop\Context\Item\Iface $context, array $mapping,
		\Aimeos\Controller\Common\Product\Import\Csv\Processor\Iface $object = null )
	{
		parent::__construct( $context, $mapping, $object );

		/** controller/common/product/import/csv/processor/product/listtypes
		 * Names of the product list types that are updated or removed
		 *
		 * Aimeos offers associated items like "bought together" products that
		 * are automatically generated by other job controllers. These relations
		 * shouldn't normally be overwritten or deleted by default during the
		 * import and this confiuration option enables you to specify the list
		 * types that should be updated or removed if not available in the import
		 * file.
		 *
		 * Contrary, if you don't generate any relations automatically in the
		 * shop and want to import those relations too, you can set the option
		 * to null to update all associated items.
		 *
		 * @param array|null List of product list type names or null for all
		 * @since 2015.05
		 * @category Developer
		 * @category User
		 * @see controller/common/product/import/csv/domains
		 * @see controller/common/product/import/csv/processor/attribute/listtypes
		 * @see controller/common/product/import/csv/processor/catalog/listtypes
		 * @see controller/common/product/import/csv/processor/media/listtypes
		 * @see controller/common/product/import/csv/processor/price/listtypes
		 * @see controller/common/product/import/csv/processor/text/listtypes
		 */
		$key = 'controller/common/product/import/csv/processor/product/listtypes';
		$this->listTypes = $context->config()->get( $key, ['default', 'suggestion'] );

		if( $this->listTypes === null )
		{
			$this->listTypes = [];
			$manager = \Aimeos\MShop::create( $context, 'product/lists/type' );

			$search = $manager->filter()->slice( 0, 0x7fffffff );
			$search->setConditions( $search->compare( '==', 'product.lists.type.domain', 'product' ) );

			foreach( $manager->search( $search ) as $item ) {
				$this->listTypes[$item->getCode()] = $item->getCode();
			}
		}
		else
		{
			$this->listTypes = array_flip( $this->listTypes );
		}

		$this->cache = $this->getCache( 'product' );
	}


	/**
	 * Saves the product related data to the storage
	 *
	 * @param \Aimeos\MShop\Product\Item\Iface $product Product item with associated items
	 * @param array $data List of CSV fields with position as key and data as value
	 * @return array List of data which has not been imported
	 */
	public function process( \Aimeos\MShop\Product\Item\Iface $product, array $data ) : array
	{
		$context = $this->context();
		$logger = $context->getLogger();
		$manager = \Aimeos\MShop::create( $context, 'product/lists' );
		$separator = $context->config()->get( 'controller/common/product/import/csv/separator', "\n" );

		$listItems = $product->getListItems( 'product', null, null, false );
		$this->cache->set( $product );

		foreach( $this->getMappedChunk( $data, $this->getMapping() ) as $list )
		{
			if( $this->checkEntry( $list ) === false ) {
				continue;
			}

			$listtype = $this->val( $list, 'product.lists.type', 'default' );
			$this->addType( 'product/lists/type', 'product', $listtype );

			foreach( explode( $separator, $this->val( $list, 'product.code', '' ) ) as $code )
			{
				$code = trim( $code );

				if( ( $prodid = $this->cache->get( $code ) ) === null )
				{
					$msg = 'No product for code "%1$s" available when importing product with code "%2$s"';
					$logger->log( sprintf( $msg, $code, $product->getCode() ), Log::WARN, 'import/csv/product' );
				}

				if( ( $listItem = $product->getListItem( 'product', $listtype, $prodid ) ) === null ) {
					$listItem = $manager->create()->setType( $listtype );
				} else {
					unset( $listItems[$listItem->getId()] );
				}

				$listItem = $listItem->fromArray( $list )->setRefId( $prodid );
				$product->addListItem( 'product', $listItem );
			}
		}

		$product->deleteListItems( $listItems->toArray() );

		return $this->object()->process( $product, $data );
	}


	/**
	 * Checks if an entry can be used for updating a media item
	 *
	 * @param array $list Associative list of key/value pairs from the mapping
	 * @return bool True if valid, false if not
	 */
	protected function checkEntry( array $list ) : bool
	{
		if( $this->val( $list, 'product.code' ) === null ) {
			return false;
		}

		if( ( $type = $this->val( $list, 'product.lists.type' ) ) && !isset( $this->listTypes[$type] ) )
		{
			$msg = sprintf( 'Invalid type "%1$s" (%2$s)', $type, 'product list' );
			throw new \Aimeos\Controller\Common\Exception( $msg );
		}

		return true;
	}
}
