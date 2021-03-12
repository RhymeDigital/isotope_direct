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
use Contao\System;
use Contao\Controller;
use Contao\StringUtil;
use Isotope\Model\Product;
use IsotopeDirect\Interfaces\IsotopeDirectFilter;

/**
 * Class Sorting
 * Sorting filter
 */
class Sorting extends Filter implements IsotopeDirectFilter
{
	
	/**
	 * Filter key
	 * @var string
	 */
	protected static $strKey = 'sorting';

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
        System::loadLanguageFile(Product::getTable());
        Controller::loadDataContainer(Product::getTable());
        
		$arrFields = StringUtil::deserialize($objModule->iso_sortingFields, true);

    	if($blnGenURL)
    	{
	    	//return the URL fragment needed for this filter to pass to the lister
	    	if (Input::post(static::$strKey) && in_array(str_replace(array('-asc', '-desc'), '', Input::post(static::$strKey)), $arrFields))
	    	{
		    	return static::$strKey . '/' . urlencode(Input::post(static::$strKey));
	    	}
	    	
	    	return false;
    	}
		
    	$arrAvailable = array(''=>$GLOBALS['TL_LANG']['MSC']['relevancyFilterLabel']);
		
		foreach ($arrFields as $field)
		{
			list($asc, $desc) = static::getSortingLabels($field);
			$strLabel = is_array($GLOBALS['TL_DCA'][Product::getTable()]['fields'][$field]['label']) ? $GLOBALS['TL_DCA'][Product::getTable()]['fields'][$field]['label'][0] : $field;
			$arrAvailable[$field.'-asc'] = $strLabel . ' ' . $asc;
			$arrAvailable[$field.'-desc'] = $strLabel . ' ' . $desc;
		}
    	
    	if(count($arrAvailable) > 0)
    	{
			$objTemplate->hasSorting = true;
			$objTemplate->sort = $arrAvailable;
			$objTemplate->sortselected = Input::get(static::$strKey) ?: ($objModule->iso_listingSortField ? $objModule->iso_listingSortField.'-'.strtolower($objModule->iso_listingSortDirection) : '');
			$objTemplate->psortLabel = $GLOBALS['TL_LANG']['MSC'][static::$strKey.'FilterLabel'];
    	}
	}


    /**
     * Get the sorting labels (asc/desc) for an attribute
     * @param string
     * @return array
     */
    protected static function getSortingLabels($field)
    {
        $arrData = $GLOBALS['TL_DCA'][Product::getTable()]['fields'][$field];

        switch ($arrData['eval']['rgxp'])
        {
            case 'price':
            case 'digit':
                return array($GLOBALS['TL_LANG']['MSC']['low_to_high'], $GLOBALS['TL_LANG']['MSC']['high_to_low']);

            case 'date':
            case 'time':
            case 'datim':
                return array($GLOBALS['TL_LANG']['MSC']['old_to_new'], $GLOBALS['TL_LANG']['MSC']['new_to_old']);
        }

        // !HOOK: custom sorting labels
        if (isset($GLOBALS['ISO_HOOKS']['sortingLabels']) && is_array($GLOBALS['ISO_HOOKS']['sortingLabels']))
        {
            foreach ($GLOBALS['ISO_HOOKS']['sortingLabels'] as $callback)
            {
                $objCallback = \System::importStatic($callback[0]);
                $varReturn = $objCallback->{$callback[1]}($field, $arrData, null);
                
                if ($varReturn !== false)
                {
	                return $varReturn;
                }
            }
        }

        return array($GLOBALS['TL_LANG']['MSC']['a_to_z'], $GLOBALS['TL_LANG']['MSC']['z_to_a']);
    }

}
