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

use Contao\Input;
use Contao\Template;
use Contao\Module;
use IsotopeDirect\Interfaces\IsotopeDirectFilter;

/**
 * Class Keywords
 * Keywords filter
 */
class Keywords extends Filter implements IsotopeDirectFilter
{
	
	/**
	 * Filter key
	 * @var string
	 */
	protected static $strKey = 'keywords';

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
    	if($blnGenURL)
    	{
	    	// Return the URL fragment needed for this filter to pass to the lister
	    	if ((Input::post(static::$strKey)) && trim(Input::post(static::$strKey)) != $GLOBALS['TL_LANG']['MSC']['defaultSearchText'])
	    	{
		    	return static::$strKey . '/' . urlencode(Filter::cleanChars(Input::post(static::$strKey)));
	    	}
	    	
	    	return false;
    	}
    	
		$objTemplate->hasSearch = true;
		$objTemplate->hasAutocomplete = strlen($objModule->iso_searchAutocomplete) ? true : false;
		$objTemplate->keywords = htmlentities(Filter::uncleanChars(Input::get(static::$strKey)));
		$objTemplate->pkeywordsLabel = $GLOBALS['TL_LANG']['MSC'][static::$strKey.'FilterLabel'];
		$objTemplate->defaultSearchText = $GLOBALS['TL_LANG']['MSC']['defaultSearchText'];

	}

}