<?php

/**
 * Copyright (C) 2015 Rhyme Digital, LLC
 *
 * @author		Blair Winans <blair@rhyme.digital>
 * @author		Adam Fisher <adam@rhyme.digital>
 * @link		http://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace IsotopeDirect\Filter;

use Contao\Cache;
use Contao\Database;
use Contao\Controller;
use IsotopeDirect\Interfaces\IsotopeDirectFilter;
use Isotope\Model\Product;
use Isotope\Model\ProductCategory;

/**
 * Class Filter
 * Base class for IsotopeDirect filters
 */
abstract class Filter extends Controller implements IsotopeDirectFilter
{
	
	/**
	 * Filter key
	 * @var string
	 */
	protected static $strKey = '';
    
	
    /**
     * Clean characters
     * @param   string
     * @return  string
     */
	public static function cleanChars($value)
	{
		return str_replace(' ', '--', str_replace('/', '||', $value));
	}
    
	
    /**
     * Put characters back
     * @param   string
     * @return  string
     */
	public static function uncleanChars($value)
	{
		return str_replace('--', ' ', str_replace('||', '/', $value));
	}
    
    /**
     * Add this filter to the module's template or get the URL params
     * @param   array
     * @param   object
     * @param   array
     * @param   object
     * @param   boolean
     * @return  mixed string|bool|void
     */
	public static function generateFilter(&$arrCategories, &$objTemplate, $objModule, $blnGenURL=false)
    {
    }
	

    /**
     * Find all available property types and return as array
     * @param   array
     * @return  array
     */
    public static function findAllAvailable(&$arrCategories, $arrOptions=[])
    {
    	$strHash = md5(implode(',', $arrCategories));
    	
    	if (!Cache::has(static::$strKey . '-' . $strHash))
    	{
	        $t = Product::getTable();
	        $arrAvailable = array();
	        
	        if(!is_array($arrCategories) || empty($arrCategories))
	        {
		        $arrCategories = array(0);
	        }
	        
	        //This query is by far the fastest way to get the available attributes	        
	        $strQuery = "SELECT $t.".static::$strKey." FROM $t WHERE ".static::$strKey." != '' AND $t.id IN (" . implode(',', static::getProductsForCategories($arrCategories)) . ")";
	        
	        if (BE_USER_LOGGED_IN !== true) {
	            $time = time();
	            $strQuery .= " AND $t.published='1' AND ($t.start='' OR $t.start<$time) AND ($t.stop='' OR $t.stop>$time)";
	        }

	        $objResult = Database::getInstance()->execute($strQuery);
	        
	        if ($objResult->numRows)
	        {
		        while ($objResult->next())
		        {
			        if (strlen($objResult->{static::$strKey}) && !in_array($objResult->{static::$strKey}, $arrAvailable))
			        {
				        $arrAvailable[specialchars($objResult->{static::$strKey})] = $objResult->{static::$strKey};
			        }
		        }
	        }
	        
	        ksort($arrAvailable);
	        	        
	        Cache::set(static::$strKey . '-' . $strHash, $arrAvailable);
	    }
				
        return Cache::get(static::$strKey . '-' . $strHash);
    }
    
	
    /**
     * Load the products for the provided categories if they haven't been already
     * @param   array
     * @return  array
     */
	public static function getProductsForCategories(&$arrCategories)
	{
    	$strHash = md5(implode(',', $arrCategories));
    	
    	if (!Cache::has('category-products-' . $strHash))
    	{
	        $c = ProductCategory::getTable();
	        
	        if(!is_array($arrCategories) || empty($arrCategories))
	        {
		        $arrCategories = array(0);
	        }
	        
	        $strQuery = "SELECT pid AS `product_id` FROM $c WHERE $c.page_id IN (" . implode(',', $arrCategories) . ")";
	        
	        $arrIds = Database::getInstance()->prepare($strQuery)->execute()->fetchEach('product_id');
	        
	        Cache::set('category-products-' . $strHash, (empty($arrIds) ? array(0) : $arrIds));
	    }
	    
        return Cache::get('category-products-' . $strHash);
	}

}
