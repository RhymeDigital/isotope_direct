<?php

/**
 * Copyright (C) 2021 Rhyme Digital, LLC
 *
 * @author		Blair Winans <blair@rhyme.digital>
 * @author		Adam Fisher <adam@rhyme.digital>
 * @link		https://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace IsotopeDirect\Filter;

use Contao\Template;
use Contao\Module;
use IsotopeDirect\Interfaces\IsotopeDirectFilter;

/**
 * Class PriceRange
 * Price range filter
 */
class PriceRange extends Filter implements IsotopeDirectFilter
{
	/**
	 * Filter key
	 * @var string
	 */
	protected static $strKey = 'pricerange';

    /**
     * Add this filter to the module's template or get the URL params
     * @param array $arrCategories
     * @param Template $objTemplate
     * @param Module $objModule
     * @param bool $blnGenURL
     * @return bool|mixed|string
     */
	public static function generateFilter(array &$arrCategories, Template &$objTemplate, Module $objModule, bool $blnGenURL=false)
	{
        $arrRanges = static::getPriceRanges();

        // !HOOK: custom price ranges
        if (isset($GLOBALS['ISO_HOOKS']['getFilterPriceRanges']) && is_array($GLOBALS['ISO_HOOKS']['getFilterPriceRanges']))
        {
            foreach ($GLOBALS['ISO_HOOKS']['getFilterPriceRanges'] as $callback)
            {
                $objCallback = \System::importStatic($callback[0]);
                $arrRanges = $objCallback->{$callback[1]}($arrRanges, $arrCategories, $objTemplate, $objModule, $blnGenURL);
            }
        }

    	if($blnGenURL)
    	{
    		$arrPosted = (array) \Input::post(static::$strKey);
    		
	    	//return the URL fragment needed for this filter to pass to the lister
	    	if(!empty($arrPosted))
	    	{
				$arrURLFilters = array();
			
		    	foreach($arrPosted as $post)
		    	{
		    		//Check that they exist
			    	if (strlen(trim($post)) && (in_array($post, array_keys($arrRanges)) || in_array(htmlentities($post), array_keys($arrRanges))))
			    	{
				    	$arrURLFilters[] = $post;
			    	}
		    	}
		    			    	
		    	if (count($arrURLFilters) > 0)
		    	{
		    		return static::$strKey . '/' . urlencode(Filter::cleanChars(implode(',', $arrURLFilters)));
				}
	    	}
	    	
	    	return false;
    	}
    	
    	if(count($arrRanges) > 0)
    	{
    		$arrChecked = \Input::get(static::$strKey) ? explode(',', \Input::get(static::$strKey)) : array();
			$objTemplate->hasPriceFilter = true;
			$objTemplate->price = array_map('htmlentities', $arrRanges);
			$objTemplate->priceselected = array_map('htmlentities', array_map(array('IsotopeDirect\Filter\Filter', 'uncleanChars'), $arrChecked));
			$objTemplate->ppriceLabel = $GLOBALS['TL_LANG']['MSC'][static::$strKey.'FilterLabel'];
			$objTemplate->priceBlankLabel = $GLOBALS['TL_LANG']['MSC']['directBlankOptionLabel'];
    	}
    	
	}
	
	/*
	 * Return default price ranges
	 * @return array
	 */
	protected static function getPriceRanges()
	{
		return array(
    	    '0to100'			=>'Under $100', 
    	    '100to200'			=>'$100 to $200', 
    	    '200to500'			=>'$200 to $500',
    	    '500to1000'			=>'$500 to $1,000',
    	    '1000to5000'		=>'$1,000 to $5,000',
    	    '5000to'			=>'$5,000+',
        );
	}


}